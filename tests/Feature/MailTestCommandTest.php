<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Perintah `dwf:mail-test` harus GAGAL saat tidak ada surel yang keluar.
 *
 * Itu seluruh gunanya. `MAIL_MAILER=log` mengirim dengan sukses ke berkas log,
 * jadi perintah yang cuma membungkus `Mail::raw()` akan mencetak "Terkirim"
 * pada server yang tidak pernah mengirim apa pun ke siapa pun — persis
 * kesalahpahaman yang perintah ini dibuat untuk mencegahnya.
 */
class MailTestCommandTest extends TestCase
{
    public function test_it_fails_when_mail_only_goes_to_the_log(): void
    {
        config(['mail.default' => 'log']);

        $this->artisan('dwf:mail-test', ['email' => 'orang@example.com'])
            ->expectsOutputToContain('tidak ada surel yang keluar')
            ->assertExitCode(1);
    }

    /** `array` sama saja — ia mailer tes, dan di produksi berarti diam total. */
    public function test_it_fails_for_the_array_mailer_too(): void
    {
        config(['mail.default' => 'array']);

        $this->artisan('dwf:mail-test', ['email' => 'orang@example.com'])
            ->assertExitCode(1);
    }

    /** Alamat pengirim kosong ditolak SEBELUM mencoba menyambung. */
    public function test_it_fails_without_a_from_address(): void
    {
        config(['mail.default' => 'smtp', 'mail.from.address' => null]);

        $this->artisan('dwf:mail-test', ['email' => 'orang@example.com'])
            ->expectsOutputToContain('MAIL_FROM_ADDRESS')
            ->assertExitCode(1);
    }

    /**
     * Dan jalur yang benar LULUS — kalau tidak, tes di atas lulus karena
     * perintahnya menolak segalanya.
     */
    public function test_it_succeeds_on_a_working_mailer(): void
    {
        Mail::fake();
        config(['mail.default' => 'smtp', 'mail.from.address' => 'no-reply@dwf.test']);

        $this->artisan('dwf:mail-test', ['email' => 'orang@example.com'])
            ->expectsOutputToContain('Terkirim ke orang@example.com')
            ->assertExitCode(0);
    }
}
