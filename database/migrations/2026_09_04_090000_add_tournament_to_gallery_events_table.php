<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Album bertipe "tournament" sekarang MENUNJUK turnamennya, bukan mengeja
 * namanya sendiri.
 *
 * Sebelum ini layar Add Gallery membiarkan orang mengetik nama turnamen baru,
 * jadi "Madrid Qualifier 2026" di galeri adalah teks yang tidak berhubungan
 * dengan turnamen mana pun di modul Tournaments. Dua daftar turnamen yang
 * berdampingan dan sama-sama terlihat benar — dan yang di galeri tidak pernah
 * ikut berubah saat turnamennya diganti nama.
 *
 * Kolomnya `nullable` karena baris lama memang tidak punya jawabannya: tidak
 * ada satu pun nama yang cocok, dan menebaknya lewat kemiripan teks justru
 * melahirkan tautan yang salah — persis kesalahan yang sedang diperbaiki.
 * Baris lama dibiarkan apa adanya; ia tetap tampil di daftar, dan barulah saat
 * asetnya disunting orangnya diminta memilih turnamen yang sebenarnya.
 *
 * `unique` karena satu turnamen punya satu album. Tanpa itu, dua kali unggah
 * menghasilkan dua album bernama sama untuk satu pertandingan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gallery_events', function (Blueprint $table) {
            $table->foreignId('tournament_id')
                ->nullable()
                ->after('type')
                ->unique()
                ->constrained()
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('gallery_events', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tournament_id');
        });
    }
};
