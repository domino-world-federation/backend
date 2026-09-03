<?php

namespace App\Providers;

use App\Support\Access;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        /*
         * `super-admin` melewati seluruh pemeriksaan izin.
         *
         * Sengaja lewat `Gate::before`, bukan dengan memberinya seluruh izin
         * di database: dengan cara ini modul baru langsung terjangkau tanpa
         * ada yang harus ingat menjalankan seeder lagi. Kalau daftar izinnya
         * yang disinkronkan, satu deploy yang lupa menjalankan seeder berarti
         * super admin kehilangan akses ke modul terbarunya.
         *
         * `null` (bukan `false`) saat bukan super admin: itu yang membuat Gate
         * melanjutkan ke pemeriksaan izin biasa alih-alih menolak di sini.
         */
        Gate::before(fn ($user) => $user->hasRole(Access::SUPER_ADMIN) ? true : null);

        /*
         * Boleh mengunggah gambar ke dalam editor kalau ia boleh menulis apa
         * pun di suatu tempat.
         *
         * Bukan `can:news.update`: editor yang sama dipakai FAQ dan Legal
         * Pages, jadi izin satu modul akan mengunci dua modul lain. Bukan pula
         * sekadar `auth`: peran `viewer` bisa masuk dan hanya boleh membaca —
         * memberinya titik unggah berarti memberi seluruh orang yang punya akun
         * sebuah tempat menaruh berkas di server.
         */
        Gate::define('upload-editor-image', function ($user): bool {
            foreach (Access::permissions() as $permission) {
                if (! str_ends_with($permission, '.create') && ! str_ends_with($permission, '.update')) {
                    continue;
                }

                if ($user->can($permission)) {
                    return true;
                }
            }

            return false;
        });
    }
}
