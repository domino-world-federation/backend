<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Daftar IP yang boleh membuka backoffice — Figma `527:7038`.
 *
 * Satu tabel, bukan satu per lingkup. Desainnya (`527:7039`) menaruh "All
 * Admins", nama peran, dan nama orang di SATU kolom "Access Scope", jadi
 * ketiganya memang satu jenis baris yang berbeda sasarannya — memecahnya jadi
 * tiga tabel berarti tiga layar dan tiga tempat untuk lupa.
 *
 * `role_id` dan `user_id` keduanya nullable dan saling meniadakan; yang
 * menentukan mana yang dibaca adalah `scope`. Validasinya di
 * `IpWhitelistRuleRequest`, bukan di database — Postgres bisa menegakkannya
 * lewat CHECK, tapi galat constraint yang muncul sebagai 500 jauh lebih buruk
 * daripada pesan validasi di sebelah fieldnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ip_whitelist_rules', function (Blueprint $table) {
            $table->id();

            // "PB Office Jakarta", "Security VPN" — nama yang dikenali orang.
            $table->string('name');

            // IPv4, IPv6, atau CIDR. 45 karakter cukup untuk IPv6 terpanjang;
            // 64 memberi ruang untuk sufiks prefix.
            $table->string('ip_range', 64);

            // all_admins | role | user
            $table->string('scope', 16);
            $table->foreignId('role_id')->nullable()->constrained('roles')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();

            // permanent | temporary
            $table->string('validity', 16)->default('permanent');
            $table->timestamp('expires_at')->nullable();

            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);

            // Desain mencetak "Created By" dan "Last Updated" sebagai kolom
            // hanya-baca (`527:8163`). Yang kedua diisi trait `TracksEditor`.
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            // Middleware membaca baris aktif setiap request yang masuk
            // backoffice, disaring lingkup. Indeksnya mengikuti bentuk query
            // itu, bukan bentuk layar daftarnya.
            $table->index(['is_active', 'scope']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ip_whitelist_rules');
    }
};
