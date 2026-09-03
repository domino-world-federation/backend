<?php

namespace Database\Seeders;

use App\Support\Access;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Menyinkronkan peran dan izin dengan `App\Support\Access`.
 *
 * Aman dijalankan berulang: izin yang sudah ada tidak diduplikasi, dan peran
 * yang sudah ada disesuaikan izinnya. Jalankan lagi setiap kali ada modul baru.
 */
class AccessSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (Access::permissions() as $name) {
            Permission::findOrCreate($name, 'web');
        }

        // WAJIB di antara keduanya. Izin yang baru saja dibuat belum ada di
        // cache yang dibaca `syncPermissions()`, jadi pada database kosong
        // pemberian izin ke peran gagal — dan hanya berhasil kalau seeder-nya
        // dijalankan untuk KEDUA kalinya. Persis jenis kesalahan yang lolos
        // dari pengujian di mesin yang datanya sudah ada.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (Access::roles() as $name => $permissions) {
            $role = Role::findOrCreate($name, 'web');

            /*
             * Peran yang lahir dari sini SELALU `system` — itulah artinya:
             * berasal dari `App\Support\Access`, bukan dari seseorang yang
             * mengetik nama di layar Roles. Konsekuensinya ia tidak bisa
             * dihapus lewat UI (`528:9744`), dan itu memang jujur: seeder ini
             * akan membangunnya kembali pada eksekusi berikutnya.
             *
             * `summary` dan `scope` ikut ditulis ulang tiap kali, sedangkan
             * `updated_by_id` TIDAK disentuh — seeder bukan orang, dan menandai
             * baris ini atas nama siapa pun akan membuat kolom "Last Updated"
             * berbohong.
             */
            $meta = Access::ROLE_META[$name] ?? ['scope' => 'global', 'summary' => null];

            $role->forceFill([
                'type' => 'system',
                'scope' => $meta['scope'],
                'summary' => $meta['summary'],
            ])->save();

            // `null` = super-admin, yang izinnya datang dari `Gate::before`.
            // Memberinya baris izin juga hanya menambah data yang tidak pernah
            // dibaca — dan yang akan basi tiap kali ada modul baru.
            if ($permissions !== null) {
                $role->syncPermissions($permissions);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
