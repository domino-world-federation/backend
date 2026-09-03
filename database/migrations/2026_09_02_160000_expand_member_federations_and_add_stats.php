<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Federasi anggota naik dari tabel minimal jadi modul penuh.
 *
 * `member_federations` lahir lebih awal (migrasi `2026_09_02_110000`) hanya
 * supaya field "Federation Scope" di Add Admin punya daftar untuk dipilih.
 * Kolom di bawah melengkapinya ke kontrak `MemberFederation` di
 * `../../landing-page-nuxt/app/lib/api/types.ts` — sebelas field, tidak lebih.
 *
 * ── Yang TIDAK ditambahkan: koordinat peta. ──
 *
 * Halaman `/federation-members` punya peta bertitik, dan naluri pertama adalah
 * menyimpan lintang-bujur tiap federasi. Ternyata keliru: 57 markernya dibaca
 * dari artwork `world-map-dots.svg` sebagai PERSENTASE kotak 1505×752, bukan
 * koordinat geografis (lihat `app/content/members/map-markers.ts`). Menyimpan
 * lat/lng di sini berarti kolom yang tidak pernah dibaca siapa pun.
 *
 * ── Sembilan dari sebelas field nullable, dan itu kontraknya. ──
 *
 * `types.ts` menuliskannya: daftar ini register federasi itu sendiri, dan
 * sebuah badan bisa diakui jauh sebelum ia menyetorkan nama presiden atau nomor
 * telepon. Kartunya mencetak baris yang ada dan membuang yang tidak — jadi
 * baris setengah terisi menghasilkan kartu yang lebih pendek, bukan kartu penuh
 * ruang kosong.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('member_federations', function (Blueprint $table) {
            $table->string('flag_path')->nullable()->after('country');
            $table->string('tier', 32)->nullable()->after('flag_path');
            $table->unsignedSmallInteger('joined_year')->nullable()->after('tier');
            $table->string('president')->nullable()->after('joined_year');
            // Satu baris siap cetak: "Miami, FL, United States".
            $table->string('headquarters')->nullable()->after('president');
            $table->string('email')->nullable()->after('headquarters');
            $table->string('phone', 40)->nullable()->after('email');
            $table->string('website_url')->nullable()->after('phone');

            $table->foreignId('updated_by_id')->nullable()->after('position')
                ->constrained('users')->nullOnDelete();

            $table->index('tier');
        });

        /*
         * Angka statistik federasi.
         *
         * SATU tabel untuk dua tempat, dibedakan `scope`: roda statistik di
         * beranda (`getFederationStats`) dan blok keanggotaan di
         * `/federation-members` (`getMembershipStats`). Bentuk barisnya identik
         * — label dan nilai — jadi dua tabel berarti dua layar admin untuk hal
         * yang sama.
         *
         * `value` string, BUKAN angka: desainnya mencetak "57", "1974", dan
         * "120+" di slot yang sama. Memaksanya jadi integer berarti yang
         * terakhir mustahil ditulis.
         */
        Schema::create('federation_stats', function (Blueprint $table) {
            $table->id();
            $table->string('scope', 16); // home | members
            $table->string('label');
            $table->string('value', 32);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('position')->default(0);
            $table->foreignId('updated_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['scope', 'is_active', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('federation_stats');

        Schema::table('member_federations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('updated_by_id');
            $table->dropIndex(['tier']);
            $table->dropColumn([
                'flag_path', 'tier', 'joined_year', 'president',
                'headquarters', 'email', 'phone', 'website_url',
            ]);
        });
    }
};
