<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cms\TournamentRequest;
use App\Models\Document;
use App\Models\Tournament;
use App\Models\TournamentNotification;
use App\Support\Csv;
use App\Support\Media\StoredFile;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Mews\Purifier\Facades\Purifier;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Turnamen — formulir `585:11241`.
 *
 * Layar DAFTARNYA tidak ada di file desain: node itu hanya menggambar Add
 * Tournament. Daftarnya dibangun mengikuti pola yang sudah dipakai Documents
 * dan Gallery (`369:5236`, `478:5884`) — kolom identitas, Visibility di dalam
 * sel, lalu "siapa + kapan" — supaya modul ini tidak jadi satu-satunya yang
 * bentuknya berbeda. Penyimpangannya dicatat di docs/PROGRESS.md.
 */
class TournamentController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $this->filters($request);

        return Inertia::render('Tournaments/Index', [
            'tournaments' => $this->filtered($filters)
                ->paginate(config('dwf.per_page'))
                ->withQueryString()
                ->through(fn (Tournament $t) => [
                    'id' => $t->id,
                    'name' => $t->name,
                    'coverage' => $t->coverage,
                    'location' => $t->location,
                    'startsOn' => $t->starts_on?->toIso8601String(),
                    'endsOn' => $t->ends_on?->toIso8601String(),
                    'stage' => $t->stage,
                    'registrationState' => $t->registration_state,
                    'visibility' => $t->visibility,
                    'scheduledFor' => $t->visibility === 'scheduled' ? $t->published_at?->toIso8601String() : null,
                    'canSchedule' => $t->published_at?->isFuture() ?? false,
                    'publishedAt' => $t->published_at?->toIso8601String(),
                    'publishedBy' => $t->publisher?->name,
                    'updatedAt' => $t->updated_at?->toIso8601String(),
                    'updatedBy' => $t->editor?->name,
                    // Berapa orang menekan "Notify me" di halaman publiknya.
                    // Angka, bukan daftar: alamatnya cuma keluar lewat ekspor,
                    // yang dijaga izin terpisah dan dicatat di jejak audit.
                    'notifyCount' => $t->notifications_count,
                ]),
            'coverages' => config('dwf.tournaments.coverage'),
            'filters' => $filters,
        ]);
    }

    /** @return array{q: string, status: string, coverage: string, stage: string} */
    private function filters(Request $request): array
    {
        return [
            'q' => $request->string('q')->toString(),
            'status' => $request->string('status')->toString(),
            'coverage' => $request->string('coverage')->toString(),
            'stage' => $request->string('stage')->toString(),
        ];
    }

    /**
     * Query daftar — dipakai layar daftar DAN ekspor, supaya keduanya sepakat.
     *
     * `stage` disaring lewat TANGGAL, bukan kolom: ia memang tidak disimpan
     * (lihat `Tournament::getStageAttribute()`). Menyimpannya berarti kolom
     * yang basi setiap tengah malam.
     *
     * @param  array{q: string, status: string, coverage: string, stage: string}  $filters
     */
    private function filtered(array $filters): Builder
    {
        $today = CarbonImmutable::now()->startOfDay();

        return Tournament::query()
            ->with(['editor:id,name', 'publisher:id,name'])
            ->withCount('notifications')
            ->when($filters['q'] !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'ilike', "%{$filters['q']}%")
                ->orWhere('city', 'ilike', "%{$filters['q']}%")
                ->orWhere('country', 'ilike', "%{$filters['q']}%")))
            ->when(
                in_array($filters['status'], Tournament::STATUSES, true),
                fn ($q) => $q->where('status', $filters['status']),
            )
            ->when($filters['coverage'] !== '', fn ($q) => $q->where('coverage', $filters['coverage']))
            ->when($filters['stage'] === 'upcoming', fn ($q) => $q->whereDate('starts_on', '>', $today))
            ->when($filters['stage'] === 'completed', fn ($q) => $q->whereDate('ends_on', '<', $today))
            ->when($filters['stage'] === 'live', fn ($q) => $q
                ->whereDate('starts_on', '<=', $today)
                ->whereDate('ends_on', '>=', $today))
            ->orderByDesc('starts_on');
    }

    public function export(Request $request): StreamedResponse
    {
        $rows = $this->filtered($this->filters($request))->lazy();

        return Csv::stream('tournaments', [
            'ID', 'Name', 'Slug', 'Coverage', 'City', 'Country', 'Starts On', 'Ends On',
            'Stage', 'Registration', 'Participants', 'Participant Type',
            'Visibility', 'Published At', 'Published By', 'Last Modified At', 'Last Modified By',
        ], $rows->map(fn (Tournament $t) => [
            $t->id,
            $t->name,
            $t->slug,
            $t->coverage,
            $t->city,
            $t->country,
            $t->starts_on?->toDateString(),
            $t->ends_on?->toDateString(),
            $t->stage,
            $t->registration_state,
            $t->participant_count,
            $t->participant_type,
            $t->visibility,
            $t->published_at?->toDateTimeString(),
            $t->publisher?->name,
            $t->updated_at?->toDateTimeString(),
            $t->editor?->name,
        ]));
    }

    /** Sakelar visibilitas dari barisnya — janji ketiga `StandardListTest`. */
    /**
     * Daftar alamat yang menunggu kabar satu turnamen.
     *
     * Dijaga `tournaments.update`, bukan `.view` — mengunduh daftar alamat
     * orang adalah tindakan, bukan pembacaan, dan ia berpindah lewat surel dan
     * folder bersama begitu berkasnya jadi. Karena itu juga ia DICATAT: yang
     * hendak diaudit dari modul ini bukan siapa yang mendaftar, melainkan siapa
     * yang mengunduh daftarnya.
     */
    public function exportNotifications(Request $request, Tournament $tournament): StreamedResponse
    {
        activity('tournament')
            ->causedBy($request->user())
            ->performedOn($tournament)
            ->event('exported')
            ->withProperties(['count' => $tournament->notifications()->count()])
            ->log('exported');

        return Csv::stream("tournament-{$tournament->id}-notifications", ['Email', 'Requested At'],
            $tournament->notifications()->orderBy('created_at')->lazy()
                ->map(fn (TournamentNotification $n) => [
                    $n->email,
                    $n->created_at?->toDateTimeString(),
                ]));
    }

    public function visibility(Request $request, Tournament $tournament): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(Tournament::QUICK_STATUSES)],
        ]);

        if ($data['status'] === Tournament::STATUS_SCHEDULED && ! $tournament->published_at?->isFuture()) {
            throw ValidationException::withMessages([
                'status' => __('backoffice.news.needs_schedule'),
            ]);
        }

        if ($data['status'] === Tournament::STATUS_PUBLISHED && $tournament->published_at === null) {
            $tournament->published_at = now();
        }

        $tournament->status = $data['status'];
        $tournament->save();

        return back()->with('success', __('backoffice.tournaments.updated'));
    }

    public function create(): Response
    {
        return Inertia::render('Tournaments/Form', [
            'tournament' => null,
        ] + $this->formOptions());
    }

    public function store(TournamentRequest $request): RedirectResponse
    {
        $tournament = DB::transaction(function () use ($request) {
            $tournament = Tournament::create($this->payload($request));

            $this->syncChildren($request, $tournament);

            return $tournament;
        });

        return to_route('tournaments.index')->with(
            'success',
            __('backoffice.tournaments.saved', ['name' => $tournament->name]),
        );
    }

    public function edit(Tournament $tournament): Response
    {
        $tournament->load(['officials', 'scheduleEntries', 'documents:id']);

        return Inertia::render('Tournaments/Form', [
            'tournament' => [
                'id' => $tournament->id,
                'name' => $tournament->name,
                'slug' => $tournament->slug,
                'coverage' => $tournament->coverage,
                'startsOn' => $tournament->starts_on?->toDateString(),
                'endsOn' => $tournament->ends_on?->toDateString(),
                'city' => $tournament->city,
                'country' => $tournament->country,
                'rulesFormat' => $tournament->rules_format,
                'attendance' => $tournament->attendance,
                'heroImageUrl' => StoredFile::url($tournament->hero_image_path),
                'overview' => $tournament->overview,

                'venueName' => $tournament->venue_name,
                'venueAddress' => $tournament->venue_address,
                'venueLat' => (float) $tournament->venue_lat,
                'venueLng' => (float) $tournament->venue_lng,

                'prizeAmount' => $tournament->prize_amount,
                'prizeCurrency' => $tournament->prize_currency,
                'prizeDescription' => $tournament->prize_description,
                'prizeImageUrl' => StoredFile::url($tournament->prize_image_path),

                'contactEmail' => $tournament->contact_email,
                'contactPhone' => $tournament->contact_phone,

                'officials' => $tournament->officials->map(fn ($o) => [
                    // Dikirim balik supaya foto lama bisa dipertahankan tanpa
                    // mempercayai path dari klien — lihat `TournamentRequest`.
                    'id' => $o->id,
                    'name' => $o->name,
                    'role' => $o->role,
                    'country' => $o->country,
                    'photoUrl' => StoredFile::url($o->photo_path),
                ])->all(),

                'registrationStartsOn' => $tournament->registration_starts_on?->toDateString(),
                'registrationEndsOn' => $tournament->registration_ends_on?->toDateString(),
                'dwfIdRequirement' => $tournament->dwf_id_requirement,
                'eligibility' => $tournament->eligibility,
                'registrationMethod' => $tournament->registration_method,

                'schedule' => $tournament->scheduleEntries->map(fn ($e) => [
                    'held_on' => $e->held_on?->toDateString(),
                    'starts_at' => substr((string) $e->starts_at, 0, 5),
                    'activity' => $e->activity,
                    'area' => $e->area,
                ])->all(),

                'gameFormat' => $tournament->game_format,
                'participantCount' => $tournament->participant_count,
                'participantType' => $tournament->participant_type,
                'competitionSystem' => $tournament->competition_system,
                'scoring' => $tournament->scoring,

                'documents' => $tournament->documents->pluck('id')->all(),

                'status' => $tournament->status,
                'publishedAt' => $tournament->published_at?->format('Y-m-d\TH:i'),
            ],
        ] + $this->formOptions());
    }

    public function update(TournamentRequest $request, Tournament $tournament): RedirectResponse
    {
        DB::transaction(function () use ($request, $tournament) {
            $tournament->update($this->payload($request, $tournament));

            $this->syncChildren($request, $tournament);
        });

        return to_route('tournaments.index')->with('success', __('backoffice.tournaments.updated'));
    }

    public function destroy(Tournament $tournament): RedirectResponse
    {
        // Berkas ikut dibuang; barisnya dibersihkan `cascadeOnDelete`.
        StoredFile::forget($tournament->hero_image_path);
        StoredFile::forget($tournament->prize_image_path);

        foreach ($tournament->officials as $official) {
            StoredFile::forget($official->photo_path);
        }

        $tournament->delete();

        return back()->with('success', __('backoffice.tournaments.deleted'));
    }

    /**
     * Kolom induk yang siap disimpan.
     *
     * @return array<string, mixed>
     */
    private function payload(TournamentRequest $request, ?Tournament $tournament = null): array
    {
        $data = $request->validated();

        $payload = [
            'name' => $data['name'],
            'coverage' => $data['coverage'],
            'starts_on' => $data['starts_on'],
            'ends_on' => $data['ends_on'],
            'city' => $data['city'],
            'country' => $data['country'],
            'rules_format' => $data['rules_format'],
            'attendance' => $data['attendance'],
            // Ditulis lewat `RichTextEditor`, jadi isinya HTML — dan HTML dari
            // editor WAJIB dibersihkan sebelum disimpan. Sempat terlewat: satu-
            // satunya kolom editor di repo ini yang menyimpan mentah, dan ia
            // tampil di halaman turnamen situs publik.
            'overview' => Purifier::clean($data['overview']),

            'venue_name' => $data['venue_name'],
            'venue_address' => $data['venue_address'],
            'venue_lat' => $data['venue_lat'],
            'venue_lng' => $data['venue_lng'],

            'prize_amount' => $data['prize_amount'] ?? null,
            // Mata uang dikosongkan kalau nominalnya dihapus — kalau tidak,
            // "USD" bertahan sendirian tanpa angka di sebelahnya.
            'prize_currency' => filled($data['prize_amount'] ?? null) ? ($data['prize_currency'] ?? null) : null,
            'prize_description' => $data['prize_description'] ?? null,

            'contact_email' => $data['contact_email'] ?? null,
            'contact_phone' => $data['contact_phone'] ?? null,

            'registration_starts_on' => $data['registration_starts_on'] ?? null,
            'registration_ends_on' => $data['registration_ends_on'] ?? null,
            'dwf_id_requirement' => $data['dwf_id_requirement'] ?? null,
            'eligibility' => $data['eligibility'],
            'registration_method' => $data['registration_method'],

            'game_format' => $data['game_format'],
            'participant_count' => $data['participant_count'] ?? null,
            'participant_type' => $data['participant_type'],
            'competition_system' => $data['competition_system'],
            'scoring' => $data['scoring'],

            'status' => $request->resolvedStatus(),
            'published_at' => $this->resolvedPublishedAt($data),
        ];

        // Slug boleh diketik sendiri ("edit only when necessary"); kalau
        // dikosongkan ia lahir dari namanya.
        $source = filled($data['slug'] ?? null) ? $data['slug'] : $data['name'];
        $payload['slug'] = Tournament::uniqueSlug($source, $tournament?->id);

        if ($request->hasFile('hero_image')) {
            $payload['hero_image_path'] = StoredFile::put(
                $request->file('hero_image'),
                'tournaments',
                $tournament?->hero_image_path,
            );
        }

        if ($request->hasFile('prize_image')) {
            $payload['prize_image_path'] = StoredFile::put(
                $request->file('prize_image'),
                'tournaments',
                $tournament?->prize_image_path,
            );
        }

        return $payload;
    }

    /** @param array<string, mixed> $data */
    private function resolvedPublishedAt(array $data): ?CarbonImmutable
    {
        return match ($data['posting']) {
            // Draf belum punya tanggal tayang, dan menuliskannya sekarang
            // berarti tanggal itu ikut terbit begitu statusnya diubah nanti.
            'draft' => null,
            'schedule' => CarbonImmutable::parse($data['published_at']),
            default => CarbonImmutable::now(),
        };
    }

    /**
     * Menulis ulang ofisial, jadwal, dan tautan dokumen.
     *
     * DIHAPUS lalu ditulis ulang, bukan dicocokkan satu per satu. Keduanya
     * kelompok berulang yang bisa ditambah, dihapus, DAN diurutkan ulang
     * (`596:11361`) — mencocokkan baris lama dengan yang baru menuntut id di
     * formulir, dan id itu tidak berarti apa-apa bagi orang yang menyeret baris.
     * Foto ofisial yang tidak diunggah ulang tetap dipertahankan lewat
     * `photo_path` yang dikirim balik formulir.
     */
    private function syncChildren(TournamentRequest $request, Tournament $tournament): void
    {
        $data = $request->validated();

        // Path foto lama dibaca dari DATABASE lewat id yang dikirim balik
        // formulir — bukan dari path yang dikirim klien. Lihat alasannya di
        // `TournamentRequest`.
        $existingPhotos = $tournament->officials()->pluck('photo_path', 'id');
        $keptPhotos = [];

        foreach (array_values((array) ($data['officials'] ?? [])) as $index => $official) {
            $keptPhotos[$index] = $request->hasFile("officials.{$index}.photo")
                ? StoredFile::put($request->file("officials.{$index}.photo"), 'tournaments')
                : ($existingPhotos[$official['id'] ?? null] ?? null);
        }

        // Foto yang tidak lagi dipakai baris mana pun dibuang — kalau tidak,
        // menghapus seorang ofisial meninggalkan berkasnya di disk selamanya.
        foreach (array_diff($existingPhotos->filter()->all(), array_filter($keptPhotos)) as $orphan) {
            StoredFile::forget($orphan);
        }

        $tournament->officials()->delete();

        foreach (array_values((array) ($data['officials'] ?? [])) as $index => $official) {
            $tournament->officials()->create([
                'name' => $official['name'],
                'role' => $official['role'],
                'country' => $official['country'],
                'photo_path' => $keptPhotos[$index] ?? null,
                'position' => $index + 1,
            ]);
        }

        $tournament->scheduleEntries()->delete();
        foreach (array_values((array) ($data['schedule'] ?? [])) as $index => $entry) {
            $tournament->scheduleEntries()->create([
                'held_on' => $entry['held_on'],
                'starts_at' => $entry['starts_at'],
                'activity' => $entry['activity'],
                'area' => $entry['area'] ?? null,
                'position' => $index + 1,
            ]);
        }

        $ids = array_values(array_filter((array) ($data['documents'] ?? [])));
        $tournament->documents()->sync(
            collect($ids)->mapWithKeys(fn ($id, $i) => [$id => ['position' => $i + 1]])->all(),
        );
    }

    /** @return array<string, mixed> */
    private function formOptions(): array
    {
        $options = config('dwf.tournaments');

        return [
            'options' => [
                'coverage' => $options['coverage'],
                'rulesFormats' => $options['rules_formats'],
                'attendance' => $options['attendance'],
                'participantTypes' => $options['participant_types'],
                'currencies' => $options['currencies'],
                'dwfIdRequirements' => $options['dwf_id_requirements'],
                'eligibility' => $options['eligibility'],
                'registrationMethods' => $options['registration_methods'],
                'maxDocuments' => $options['max_documents'],
            ],

            // Hanya dokumen yang BENAR-BENAR tayang yang bisa ditautkan.
            // Menautkan draf berarti halaman turnamen memuat tautan ke berkas
            // yang belum boleh dilihat siapa pun.
            'documentOptions' => Document::query()->live()->orderBy('title')->get(['id', 'title', 'category'])
                ->map(fn (Document $d) => [
                    'value' => $d->id,
                    'label' => $d->title,
                    'category' => $d->category,
                ])->all(),
        ];
    }
}
