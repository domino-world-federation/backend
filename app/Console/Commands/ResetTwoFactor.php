<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * Jalan keluar terakhir kalau seseorang kehilangan ponsel DAN kode pemulihannya.
 *
 * Tanpa perintah ini, satu-satunya cara memulihkan akun adalah menyunting
 * database tangan — dan itu berarti orang akan melakukannya sambil menebak
 * kolom mana yang harus dikosongkan.
 */
class ResetTwoFactor extends Command
{
    protected $signature = 'dwf:2fa:reset {email : Email akun yang 2FA-nya direset}';

    protected $description = 'Hapus pendaftaran 2FA sebuah akun supaya bisa mendaftar ulang';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();

        if ($user === null) {
            $this->error("Tidak ada akun dengan email {$this->argument('email')}.");

            return self::FAILURE;
        }

        if (! $user->hasConfirmedTwoFactor()) {
            $this->info("{$user->email} memang belum mendaftarkan 2FA — tidak ada yang perlu direset.");

            return self::SUCCESS;
        }

        if (! $this->confirm("Reset 2FA untuk {$user->email}? Ia akan diminta memindai QR baru saat login berikutnya.")) {
            return self::FAILURE;
        }

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        $this->info("2FA untuk {$user->email} direset.");

        return self::SUCCESS;
    }
}
