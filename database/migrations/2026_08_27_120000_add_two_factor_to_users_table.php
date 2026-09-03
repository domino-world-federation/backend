<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Rahasia TOTP dan kode pemulihan disimpan TERENKRIPSI (cast
            // `encrypted` di model). Keduanya setara kunci: siapa pun yang
            // membaca `two_factor_secret` bisa membuat kode yang sah kapan pun,
            // jadi dump database yang bocor tidak boleh langsung berarti 2FA
            // yang bocor. `text`, bukan `string`, karena ciphertext Laravel
            // jauh lebih panjang dari nilai aslinya.
            $table->text('two_factor_secret')->nullable()->after('locale');
            $table->text('two_factor_recovery_codes')->nullable()->after('two_factor_secret');

            // Diisi hanya setelah pengguna berhasil memasukkan kode pertama.
            // Rahasia yang dibuat tapi belum dikonfirmasi TIDAK dianggap aktif —
            // kalau tidak, orang yang membuka halaman QR lalu menutupnya akan
            // terkunci di luar oleh rahasia yang tidak pernah ia pindai.
            $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_recovery_codes');

            // Sakelar per pengguna, untuk User Management nanti.
            $table->boolean('two_factor_enabled')->default(true)->after('two_factor_confirmed_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'two_factor_secret',
                'two_factor_recovery_codes',
                'two_factor_confirmed_at',
                'two_factor_enabled',
            ]);
        });
    }
};
