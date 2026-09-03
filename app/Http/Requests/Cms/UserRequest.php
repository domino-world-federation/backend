<?php

namespace App\Http\Requests\Cms;

use App\Models\Role;
use App\Support\Access;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $user = $this->route('user');
        $isCreate = $user === null;

        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:160', Rule::unique('users', 'email')->ignore($user?->id)],

            /*
             * TIDAK ADA sandi saat membuat — "No password is created in this
             * form" (`529:9716`). Akunnya lahir tanpa sandi dan menerima tautan
             * undangan; sandinya dipilih orangnya sendiri di
             * `InvitationController`.
             *
             * Saat MENYUNTING ia tetap ada dan tetap opsional. Layar itu tidak
             * digambar desain, dan menghapusnya berarti akun yang undangannya
             * hangus tidak punya jalan pemulihan selain menerbitkan undangan
             * baru — yang memang ada, tapi memaksanya sebagai satu-satunya
             * jalan bukan keputusan yang dibuat desain.
             */
            'password' => $isCreate
                ? ['prohibited']
                : ['nullable', Password::defaults()],

            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => [Rule::exists('roles', 'name')],

            // Wajib hanya kalau salah satu peran berlingkup federasi
            // (`529:9696`); diperiksa di `withValidator` karena syaratnya
            // bergantung pada isi `roles`.
            'member_federation_id' => ['nullable', Rule::exists('member_federations', 'id')],

            'two_factor_enabled' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * Tiga penjagaan. Dua yang pertama mencegah backoffice mengunci dirinya
     * sendiri; yang ketiga menegakkan lingkup federasi.
     *
     * Semuanya di sini, bukan di controller, supaya pesannya mendarat di field
     * yang bersangkutan dan bukan sebagai galat 500 yang misterius.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $this->requireFederationForScopedRoles($validator);

            $user = $this->route('user');
            $roles = (array) $this->input('roles', []);

            if ($user === null) {
                return;
            }

            // 1. Jangan mencabut peran super admin dari diri sendiri.
            if ($user->is($this->user())
                && $user->isSuperAdmin()
                && ! in_array(Access::SUPER_ADMIN, $roles, true)) {
                $validator->errors()->add('roles', __('backoffice.users.cannot_demote_self'));
            }

            // 2. Jangan menurunkan super admin TERAKHIR.
            if ($user->isSuperAdmin() && ! in_array(Access::SUPER_ADMIN, $roles, true)) {
                $remaining = Role::findByName(Access::SUPER_ADMIN, 'web')
                    ->users()
                    ->where('users.id', '!=', $user->id)
                    ->count();

                if ($remaining === 0) {
                    $validator->errors()->add('roles', __('backoffice.users.last_super_admin'));
                }
            }

            // 3. Jangan mematikan akun sendiri — sama seperti tidak bisa
            //    menghapusnya. Yang hilang identik: akses, seketika.
            if ($user->is($this->user()) && ! $this->boolean('is_active')) {
                $validator->errors()->add('is_active', __('backoffice.users.cannot_deactivate_self'));
            }
        });
    }

    /**
     * "Required for federation-scoped roles such as Admin PB" (`529:9696`).
     *
     * Diperiksa terhadap peran yang DIPILIH, bukan terhadap satu nama peran
     * yang di-hardcode: lingkup itu kolom di tabel `roles` dan bisa berubah
     * lewat layar Roles, jadi daftar nama di sini akan basi tanpa ada yang tahu.
     */
    private function requireFederationForScopedRoles(Validator $validator): void
    {
        $roles = (array) $this->input('roles', []);

        if ($roles === []) {
            return;
        }

        $needsFederation = Role::query()
            ->whereIn('name', $roles)
            ->where('scope', 'federation')
            ->exists();

        if ($needsFederation && blank($this->input('member_federation_id'))) {
            $validator->errors()->add(
                'member_federation_id',
                __('backoffice.users.federation_required'),
            );
        }
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'name' => __('backoffice.users.name'),
            'email' => __('backoffice.users.email'),
            'password' => __('backoffice.users.password'),
            'roles' => __('backoffice.users.roles'),
            'member_federation_id' => __('backoffice.users.federation'),
            'is_active' => __('backoffice.common.status'),
        ];
    }
}
