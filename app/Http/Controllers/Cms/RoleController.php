<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Support\Access;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class RoleController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = [
            'q' => $request->string('q')->toString(),
            'type' => $request->string('type')->toString(),
            'scope' => $request->string('scope')->toString(),
        ];

        return Inertia::render('Roles/Index', [
            'roles' => Role::query()
                ->withCount(['permissions', 'users'])
                ->with('editor:id,name')
                ->when($filters['q'] !== '', fn ($q) => $q->where('name', 'ilike', "%{$filters['q']}%"))
                ->when($filters['type'] !== '', fn ($q) => $q->where('type', $filters['type']))
                ->when($filters['scope'] !== '', fn ($q) => $q->where('scope', $filters['scope']))
                ->orderBy('name')
                ->get()
                ->map(fn (Role $r) => [
                    'id' => $r->id,
                    'name' => $r->name,
                    'type' => $r->type ?? 'custom',
                    'scope' => $r->scope ?? 'global',
                    'summary' => $r->summary,
                    'permissions' => $r->permissions_count,
                    'users' => $r->users_count,
                    'updatedBy' => $r->editor?->name,
                    'updatedAt' => $r->updated_at?->toIso8601String(),

                    // Peran SYSTEM tidak bisa dihapus — "System roles can be
                    // inspected but not deleted" (`528:9744`). Ia lebih luas
                    // dari `isSuperAdmin`: keempat peran bawaan lahir dari
                    // `Access::roles()` dan dibangun ulang tiap `AccessSeeder`,
                    // jadi menghapusnya hanya menunda kembalinya.
                    'isSystem' => ($r->type ?? 'custom') === 'system',
                    'isSuperAdmin' => $r->name === Access::SUPER_ADMIN,
                ])
                ->all(),
            'filters' => $filters,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Roles/Form', ['role' => null, 'matrix' => $this->matrix()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $role = Role::create([
            'name' => $data['name'],
            'guard_name' => 'web',
            // Peran yang lahir dari layar ini selalu `custom`. `system` hanya
            // diberikan `AccessSeeder`, karena itulah artinya: berasal dari
            // `App\Support\Access`, bukan dari seseorang yang mengetik nama.
            'type' => 'custom',
            'scope' => $data['scope'],
            'summary' => $data['summary'] ?? null,
            'updated_by_id' => $request->user()->id,
        ]);
        $role->syncPermissions($data['permissions']);

        return to_route('roles.index')->with('success', __('backoffice.roles.saved'));
    }

    public function edit(Role $role): Response
    {
        return Inertia::render('Roles/Form', [
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'permissions' => $role->permissions->pluck('name')->all(),
                'scope' => $role->scope ?? 'global',
                'summary' => $role->summary,
                'type' => $role->type ?? 'custom',
                'isSystem' => ($role->type ?? 'custom') === 'system',
                'isSuperAdmin' => $role->name === Access::SUPER_ADMIN,
            ],
            'matrix' => $this->matrix(),
        ]);
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        // `super-admin` melewati seluruh pemeriksaan lewat `Gate::before`, jadi
        // baris izinnya tidak pernah dibaca. Membiarkannya disunting berarti
        // menampilkan layar yang tidak mengubah apa pun — bentuk kebohongan
        // yang paling membingungkan.
        $this->refuseSuperAdmin($role);

        $data = $this->validated($request, $role);

        $role->update([
            'name' => $data['name'],
            'scope' => $data['scope'],
            'summary' => $data['summary'] ?? null,
            'updated_by_id' => $request->user()->id,
        ]);
        $role->syncPermissions($data['permissions']);

        return to_route('roles.index')->with('success', __('backoffice.roles.updated'));
    }

    public function destroy(Role $role): RedirectResponse
    {
        $this->refuseSuperAdmin($role);
        $this->refuseSystemRole($role);

        if ($role->users()->exists()) {
            throw ValidationException::withMessages([
                'role' => __('backoffice.roles.in_use', ['count' => $role->users()->count()]),
            ]);
        }

        $role->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return back()->with('success', __('backoffice.roles.deleted'));
    }

    /**
     * "System roles can be inspected but not deleted" (`528:9744`).
     *
     * Terpisah dari `refuseSuperAdmin` karena alasannya berbeda: super admin
     * terkunci karena izinnya tidak pernah dibaca, sedangkan peran sistem
     * terkunci karena `AccessSeeder` akan membangunnya kembali — menghapusnya
     * bukan menghapus, melainkan menunda.
     */
    private function refuseSystemRole(Role $role): void
    {
        if (($role->type ?? 'custom') === 'system') {
            throw ValidationException::withMessages([
                'role' => __('backoffice.roles.system_locked'),
            ]);
        }
    }

    private function refuseSuperAdmin(Role $role): void
    {
        if ($role->name === Access::SUPER_ADMIN) {
            throw ValidationException::withMessages([
                'role' => __('backoffice.roles.super_admin_locked'),
            ]);
        }
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Role $role = null): array
    {
        return $request->validate([
            'name' => [
                'required', 'string', 'max:60', 'regex:/^[a-z0-9-]+$/',
                Rule::unique('roles', 'name')->ignore($role?->id),
                Rule::notIn([Access::SUPER_ADMIN]),
            ],
            'permissions' => ['array'],
            'permissions.*' => [Rule::in(Permission::query()->pluck('name')->all())],
            'scope' => ['required', Rule::in(['global', 'federation'])],
            'summary' => ['nullable', 'string', 'max:160'],
        ], [
            'name.regex' => __('backoffice.roles.name_format'),
            'name.not_in' => __('backoffice.roles.name_reserved'),
        ], [
            'name' => __('backoffice.roles.name'),
            'permissions' => __('backoffice.roles.permissions'),
            'scope' => __('backoffice.roles.scope'),
            'summary' => __('backoffice.roles.summary'),
        ]);
    }

    /**
     * Matriks modul × aksi untuk kotak centangnya.
     *
     * Dibangun dari `Access`, bukan dari tabel `permissions`: yang pertama
     * tahu modul mana punya aksi mana, sementara tabelnya hanya daftar datar
     * yang tidak bisa dikelompokkan tanpa menebak.
     *
     * @return array<int, array{key: string, label: string, actions: array<int, array{value: string, label: string}>}>
     */
    private function matrix(): array
    {
        return collect(Access::MODULES)
            ->map(fn (string $label, string $key) => [
                'key' => $key,
                'label' => $label,
                'actions' => collect(Access::actionsFor($key))
                    ->map(fn (string $action) => [
                        'value' => "{$key}.{$action}",
                        'label' => $action,
                    ])
                    ->all(),
            ])
            ->values()
            ->all();
    }
}
