<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Models\MemberFederation;
use App\Support\Csv;
use App\Support\Media\StoredFile;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Federasi anggota — direktori yang mengisi `/federation-members` di situs
 * publik (`getMemberFederations`).
 *
 * Tidak ada layar desainnya: kanvas Backoffice tidak menggambar modul ini sama
 * sekali. Bentuknya karena itu mengikuti pola yang sudah dipakai Documents dan
 * Gallery — identitas, sakelar status, lalu "siapa + kapan". Penyimpangannya
 * dicatat di docs/PROGRESS.md.
 */
class MemberFederationController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $this->filters($request);

        return Inertia::render('Federations/Index', [
            'federations' => $this->filtered($filters)
                ->paginate(config('dwf.per_page'))
                ->withQueryString()
                ->through(fn (MemberFederation $f) => [
                    'id' => $f->id,
                    'name' => $f->name,
                    'country' => $f->country,
                    'flagUrl' => StoredFile::url($f->flag_path),
                    'tier' => $f->tier,
                    'tierLabel' => $f->tier_label,
                    'joinedYear' => $f->joined_year,
                    'president' => $f->president,
                    'isActive' => $f->is_active,
                    'adminCount' => $f->admins_count,
                    'updatedAt' => $f->updated_at?->toIso8601String(),
                    'updatedBy' => $f->editor?->name,
                ]),
            'tiers' => $this->tierOptions(),
            'filters' => $filters,
        ]);
    }

    /** @return array{q: string, status: string, tier: string} */
    private function filters(Request $request): array
    {
        return [
            'q' => $request->string('q')->toString(),
            'status' => $request->string('status')->toString(),
            'tier' => $request->string('tier')->toString(),
        ];
    }

    /**
     * Query daftar — dipakai layar daftar DAN ekspor, supaya keduanya sepakat.
     *
     * @param  array{q: string, status: string, tier: string}  $filters
     */
    private function filtered(array $filters): Builder
    {
        return MemberFederation::query()
            ->with('editor:id,name')
            // Dihitung, bukan dimuat: daftar ini cuma mencetak angkanya, dan
            // memuat seluruh admin tiap baris untuk menghitungnya adalah cara
            // termahal mendapatkan satu bilangan.
            ->withCount('admins')
            ->when($filters['q'] !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'ilike', "%{$filters['q']}%")
                ->orWhere('country', 'ilike', "%{$filters['q']}%")
                ->orWhere('president', 'ilike', "%{$filters['q']}%")))
            ->when($filters['status'] === 'active', fn ($q) => $q->where('is_active', true))
            ->when($filters['status'] === 'inactive', fn ($q) => $q->where('is_active', false))
            ->when($filters['tier'] !== '', fn ($q) => $q->where('tier', $filters['tier']))
            ->ordered();
    }

    /**
     * Ekspor direktori, mengikuti filter yang sedang aktif.
     *
     * Email dan telepon presiden federasi IKUT — berbeda dari ekspor pengguna,
     * yang sengaja tidak membawa apa pun. Bedanya: ini kontak resmi sebuah
     * badan yang memang tayang di situs publik, bukan kredensial seseorang.
     */
    public function export(Request $request): StreamedResponse
    {
        $rows = $this->filtered($this->filters($request))->lazy();

        return Csv::stream('member-federations', [
            'ID', 'Name', 'Country', 'Tier', 'Joined Year', 'President',
            'Headquarters', 'Email', 'Phone', 'Website', 'Status',
            'Admins', 'Last Modified At', 'Last Modified By',
        ], $rows->map(fn (MemberFederation $f) => [
            $f->id,
            $f->name,
            $f->country,
            $f->tier_label,
            $f->joined_year,
            $f->president,
            $f->headquarters,
            $f->email,
            $f->phone,
            $f->website_url,
            $f->is_active ? 'active' : 'inactive',
            $f->admins_count,
            $f->updated_at?->toDateTimeString(),
            $f->editor?->name,
        ]));
    }

    /** Sakelar status dari barisnya — janji ketiga `StandardListTest`. */
    public function status(Request $request, MemberFederation $federation): RedirectResponse
    {
        $active = $request->boolean('is_active');

        // Federasi yang masih jadi lingkup akun admin tidak boleh dimatikan:
        // akunnya akan menunjuk badan yang sudah tidak diakui, dan itu tidak
        // terlihat dari layar mana pun.
        if (! $active && $federation->admins()->exists()) {
            throw ValidationException::withMessages([
                'is_active' => __('backoffice.federations.in_use', [
                    'count' => $federation->admins()->count(),
                ]),
            ]);
        }

        $federation->update(['is_active' => $active]);

        return back()->with('success', __('backoffice.federations.updated'));
    }

    public function create(): Response
    {
        return Inertia::render('Federations/Form', [
            'federation' => null,
            'tiers' => $this->tierOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        MemberFederation::create($this->payload($request, $data));

        return to_route('federations.index')->with('success', __('backoffice.federations.saved'));
    }

    public function edit(MemberFederation $federation): Response
    {
        return Inertia::render('Federations/Form', [
            'federation' => [
                'id' => $federation->id,
                'name' => $federation->name,
                'country' => $federation->country,
                'flagUrl' => StoredFile::url($federation->flag_path),
                'tier' => $federation->tier,
                'joinedYear' => $federation->joined_year,
                'president' => $federation->president,
                'headquarters' => $federation->headquarters,
                'email' => $federation->email,
                'phone' => $federation->phone,
                'websiteUrl' => $federation->website_url,
                'isActive' => $federation->is_active,
            ],
            'tiers' => $this->tierOptions(),
        ]);
    }

    public function update(Request $request, MemberFederation $federation): RedirectResponse
    {
        $data = $this->validated($request, $federation);

        $federation->update($this->payload($request, $data, $federation));

        return to_route('federations.index')->with('success', __('backoffice.federations.updated'));
    }

    public function destroy(MemberFederation $federation): RedirectResponse
    {
        // `member_federation_id` di `users` memakai `nullOnDelete`, jadi
        // menghapus badan ini akan diam-diam mencabut lingkup akun adminnya.
        // Ditolak di sini supaya yang terjadi bukan kejutan.
        if ($federation->admins()->exists()) {
            throw ValidationException::withMessages([
                'federation' => __('backoffice.federations.in_use', [
                    'count' => $federation->admins()->count(),
                ]),
            ]);
        }

        StoredFile::forget($federation->flag_path);
        $federation->delete();

        return back()->with('success', __('backoffice.federations.deleted'));
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?MemberFederation $federation = null): array
    {
        $uploads = config('dwf.uploads');

        return $request->validate([
            'name' => ['required', 'string', 'max:160', Rule::unique('member_federations', 'name')->ignore($federation?->id)],
            'country' => ['required', 'string', 'max:120'],
            'flag' => [
                'nullable', 'image',
                'mimes:'.implode(',', $uploads['image_mimes']),
                'max:'.$uploads['image_max_kb'],
            ],
            'tier' => ['nullable', Rule::in(array_keys(config('dwf.membership_tiers')))],

            // Batas atasnya tahun berjalan: federasi yang bergabung tahun depan
            // adalah salah ketik, bukan rencana.
            'joined_year' => ['nullable', 'integer', 'min:1900', 'max:'.now()->year],

            'president' => ['nullable', 'string', 'max:160'],
            'headquarters' => ['nullable', 'string', 'max:200'],
            'email' => ['nullable', 'email', 'max:160'],
            'phone' => ['nullable', 'string', 'max:40'],
            'website_url' => ['nullable', 'url', 'max:200'],
            'is_active' => ['required', 'boolean'],
        ], attributes: [
            'name' => __('backoffice.federations.name'),
            'country' => __('backoffice.federations.country'),
            'tier' => __('backoffice.federations.tier'),
            'joined_year' => __('backoffice.federations.joined_year'),
            'website_url' => __('backoffice.federations.website'),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function payload(Request $request, array $data, ?MemberFederation $federation = null): array
    {
        // Kunci nullable yang tidak dikirim TIDAK muncul di data tervalidasi —
        // `$data['email']` melempar "Undefined array key", bukan null. Semua
        // pembacaan opsional lewat `??` karena itu.
        $payload = [
            'name' => $data['name'],
            'country' => $data['country'],
            'tier' => $data['tier'] ?? null,
            'joined_year' => $data['joined_year'] ?? null,
            'president' => $data['president'] ?? null,
            'headquarters' => $data['headquarters'] ?? null,
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'website_url' => $data['website_url'] ?? null,
            'is_active' => $data['is_active'],
        ];

        if ($request->hasFile('flag')) {
            $payload['flag_path'] = StoredFile::put($request->file('flag'), 'federations', $federation?->flag_path);
        }

        if ($federation === null) {
            $payload['position'] = MemberFederation::nextPosition();
        }

        return $payload;
    }

    /** @return array<int, array{value: string, label: string}> */
    private function tierOptions(): array
    {
        return collect(config('dwf.membership_tiers'))
            ->map(fn (string $label, string $value) => ['value' => $value, 'label' => $label])
            ->values()
            ->all();
    }
}
