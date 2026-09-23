<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * "Tournament Rules Format" dipecah jadi DUA field (`585:11241`).
 *
 * Satu kolom `rules_format` menyimpan enam nama — Single 101, Double 101,
 * Single Knockout, Double Knockout, Single BO3, Double BO3 — dan keenamnya
 * ternyata hasil silang dua pertanyaan yang berdiri sendiri: berapa orang per
 * sisi (`tournament_mode`), dan bagaimana satu pertandingan dimenangkan
 * (`domino_rules`). Atas permintaan pemilik repo keduanya ditanya terpisah.
 *
 * Kombinasinya tetap enam yang sama, jadi konversinya LENGKAP dan tanpa
 * tebakan — tiap nama lama punya tepat satu pasangan baru:
 *
 *     Single 101      → Single + 101
 *     Double 101      → Double + 101
 *     Single Knockout → Single + 1 Round
 *     Double Knockout → Double + 1 Round
 *     Single BO3      → Single + Double Win
 *     Double BO3      → Double + Double Win
 *
 * `Knockout` dan `BO3` ikut berganti nama jadi `1 Round` dan `Double Win`,
 * nama yang dipakai federasi sendiri. Mekanismenya tidak berubah.
 *
 * ── `rules_format` DIBUANG, tidak ditinggal ──
 *
 * Kolom lama yang dibiarkan berdiri akan tetap diisi kode lama yang terlewat,
 * dan sejak hari ini ia tidak punya pemilik: tidak ada layar yang menulisnya
 * dan tidak ada pembaca yang mempercayainya. Dua sumber untuk satu fakta persis
 * yang dihindari di seluruh repo ini. Yang menggantikannya di API publik
 * `formatLabel`, dirangkai dari pasangan barunya.
 *
 * `down()` merangkai ulang nama lamanya, jadi migrasi ini bisa dibalik tanpa
 * kehilangan apa pun.
 */
return new class extends Migration
{
    /** Nama lama → [mode, aturan]. */
    private const FORWARD = [
        'Single 101' => ['Single', '101'],
        'Double 101' => ['Double', '101'],
        'Single Knockout' => ['Single', '1 Round'],
        'Double Knockout' => ['Double', '1 Round'],
        'Single BO3' => ['Single', 'Double Win'],
        'Double BO3' => ['Double', 'Double Win'],
    ];

    public function up(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->string('tournament_mode', 16)->nullable()->after('rules_format');
            $table->string('domino_rules', 24)->nullable()->after('tournament_mode');
        });

        foreach (self::FORWARD as $old => [$mode, $rules]) {
            DB::table('tournaments')
                ->where('rules_format', $old)
                ->update(['tournament_mode' => $mode, 'domino_rules' => $rules]);
        }

        /*
         * Baris yang namanya TIDAK dikenal — data lama dari sebelum daftarnya
         * ditutup — diberi pasangan bawaan alih-alih ditinggal kosong: kolomnya
         * wajib di formulir, dan baris tanpa isi tidak bisa disimpan ulang
         * sampai seseorang menebak apa yang dulu tertulis di sana. `Single` +
         * `101` adalah kombinasi paling sederhana, dan yang salah tinggal
         * diperbaiki di formulirnya.
         */
        DB::table('tournaments')
            ->whereNull('tournament_mode')
            ->update(['tournament_mode' => 'Single', 'domino_rules' => '101']);

        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropColumn('rules_format');
        });
    }

    public function down(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->string('rules_format', 60)->nullable();
        });

        foreach (self::FORWARD as $old => [$mode, $rules]) {
            DB::table('tournaments')
                ->where('tournament_mode', $mode)
                ->where('domino_rules', $rules)
                ->update(['rules_format' => $old]);
        }

        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropColumn(['tournament_mode', 'domino_rules']);
        });
    }
};
