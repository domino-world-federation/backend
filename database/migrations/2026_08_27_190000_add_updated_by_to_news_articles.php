<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kolom "Last Modified" di daftar berita menyebut NAMA, bukan hanya waktu.
 *
 * `author_id` tidak bisa menjawabnya. Penulis dan penyunting terakhir sering
 * bukan orang yang sama, dan pertanyaan yang membawa orang ke kolom itu justru
 * "siapa yang mengubah ini", bukan "siapa yang dulu menulisnya".
 *
 * `nullOnDelete`, sama seperti `author_id`: menghapus akun tidak boleh ikut
 * menghapus artikelnya. Yang hilang cuma namanya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('news_articles', function (Blueprint $table) {
            $table->foreignId('updated_by_id')
                ->nullable()
                ->after('author_id')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('news_articles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('updated_by_id');
        });
    }
};
