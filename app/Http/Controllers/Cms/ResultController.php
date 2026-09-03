<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Models\Champion;
use App\Models\OlympicResult;
use App\Models\Tournament;
use App\Support\Csv;
use App\Support\Media\StoredFile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Results & Winners — menu terpisah yang dijanjikan desain Add Tournament
 * (`596:11483`).
 *
 * Tiga layar dalam satu modul, karena situs publik meminta tiga hal berbeda:
 *
 * - `/results` — pemenang per turnamen, hanya untuk yang sudah SELESAI
 * - `/results/champions` — Champions Hall lintas tahun (`381:17645`)
 * - `/results/olympic` — tabel hasil Olympic
 *
 * Tidak ada layar desainnya: kanvas Backoffice tidak menggambar satu pun.
 * Bentuknya meminjam pola modul lain; penyimpangannya dicatat di
 * docs/PROGRESS.md.
 */
class ResultController extends Controller
{
    /**
     * Daftar turnamen yang pemenangnya bisa diisi.
     *
     * Hanya yang SUDAH SELESAI — "managed from a separate menu after the
     * tournament is completed". Turnamen yang belum dimainkan tidak punya
     * pemenang, dan menawarkan formulirnya mengundang orang mengarang hasil.
     */
    public function index(Request $request): Response
    {
        $search = $request->string('q')->toString();

        return Inertia::render('Results/Index', [
            'tournaments' => Tournament::query()
                ->withCount('winners')
                ->whereDate('ends_on', '<', now()->startOfDay())
                ->when($search !== '', fn ($q) => $q->where('name', 'ilike', "%{$search}%"))
                ->orderByDesc('ends_on')
                ->paginate(config('dwf.per_page'))
                ->withQueryString()
                ->through(fn (Tournament $t) => [
                    'id' => $t->id,
                    'name' => $t->name,
                    'location' => $t->location,
                    'endsOn' => $t->ends_on?->toIso8601String(),
                    'winnerCount' => $t->winners_count,
                ]),
            'filters' => ['q' => $search],
        ]);
    }

    /** Formulir pemenang satu turnamen. */
    public function edit(Tournament $tournament): Response
    {
        $tournament->load('winners');

        if ($tournament->stage !== 'completed') {
            throw ValidationException::withMessages([
                'tournament' => __('backoffice.results.not_completed'),
            ]);
        }

        return Inertia::render('Results/Winners', [
            'tournament' => [
                'id' => $tournament->id,
                'name' => $tournament->name,
                'location' => $tournament->location,
                'endsOn' => $tournament->ends_on?->toIso8601String(),
            ],
            'winners' => $tournament->winners->map(fn ($w) => [
                'id' => $w->id,
                'rank_label' => $w->rank_label,
                'names' => $w->names,
                'country' => $w->country,
                'portraitUrls' => collect($w->portrait_paths ?? [])
                    ->map(fn (string $path) => StoredFile::url($path))
                    ->all(),
            ])->all(),
        ]);
    }

    /**
     * Menulis ulang pemenang satu turnamen.
     *
     * Dihapus lalu ditulis ulang, alasan yang sama dengan ofisial dan jadwal di
     * formulir turnamen: urutannya ditentukan orang, dan mencocokkan baris lama
     * dengan yang baru menuntut id yang tidak berarti apa-apa baginya.
     *
     * Potret dipertahankan lewat ID barisnya, BUKAN path dari klien — path dari
     * klien adalah jalan masuk untuk menunjuk berkas mana pun di disk.
     */
    public function update(Request $request, Tournament $tournament): RedirectResponse
    {
        $uploads = config('dwf.uploads');

        $data = $request->validate([
            'winners' => ['array', 'max:20'],
            'winners.*.id' => [
                'nullable', 'integer',
                Rule::exists('tournament_winners', 'id')
                    ->where('tournament_id', $tournament->id),
            ],
            'winners.*.rank_label' => ['required', 'string', 'max:40'],
            'winners.*.names' => ['required', 'string', 'max:255'],
            'winners.*.country' => ['required', 'string', 'max:120'],
            'winners.*.portraits' => ['array', 'max:4'],
            'winners.*.portraits.*' => [
                'image',
                'mimes:'.implode(',', $uploads['image_mimes']),
                'max:'.$uploads['image_max_kb'],
            ],
        ], attributes: [
            'winners' => __('backoffice.results.winners'),
        ]);

        DB::transaction(function () use ($request, $tournament, $data) {
            $existing = $tournament->winners()->pluck('portrait_paths', 'id');
            $kept = [];

            foreach (array_values($data['winners'] ?? []) as $index => $winner) {
                $uploaded = collect($request->file("winners.{$index}.portraits") ?? [])
                    ->map(fn ($file) => StoredFile::put($file, 'winners'))
                    ->all();

                // Unggahan baru MENGGANTI potret lama; kalau tidak ada yang
                // diunggah, yang lama dipertahankan lewat id barisnya.
                $kept[$index] = $uploaded !== []
                    ? $uploaded
                    : ($existing[$winner['id'] ?? null] ?? []);
            }

            // Berkas yang tidak lagi dipakai baris mana pun dibuang.
            $stillUsed = collect($kept)->flatten()->all();

            foreach ($existing->flatten()->filter()->all() as $path) {
                if (! in_array($path, $stillUsed, true)) {
                    StoredFile::forget($path);
                }
            }

            $tournament->winners()->delete();

            foreach (array_values($data['winners'] ?? []) as $index => $winner) {
                $tournament->winners()->create([
                    'rank_label' => $winner['rank_label'],
                    'names' => $winner['names'],
                    'country' => $winner['country'],
                    'portrait_paths' => $kept[$index] ?: null,
                    'position' => $index + 1,
                ]);
            }

            // Turnamennya sendiri ikut ditandai berubah: kolom "Last Modified"
            // di daftar turnamen harus bergerak saat hasilnya diisi, dan
            // Eloquent melewati baris yang tidak kotor.
            $tournament->touch();
        });

        return back()->with('success', __('backoffice.results.winners_saved'));
    }

