<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cms\IpWhitelistRuleRequest;
use App\Models\IpWhitelistRule;
use App\Models\User;
use App\Support\Csv;
use App\Support\Security\IpWhitelist;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Daftar IP yang boleh membuka backoffice — Figma `527:7039` (daftar) dan
 * `527:7182` (formulir).
 */
class IpWhitelistRuleController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $this->filters($request);

        return Inertia::render('IpWhitelist/Index', [
            'rules' => $this->filtered($filters)
                ->paginate(config('dwf.per_page'))
                ->withQueryString()
                ->through(fn (IpWhitelistRule $rule) => [
                    'id' => $rule->id,
                    'name' => $rule->name,
                    'ipRange' => $rule->ip_range,
                    'scope' => $rule->scope,
                    'scopeLabel' => $rule->scope_label,
                    'validity' => $rule->validity,
                    'expiresAt' => $rule->expires_at?->toIso8601String(),
                    'isExpired' => $rule->is_expired,
                    'isActive' => $rule->is_active,
                    'updatedBy' => $rule->editor?->name,
                    'updatedAt' => $rule->updated_at?->toIso8601String(),
                ]),
            'roles' => $this->roleOptions(),
            'filters' => $filters,

            // Dicetak di bawah tabel supaya orang tahu alamat mana yang sedang
            // ia pakai SEBELUM menekan sakelar yang bisa mengunci dirinya.
            'currentIp' => $request->ip(),
        ]);
    }

    /** @return array{q: string, status: string, scope: string, validity: string} */
    private function filters(Request $request): array
    {
        return [
            'q' => $request->string('q')->toString(),
            'status' => $request->string('status')->toString(),
            'scope' => $request->string('scope')->toString(),
            'validity' => $request->string('validity')->toString(),
        ];
    }

    /**
     * Query daftar — dipakai layar daftar DAN ekspor, supaya keduanya sepakat.
     *
     * @param  array{q: string, status: string, scope: string, validity: string}  $filters
     */
    private function filtered(array $filters): Builder
    {
        return IpWhitelistRule::query()
            ->with(['role:id,name', 'user:id,name', 'editor:id,name'])
            ->when($filters['q'] !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'ilike', "%{$filters['q']}%")
                ->orWhere('ip_range', 'ilike', "%{$filters['q']}%")))
            ->when($filters['status'] === 'active', fn ($q) => $q->where('is_active', true))
            ->when($filters['status'] === 'inactive', fn ($q) => $q->where('is_active', false))
            ->when($filters['scope'] !== '', fn ($q) => $q->where('scope', $filters['scope']))
            ->when($filters['validity'] !== '', fn ($q) => $q->where('validity', $filters['validity']))
            ->orderByDesc('updated_at');
    }

    public function export(Request $request): StreamedResponse
    {
        $rows = $this->filtered($this->filters($request))->lazy();

        return Csv::stream('ip-whitelist', [
            'ID', 'Name', 'IP Address / CIDR', 'Access Scope', 'Scope Target',
            'Validity', 'Expires At', 'Status', 'Notes', 'Created By', 'Last Modified By', 'Last Modified At',
        ], $rows->map(fn (IpWhitelistRule $rule) => [
            $rule->id,
            $rule->name,
            $rule->ip_range,
            $rule->scope,
            $rule->scope_label,
            $rule->validity,
            $rule->expires_at?->toDateTimeString(),
            $rule->is_active ? 'active' : 'inactive',
            $rule->notes,
            $rule->creator?->name,
            $rule->editor?->name,
            $rule->updated_at?->toDateTimeString(),
        ]));
    }

    public function create(Request $request): Response
    {
        return Inertia::render('IpWhitelist/Form', [
            'rule' => null,
            'roles' => $this->roleOptions(),
            'admins' => $this->adminOptions(),
            'currentIp' => $request->ip(),
        ]);
    }

    public function store(IpWhitelistRuleRequest $request): RedirectResponse
    {
        $data = $this->prepared($request);

        $this->guardAgainstSelfLockout($request, null, new IpWhitelistRule($data));

        IpWhitelistRule::create($data + ['created_by_id' => $request->user()->id]);

        return to_route('ip-whitelist.index')->with('success', __('backoffice.ip_whitelist.saved'));
    }

    public function edit(Request $request, IpWhitelistRule $ipWhitelist): Response
    {
        return Inertia::render('IpWhitelist/Form', [
            'rule' => [
                'id' => $ipWhitelist->id,
                'name' => $ipWhitelist->name,
                'ipRange' => $ipWhitelist->ip_range,
                'scope' => $ipWhitelist->scope,
                'roleId' => $ipWhitelist->role_id,
                'userId' => $ipWhitelist->user_id,
                'validity' => $ipWhitelist->validity,

                // `datetime-local` menolak sufiks zona dan offset. Dikirim
                // sebagai waktu lokal aplikasi supaya kotaknya terisi; tanpa
                // ini field-nya kosong saat menyunting dan orang mengira
                // tanggalnya hilang.
                'expiresAt' => $ipWhitelist->expires_at?->format('Y-m-d\TH:i'),
                'notes' => $ipWhitelist->notes,
                'isActive' => $ipWhitelist->is_active,
                'createdBy' => $ipWhitelist->creator?->name,
                'createdAt' => $ipWhitelist->created_at?->toIso8601String(),
                'updatedBy' => $ipWhitelist->editor?->name,
                'updatedAt' => $ipWhitelist->updated_at?->toIso8601String(),
            ],
            'roles' => $this->roleOptions(),
            'admins' => $this->adminOptions(),
            'currentIp' => $request->ip(),
        ]);
    }

    public function update(IpWhitelistRuleRequest $request, IpWhitelistRule $ipWhitelist): RedirectResponse
    {
        $data = $this->prepared($request);

        $replacement = (clone $ipWhitelist)->forceFill($data);
        $this->guardAgainstSelfLockout($request, $ipWhitelist->id, $replacement);

        $ipWhitelist->update($data);

        return to_route('ip-whitelist.index')->with('success', __('backoffice.ip_whitelist.updated'));
    }

    /**
     * Sakelar status di daftar — tidak menuntut formulir yang lengkap.
     *
     * Janji ketiga `StandardListTest`. Penjaga kunci-diri-sendiri tetap jalan
     * di sini: mematikan aturan justru cara paling cepat mengusir diri sendiri,
     * dan di layar daftar tidak ada satu pun field yang mengingatkannya.
     */
    public function status(Request $request, IpWhitelistRule $ipWhitelist): RedirectResponse
    {
        $active = $request->boolean('is_active');

        $replacement = (clone $ipWhitelist)->forceFill(['is_active' => $active]);
        $this->guardAgainstSelfLockout($request, $ipWhitelist->id, $replacement, 'is_active');

        $ipWhitelist->update(['is_active' => $active]);

        return back()->with('success', __('backoffice.ip_whitelist.updated'));
    }

    public function destroy(Request $request, IpWhitelistRule $ipWhitelist): RedirectResponse
    {
        $this->guardAgainstSelfLockout($request, $ipWhitelist->id, null, 'rule');

        $ipWhitelist->delete();

        return back()->with('success', __('backoffice.ip_whitelist.deleted'));
    }

    /**
     * Menolak perubahan yang akan mengusir pemakainya sendiri.
     *
     * Desain menuliskannya sebagai peringatan — "Deactivating or deleting a
     * rule that matches your current session may block your own access"
     * (`527:8163`). Peringatan tidak menahan apa pun, dan yang hilang kalau
     * seseorang tetap menekannya adalah akses ke satu-satunya layar yang bisa
     * membatalkannya. Pemulihannya lewat `php artisan tinker` di server.
     *
     * Dilewati di `local` dengan alasan yang sama seperti middleware-nya: di
     * sana daftar ini memang tidak ditegakkan, jadi menahan perubahan atas nama
     * penguncian yang tidak akan terjadi hanya menghalangi orang menyiapkan
     * data.
     */
    private function guardAgainstSelfLockout(
        Request $request,
        ?int $changingId,
        ?IpWhitelistRule $replacement,
        string $field = 'ip_range',
    ): void {
        if (app()->environment('local')) {
            return;
        }

        $ip = $request->ip();

        if ($ip === null) {
            return;
        }

        if (IpWhitelist::wouldLockOut($request->user(), $ip, $changingId, $replacement)) {
            throw ValidationException::withMessages([
                $field => __('backoffice.ip_whitelist.would_lock_out', ['ip' => $ip]),
            ]);
        }
    }

    /**
     * Data yang siap disimpan.
     *
     * Kolom sasaran yang tidak dipakai lingkupnya DIKOSONGKAN, bukan dibiarkan.
     * Aturan yang pernah dibuat untuk sebuah peran lalu diubah jadi "All
     * Admins" akan menyimpan `role_id` lamanya, dan ia tidak terlihat di layar
     * mana pun — sampai seseorang mengubah lingkupnya kembali dan mendapati
     * peran yang bukan pilihannya.
     *
     * @return array<string, mixed>
     */
    private function prepared(IpWhitelistRuleRequest $request): array
    {
        $data = $request->validated();
        $scope = $data['scope'];

        return [
            'name' => $data['name'],
            'ip_range' => trim($data['ip_range']),
            'scope' => $scope,
            'role_id' => $scope === IpWhitelistRule::SCOPE_ROLE ? $data['role_id'] : null,
            'user_id' => $scope === IpWhitelistRule::SCOPE_USER ? $data['user_id'] : null,
            'validity' => $data['validity'],
            'expires_at' => $data['validity'] === IpWhitelistRule::VALIDITY_TEMPORARY
                ? ($data['expires_at'] ?? null)
                : null,
            'notes' => $data['notes'] ?? null,
            'is_active' => $data['is_active'],
        ];
    }

    /** @return array<int, array{value: int, label: string}> */
    private function roleOptions(): array
    {
        return Role::query()->orderBy('name')->get(['id', 'name'])
            ->map(fn (Role $role) => ['value' => $role->id, 'label' => $role->name])
            ->all();
    }

    /** @return array<int, array{value: int, label: string}> */
    private function adminOptions(): array
    {
        return User::query()->orderBy('name')->get(['id', 'name', 'email'])
            ->map(fn (User $user) => ['value' => $user->id, 'label' => "{$user->name} — {$user->email}"])
            ->all();
    }
}
