<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Menguji jalur surel tanpa membakar undangan sungguhan.
 *
 * Aplikasi ini cuma mengirim SATU surel — tautan undangan admin — dan tautan
 * itu sekali pakai, berlaku 72 jam, dan satu-satunya cara pemilik akun baru
 * bisa masuk pertama kali. Tanpa perintah ini, satu-satunya cara memastikan
 * SMTP-nya benar adalah mengundang orang sungguhan dan menunggu ia mengabari
 * bahwa surelnya tidak pernah datang.
 *
 * Yang dicetak lebih dulu adalah konfigurasi yang BENAR-BENAR dibaca aplikasi,
 * bukan isi `.env`. Keduanya bisa berbeda, dan itu kegagalan paling sering di
 * server ini: `bootstrap/cache/config.php` yang belum dibuang, atau OPcache
 * yang belum melepas berkas itu (§8 PRODUCTION.md). Membaca `.env` dengan mata
 * tidak akan pernah memperlihatkannya.
 */
class MailTest extends Command
{
    protected $signature = 'dwf:mail-test {email : Alamat tujuan surel uji}';

    protected $description = 'Kirim satu surel uji dan laporkan persis apa yang gagal';

    /** Mailer yang TIDAK mengirim ke mana pun, sekeras apa pun ia terlihat berhasil. */
    private const OFFLINE_MAILERS = ['log', 'array'];

    public function handle(): int
    {
        $mailer = config('mail.default');
        $from = config('mail.from.address');

        $this->line('');
        $this->line('  Yang dibaca aplikasi <fg=gray>(bukan isi .env — lihat komentar di kelas ini)</>:');
        $this->line("    mailer  : <fg=yellow>{$mailer}</>");

        if ($mailer === 'smtp') {
            $this->line('    host    : '.config('mail.mailers.smtp.host').':'.config('mail.mailers.smtp.port'));
            $this->line('    scheme  : '.(config('mail.mailers.smtp.scheme') ?? '(kosong)'));
            $this->line('    username: '.(config('mail.mailers.smtp.username') ?? '(kosong)'));
            $this->line('    password: '.(config('mail.mailers.smtp.password') ? '(terisi)' : '<fg=red>(kosong)</>'));
        }

        $this->line("    from    : {$from}");
        $this->line('');

        /*
         * `log` dan `array` "berhasil" mengirim — dan itu justru bahayanya.
         * Dilaporkan sebagai KEGAGALAN di sini, karena orang yang menjalankan
         * perintah ini sedang bertanya "apakah surelnya sampai ke orang", dan
         * jawabannya tidak.
         */
        if (in_array($mailer, self::OFFLINE_MAILERS, true)) {
            $this->error("MAIL_MAILER={$mailer} — tidak ada surel yang keluar dari server ini.");
            $this->line('');
            $this->line('  Surel undangan admin ditulis ke storage/logs/laravel.log, LENGKAP dengan');
            $this->line('  tautan penerimaannya. Setel MAIL_MAILER=smtp, lalu:');
            $this->line('    <fg=yellow>php artisan config:clear && sudo systemctl reload php8.4-fpm</>');

            return self::FAILURE;
        }

        if (blank($from)) {
            $this->error('MAIL_FROM_ADDRESS kosong — penyedia mana pun akan menolak surelnya.');

            return self::FAILURE;
        }

        $to = $this->argument('email');

        try {
            Mail::raw(
                'Uji kirim dari '.config('app.name').".\n\n"
                ."Kalau surel ini sampai, jalur SMTP-nya benar dan undangan admin akan lewat jalan yang sama.\n"
                .'Dikirim '.now()->toDateTimeString().' dari '.config('app.url').".\n",
                fn ($message) => $message->to($to)->subject('Uji kirim '.config('app.name')),
            );
        } catch (Throwable $e) {
            $this->error('Gagal mengirim.');
            $this->line('');
            $this->line('  <fg=red>'.$e->getMessage().'</>');
            $this->line('');
            $this->line('  Yang paling sering: kredensial salah, port 587 diblokir keluar oleh');
            $this->line('  hoster, atau domain pengirim belum terverifikasi di penyedianya.');

            return self::FAILURE;
        }

        $this->info("Terkirim ke {$to} tanpa galat.");
        $this->line('');
        $this->line('  <fg=gray>Terkirim BUKAN berarti masuk inbox. Periksa folder spam juga —</>');
        $this->line('  <fg=gray>kalau ia mendarat di sana, yang kurang biasanya DKIM atau DMARC.</>');

        return self::SUCCESS;
    }
}
