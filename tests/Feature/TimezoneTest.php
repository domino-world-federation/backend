<?php

namespace Tests\Feature;

use Carbon\CarbonImmutable;
use Tests\TestCase;

/**
 * Zona waktu aplikasi datang dari `.env`, bukan dari nilai yang dipatok.
 *
 * Sampai 2026-09-06 `config/app.php` menulis `'UTC'` apa adanya sementara `.env`
 * dan `.env.example` sama-sama menyetel `APP_TIMEZONE=Asia/Jakarta`. Setelan itu
 * tidak pernah terbaca, dan kegagalannya diam: tidak ada galat, hanya jam yang
 * salah tujuh jam di setiap layar — dan penjadwalan yang tayang tujuh jam
 * terlambat, yang dari layar terbaca sebagai "jadwalnya tidak jalan".
 *
 * Yang diuji di sini BUKAN nilainya (`Asia/Jakarta` adalah keputusan deploy,
 * dan `phpunit.xml` menyetelnya sendiri), melainkan bahwa config-nya membaca
 * environment sama sekali. Mematoknya lagi akan lolos tanpa tes ini.
 */
class TimezoneTest extends TestCase
{
    public function test_the_timezone_is_read_from_the_environment(): void
    {
        $config = file_get_contents(config_path('app.php'));

        $this->assertStringContainsString(
            "'timezone' => env('APP_TIMEZONE'",
            $config,
            'config/app.php harus membaca APP_TIMEZONE, bukan mematok zona waktunya.',
        );
    }

    /** Nilai yang dipakai proses ini benar-benar dipasang ke PHP, bukan cuma tersimpan di config. */
    public function test_php_itself_runs_on_the_configured_timezone(): void
    {
        $this->assertSame(config('app.timezone'), date_default_timezone_get());
    }

    /**
     * Sebuah jadwal yang dipilih editor menjadi INSTAN yang sama dengan yang
     * dibandingkan `scopeLive`.
     *
     * Keduanya memakai zona aplikasi, jadi tes ini gagal kalau salah satunya
     * suatu saat dipaksa ke zona lain — bentuk kegagalan yang persis membuat
     * penjadwalan tayang tujuh jam terlambat.
     */
    public function test_a_scheduled_time_is_read_in_the_same_zone_as_now(): void
    {
        $picked = CarbonImmutable::parse('2026-09-06T14:00');

        $this->assertSame(config('app.timezone'), $picked->timezoneName);
        $this->assertSame($picked->timezoneName, now()->timezoneName);
    }
}
