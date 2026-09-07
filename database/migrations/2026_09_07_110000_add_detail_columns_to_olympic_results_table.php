<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kolom untuk halaman "More Olympics Results" (`648:30473`).
 *
 * Tabel di halaman turnamen mencetak lima kolom dan berhenti di situ. Halaman
 * penuhnya membuka satu baris jadi akordeon: tanggal ajangnya, tempatnya,
 * formatnya, dan satu kartu foto juara di sebelah kanan (`648:30605`). Semuanya
 * fakta tentang baris yang sama, jadi kolom di tabel yang sama — bukan tabel
 * kedua yang harus dijaga sejajar dengan yang pertama.
 *
 * Semua nullable. Baris yang sudah ada diisi lewat layar bulk yang tidak pernah
 * menanyakan hal-hal ini, dan akordeon mencetak fakta yang ADA saja: satu baris
 * lama membuka dengan lebih sedikit isi, bukan dengan label kosong.
 *
 * `event_date` string, bukan `date`. Desainnya menulis "Aug 14-17, 2025" —
 * rentang, bukan satu hari — dan tipe tanggal akan memaksa memilih ujung mana
 * yang disimpan lalu mencetak yang salah.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('olympic_results', function (Blueprint $table) {
            $table->string('event_date', 64)->nullable()->after('category');
            $table->string('location', 160)->nullable()->after('event_date');
            $table->string('format', 160)->nullable()->after('location');
            $table->string('champion_photo_path')->nullable()->after('federation');
            $table->string('champion_photo_alt', 200)->nullable()->after('champion_photo_path');
        });
    }

    public function down(): void
    {
        Schema::table('olympic_results', function (Blueprint $table) {
            $table->dropColumn([
                'event_date',
                'location',
                'format',
                'champion_photo_path',
                'champion_photo_alt',
            ]);
        });
    }
};
