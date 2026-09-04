<?php

namespace Tests\Feature;

use App\Providers\AppServiceProvider;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * URL yang dibangun aplikasi memakai `https` kalau `APP_URL` https.
 *
 * Ada karena kegagalannya tidak kelihatan sampai ada REDIRECT. Halaman biasa
 * memakai path relatif dan tetap benar; yang patah `redirect()->route(...)`,
 * yang mengirim `Location: http://…` — dan browser memblokirnya sebagai mixed
 * content. Yang terlihat pemakainya cuma "Network error" saat menekan Login,
 * tanpa satu kata pun tentang skema.
 *
 * Diuji dengan memanggil methodnya langsung, BUKAN lewat `APP_URL`: Laravel
 * memuat `.env` dengan penulis imutabel, jadi nilai pertama yang masuk proses
 * mengunci sisanya — tes yang menyetel env akan lolos sendirian dan gagal di
 * suite penuh, tergantung urutan.
 */
class HttpsUrlTest extends TestCase
{
    private function boot(string $appUrl): void
    {
        config()->set('app.url', $appUrl);

        (new AppServiceProvider($this->app))->forceHttpsWhenSiteIsHttps();
    }

    public function test_named_routes_are_built_over_https(): void
    {
        $this->boot('https://fed-bo.example');

        $this->assertStringStartsWith('https://', route('login'));
    }

    /**
     * Ini yang benar-benar patah di produksi: redirect sesudah login ke layar
     * 2FA, diikuti XHR Inertia dari halaman https.
     */
    public function test_the_redirect_after_login_points_at_https(): void
    {
        $this->boot('https://fed-bo.example');

        $this->assertStringStartsWith('https://', route('two-factor.setup'));
        $this->assertStringStartsWith('https://', route('two-factor.challenge'));
    }

    /**
     * Dan TIDAK memaksa saat situsnya memang http.
     *
     * Tanpa pasangan ini, "perbaikan" yang memaksa https di mana-mana akan
     * lolos tes di atas dengan sempurna — dan mematikan pengembangan lokal.
     */
    public function test_it_leaves_an_http_site_alone(): void
    {
        URL::forceScheme(null);
        $this->boot('http://127.0.0.1:8000');

        $this->assertStringStartsWith('http://', route('login'));
    }
}
