<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tiga kolom yang dituntut layar Admin Users (`528:8821`).
 *
 * - `is_active` — kolom "Status". Akun nonaktif tetap ada berikut seluruh
 *   jejaknya, tapi tidak bisa login. Itu yang membedakannya dari menghapus:
 *   `activity_log.causer_id` menunjuk ke baris ini, dan menghapus akun berarti
 *   jejak audit kehilangan namanya justru pada orang yang paling ingin
 *   ditelusuri.
 * - `last_login_at` — kolom "Last Login". Desain mencetak "First login pending"
 *   untuk yang masih kosong (`528:8909`), jadi ia nullable dan TIDAK diisi
 *   nilai palsu saat akun dibuat.
 * - `member_federation_id` — field "Federation Scope" (`529:9693`), wajib hanya
 *   untuk peran yang lingkupnya federasi.
 *
 * `password` ikut jadi NULLABLE, dan itu konsekuensi alur undangan: akun dibuat
 * lebih dulu supaya ia muncul di daftar dengan Last Login kosong, sementara
 * sandinya baru lahir saat orangnya menerima undangan. Sandi kosong TIDAK bisa
 * dipakai login — `Hash::check()` terhadap `null` selalu gagal, dan
 * `LoginRequest` menolaknya lebih dulu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('email');
            $table->timestamp('last_login_at')->nullable()->after('is_active');
            $table->foreignId('member_federation_id')->nullable()
                ->after('last_login_at')
                ->constrained('member_federations')
                ->nullOnDelete();
        });

        // `change()` terpisah: menggabungnya dengan `after()` di atas membuat
        // Doctrine menulis ulang seluruh definisi kolom pada sebagian driver.
        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('member_federation_id');
            $table->dropColumn(['is_active', 'last_login_at']);
        });
    }
};
