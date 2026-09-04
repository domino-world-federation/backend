<?php

namespace App\Providers;

use App\Listeners\RecordAuthActivity;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Foundation\DevCommands;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use RuntimeException;
use Spatie\Activitylog\Models\Activity;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->forceHttpsWhenSiteIsHttps();
        $this->configureDevCommand();
        $this->recordAuthActivity();
        $this->stampActivityWithOrigin();
        $this->refusePrivateFilesInsidePublicMedia();
    }

    /**
     * Membangun URL dengan `https` kalau situsnya memang `https`.
     *
     * Laravel menurunkan skema dari REQUEST, dan nginx bicara ke PHP-FPM lewat
     * soket biasa — jadi kalau `fastcgi_param HTTPS` tidak sampai, aplikasi
     * mengira setiap permintaan datang lewat http walau pengunjungnya
     * mengetik https.
     *
     * Akibatnya tidak kelihatan sampai ada REDIRECT. Halaman biasa memakai
     * path relatif dan tetap benar; yang patah adalah `redirect()->route(...)`,
     * yang mengirim `Location: http://…`. Browser memblokirnya sebagai mixed
     * content, dan yang terlihat pemakainya cuma "Network error" saat menekan
     * Login — bukan satu kata pun tentang skema.
     *
     * Dibaca dari `APP_URL`, bukan dari flag terpisah: satu nilai yang sudah
     * harus benar demi hal lain (URL gambar di API, tautan surel undangan),
     * jadi tidak ada keadaan baru yang bisa berbeda pendapat. Di lokal
     * `APP_URL` http, jadi ini tidak menyala.
     *
     * Ini JARING PENGAMAN, bukan pengganti config server yang benar. Yang
     * seharusnya memberi tahu Laravel adalah `fastcgi_param HTTPS on;` di blok
     * TLS nginx — lihat docs/PRODUCTION.md §9.
     *
     * `public` supaya bisa diuji langsung. Menguji lewat `APP_URL` tidak bisa
     * diandalkan: Laravel memuat `.env` dengan penulis IMUTABEL, jadi nilai
     * pertama yang masuk proses mengunci sisanya — tesnya lolos sendirian dan
     * gagal di suite penuh, tergantung tes mana yang menyalakan aplikasi lebih
     * dulu.
     */
    public function forceHttpsWhenSiteIsHttps(): void
    {
        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }
    }

    /**
     * Menolak boot kalau berkas privat berada DI DALAM folder media publik.
     *
     * Keduanya bisa dipindah lewat `.env` (`MEDIA_ROOT` dan
     * `MEDIA_PRIVATE_ROOT`), dan salah setel di sana bukan galat yang
     * kelihatan — ia menjadikan SETIAP dokumen bisa diunduh siapa pun lewat
     * symlink `public/storage`, tanpa satu pun pemeriksaan status tayang.
     * Aplikasinya tetap jalan, layarnya tetap normal, dan tidak ada yang tahu
     * sampai ada yang menemukan URL-nya.
     *
     * Karena itu ia dibuat berisik: lebih baik aplikasi menolak menyala
     * daripada menyala dengan seluruh dokumennya terbuka.
     */
    private function refusePrivateFilesInsidePublicMedia(): void
    {
        $public = realpath((string) config('filesystems.disks.public.root'));
        $private = realpath((string) config('filesystems.disks.local.root'));

        if ($public === false || $private === false) {
            return;
        }

        if ($private === $public || str_starts_with($private.DIRECTORY_SEPARATOR, $public.DIRECTORY_SEPARATOR)) {
            throw new RuntimeException(
                "Berkas privat ({$private}) berada di dalam media publik ({$public}). "
                .'Setiap dokumen akan bisa diunduh siapa pun lewat symlink public/storage. '
                .'Setel MEDIA_PRIVATE_ROOT ke folder DI LUAR MEDIA_ROOT.'
            );
        }
    }

    /**
     * Tiap entri log membawa IP dan user agent — bukan hanya entri otentikasi.
     *
     * Dipasang di event `creating` model `Activity`, bukan di tiap pemanggil:
     * catatannya datang dari trait `RecordsActivity` (11 model), dari listener
     * otentikasi, dan dari `ContactSettingController`. Menitipkannya ke tiap
     * pemanggil berarti suatu saat ada jalur yang lupa — dan yang hilang justru
     * baris yang paling ingin ditelusuri.
     *
     * Ditulis hanya kalau belum ada, supaya pemanggil yang sudah menyetelnya
     * sendiri tidak tertimpa.
     */
    private function stampActivityWithOrigin(): void
    {
        Activity::creating(function (Activity $activity) {
            $request = request();

            if (! $request instanceof Request) {
                return;
            }

            $properties = collect($activity->properties ?? []);

            $activity->properties = $properties
                ->put('ip', $properties->get('ip') ?? $request->ip())
                ->put('user_agent', $properties->get('user_agent') ?? $request->userAgent());
        });
    }

    /**
     * Login, logout, percobaan gagal, dan penguncian ikut masuk log aktivitas.
     *
     * Didaftarkan eksplisit. Method di `RecordAuthActivity` bernama `on…`
     * justru supaya penemuan otomatis Laravel TIDAK ikut mendaftarkannya —
     * kalau keduanya jalan, tiap login menghasilkan dua baris log identik.
     */
    private function recordAuthActivity(): void
    {
        Event::listen(Login::class, [RecordAuthActivity::class, 'onLogin']);
        Event::listen(Logout::class, [RecordAuthActivity::class, 'onLogout']);
        Event::listen(Failed::class, [RecordAuthActivity::class, 'onFailed']);
        Event::listen(Lockout::class, [RecordAuthActivity::class, 'onLockout']);
    }

    /**
     * Satu perintah untuk menyalakan seluruh lingkungan pengembangan:
     * `php artisan dev` (atau `composer dev`).
     *
     * Bawaannya menjalankan empat proses — server, Vite, listener antrean, dan
     * Pail. Antrean dibuang karena belum ada satu pun job yang di-dispatch:
     * listener yang tidak pernah kebagian kerja cuma menambah satu tab yang
     * harus diabaikan setiap hari. Kembalikan begitu ada job sungguhan.
     *
     * Vite dijalankan lewat Bun otomatis — `Illuminate\Support\NodePackageManager`
     * memilihnya karena ada `bun.lock` di akar project.
     */
    private function configureDevCommand(): void
    {
        DevCommands::except('queue');
    }
}
