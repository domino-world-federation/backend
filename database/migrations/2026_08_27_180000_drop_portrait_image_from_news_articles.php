<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gambar potret dibuang dari News.
 *
 * Formulirnya meminta tiga gambar per artikel — hero, potret, lanskap — dan yang
 * dipakai cuma dua. Kolomnya ikut dibuang, bukan sekadar field-nya dilepas dari
 * layar: kolom yang tidak bisa lagi diisi lewat antarmuka mana pun akan
 * dianggap "mungkin masih dipakai" oleh orang berikutnya, dan bertahan bertahun-
 * tahun sebagai pertanyaan yang tidak ada yang berani menjawab.
 *
 * Berkas yang telanjur terunggah TIDAK ikut dihapus dari `storage/`. Menghapus
 * berkas di dalam migrasi berarti `down()` mengembalikan kolomnya tapi bukan
 * isinya — dan migrasi yang tidak benar-benar bisa dibalik lebih berbahaya
 * daripada beberapa berkas yatim.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('news_articles', function (Blueprint $table) {
            $table->dropColumn('portrait_image_path');
        });
    }

    public function down(): void
    {
        Schema::table('news_articles', function (Blueprint $table) {
            $table->string('portrait_image_path')->nullable()->after('hero_image_path');
        });
    }
};