    // ------------------------------------------------------ Champions Hall

    public function champions(Request $request): Response
    {
        $search = $request->string('q')->toString();

        return Inertia::render('Results/Champions', [
            'champions' => Champion::query()
                ->with('editor:id,name')
                ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                    ->where('name', 'ilike', "%{$search}%")
                    ->orWhere('event', 'ilike', "%{$search}%")))
                ->ordered()
                ->get()
                ->map(fn (Champion $c) => [
                    'id' => $c->id,
                    'event' => $c->event,
                    'name' => $c->name,
                    'portraitUrl' => StoredFile::url($c->portrait_path),
                    'portraitAlt' => $c->portrait_alt,
                    'isActive' => $c->is_active,
                    'updatedAt' => $c->updated_at?->toIso8601String(),
                    'updatedBy' => $c->editor?->name,
                ])
                ->all(),
            'filters' => ['q' => $search],
        ]);
    }

    public function storeChampion(Request $request): RedirectResponse
    {
        $champion = Champion::create($this->championPayload($request) + [
            'position' => Champion::nextPosition(),
        ]);

        return back()->with('success', __('backoffice.results.champion_saved', ['name' => $champion->name]));
    }

    public function updateChampion(Request $request, Champion $champion): RedirectResponse
    {
        $champion->update($this->championPayload($request, $champion));

        return back()->with('success', __('backoffice.results.champion_updated'));
    }

    public function destroyChampion(Champion $champion): RedirectResponse
    {
        StoredFile::forget($champion->portrait_path);
        $champion->delete();

        return back()->with('success', __('backoffice.results.champion_deleted'));
    }

    /** @return array<string, mixed> */
    private function championPayload(Request $request, ?Champion $champion = null): array
    {
        $uploads = config('dwf.uploads');

        $data = $request->validate([
            'event' => ['required', 'string', 'max:160'],
            'name' => ['required', 'string', 'max:160'],
            'portrait' => [
                'nullable', 'image',
                'mimes:'.implode(',', $uploads['image_mimes']),
                'max:'.$uploads['image_max_kb'],
            ],
            'portrait_alt' => ['nullable', 'string', 'max:200'],
            'is_active' => ['required', 'boolean'],
        ], attributes: [
            'event' => __('backoffice.results.champion_event'),
            'name' => __('backoffice.results.champion_name'),
        ]);

        $payload = [
            'event' => $data['event'],
            'name' => $data['name'],
            'portrait_alt' => $data['portrait_alt'] ?? null,
            'is_active' => $data['is_active'],
        ];

        if ($request->hasFile('portrait')) {
            $payload['portrait_path'] = StoredFile::put(
                $request->file('portrait'),
                'champions',
                $champion?->portrait_path,
            );
        }

        return $payload;
    }

    // ----------------------------------------------------- Olympic results

    public function olympic(): Response
    {
        return Inertia::render('Results/Olympic', [
            'results' => OlympicResult::query()->ordered()->get()
                ->map(fn (OlympicResult $r) => [
                    'year' => $r->year,
                    'event' => $r->event,
                    'category' => $r->category,
                    'winners' => $r->winners,
                    'federation' => $r->federation,
                    'is_active' => $r->is_active,
                ])
                ->all(),
        ]);
    }

    /** Menulis ulang seluruh tabel — sama seperti statistik federasi. */
    public function updateOlympic(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'results' => ['array', 'max:100'],
            // String, bukan integer: "2024–25" wajar untuk ajang lintas tahun.
            'results.*.year' => ['required', 'string', 'max:16'],
            'results.*.event' => ['required', 'string', 'max:160'],
            'results.*.category' => ['required', 'string', 'max:120'],
            'results.*.winners' => ['required', 'string', 'max:255'],
            'results.*.federation' => ['required', 'string', 'max:160'],
            'results.*.is_active' => ['required', 'boolean'],
        ], attributes: [
            'results' => __('backoffice.results.olympic'),
        ]);

        OlympicResult::query()->delete();

        foreach (array_values($data['results'] ?? []) as $index => $row) {
            OlympicResult::create($row + ['position' => $index + 1]);
        }

        return back()->with('success', __('backoffice.results.olympic_saved'));
    }

    public function exportOlympic(): StreamedResponse
    {
        $rows = OlympicResult::query()->ordered()->lazy();

        return Csv::stream('olympic-results', [
            'ID', 'Year', 'Event', 'Category', 'Winners', 'Federation', 'Status',
        ], $rows->map(fn (OlympicResult $r) => [
            $r->id,
            $r->year,
            $r->event,
            $r->category,
            $r->winners,
            $r->federation,
            $r->is_active ? 'active' : 'inactive',
        ]));
    }
}
