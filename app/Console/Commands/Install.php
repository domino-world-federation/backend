<?php

namespace App\Console\Commands;

use App\Models\PageMeta;
use App\Models\User;
use App\Support\Access;
use Database\Seeders\AccessSeeder;
use Illuminate\Console\Command;

/**
 * Menyiapkan database yang KOSONG untuk dipakai sungguhan.
 *
 * Ada karena `db:seed` tidak bisa dipakai untuk itu: ia membuat akun admin,
 * benar — tapi sekaligus menanam berita contoh, turnamen contoh, dokumen,
 * pesan kontak, dan aturan daftar IP. Di produksi itu bukan awal yang bersih
 * melainkan pekerjaan menghapus, dan menghapusnya satu per satu lewat layar
 * adalah cara paling mudah keliru untuk memulai.
 *
 * Yang dikerjakan perintah ini cuma tiga, dan ketiganya WAJIB ada sebelum satu
 * orang pun bisa masuk:
 *
 *   1. Peran dan izin (`AccessSeeder`).
 *   2. Akun super admin pertama, dari `.env`.
 *   3. Baris SEO bawaan (`*`) — cadangan untuk setiap halaman yang belum
 *      punya barisnya sendiri. Layar SEO menolak menghapusnya justru karena
 *      seluruh situs bergantung padanya, jadi ia harus ada sejak awal.
 *
 * Aman dijalankan berulang. Ia TIDAK menimpa sandi admin yang sudah ada —
 * menjalankan ulang perintah pemasangan tidak boleh diam-diam mengembalikan
 * sandi ke nilai `.env` yang mungkin sudah usang.
 */
class Install extends Command
{
    protected $signature = 'dwf:install';

    protected $description = 'Menyiapkan database kosong: peran, izin, akun super admin pertama';

    public function handle(): int
    {
        $email = config('dwf.admin.email');
        $password = config('dwf.admin.password');

        if (blank($email) || blank($password)) {
            $this->error('DWF_ADMIN_EMAIL dan DWF_ADMIN_PASSWORD wajib diisi di .env.');

            return self::FAILURE;
        }

        $this->components->task('Peran dan izin', function () {
            $this->callSilent('db:seed', ['--class' => AccessSeeder::class, '--force' => true]);

            return true;
        });

        $existing = User::query()->where('email', $email)->first();

        $this->components->task(
            $existing ? "Akun {$email} sudah ada — sandinya TIDAK diubah" : "Akun super admin {$email}",
            function () use ($existing, $email, $password) {
                $user = $existing ?? User::query()->create([
                    'name' => config('dwf.admin.name') ?: 'DWF Admin',
                    'email' => $email,
                    'password' => $password,
                ]);

                // Perannya disetel ulang walau akunnya sudah ada: super admin
                // yang kehilangan perannya punya sesi tanpa satu pun izin, dan
                // tidak ada layar yang bisa mengembalikannya.
                $user->syncRoles([Access::SUPER_ADMIN]);

                return true;
            },
        );

        $this->components->task('Baris SEO bawaan', function () {
            PageMeta::query()->firstOrCreate(
                ['route' => PageMeta::DEFAULT_ROUTE],
                [
                    'label' => 'Site default',
                    'title' => 'Domino World Federation',
                    'position' => 0,
                ],
            );

            return true;
        });

        $this->newLine();
        $this->components->info('Selesai. Yang perlu diisi lewat backoffice sebelum situs publik hidup:');
        $this->components->bulletList([
            'Contact & Social — alamat, surel, tautan sosial (dibaca kaki halaman)',
            'Legal Pages — Privacy Policy, Terms, Cookie Policy',
            'Home Page — naskah hero dan ajakan penutup',
            'SEO & Social — judul dan gambar bagikan tiap halaman',
        ]);
        $this->newLine();
        $this->components->warn('JANGAN jalankan `db:seed` di sini — isinya data contoh.');

        return self::SUCCESS;
    }
}
