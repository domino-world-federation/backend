<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `game_format` menjadi nullable — kolomnya pensiun, bukan dihapus.
 *
 * Field-nya dicabut dari Add/Edit Tournament 2026-09-07 atas permintaan pemilik
 * repo: `rules_format` sudah menjawab pertanyaan yang sama ("Double 101"), dan
 * ia dipilih dari daftar tertutup yang sekaligus menentukan jumlah peserta,
 * penilaian, dan kalimat sistem kompetisinya. Sejak itu tidak ada lagi yang
 * menulis ke kolom ini, sementara di database ia masih NOT NULL tanpa default —
 * turnamen baru akan gagal disimpan.
 *
 * Dibuat nullable, bukan di-drop: baris lama masih menyimpan kalimat yang
 * pernah diketik orang, dan mengembalikan field-nya suatu saat nanti tidak
 * perlu menyentuh skema lagi. Yang membaca `formatLabel` dan fakta
 * "Game format" di situs publik sekarang `rules_format`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->string('game_format', 80)->nullable()->change();
        });
    }

    public function down(): void
    {
        /*
         * Baris yang tersimpan setelah field-nya dicabut punya `game_format`
         * kosong; NOT NULL tidak bisa dipasang kembali di atasnya. Diisi dari
         * `rules_format` — sumber yang sama dengan yang dibaca situs publik.
         */
        DB::table('tournaments')->whereNull('game_format')->update([
            'game_format' => DB::raw('rules_format'),
        ]);

        Schema::table('tournaments', function (Blueprint $table) {
            $table->string('game_format', 80)->nullable(false)->change();
        });
    }
};
