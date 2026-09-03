<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dua pelaku yang dituntut daftar Gallery (`478:5884`).
 *
 * Desain menggambar TIGA kolom berbentuk sama — Published, Created, Last
 * Modified — dan ketiganya memakai sel yang sama: satu nama di atas, satu
 * waktu di bawahnya (`478:5928` dan saudaranya). `updated_by_id` sudah ada dan
 * menjawab yang ketiga; dua sisanya belum punya siapa-siapa.
 *
 * `published_by_id` terpisah dari `created_by_id` dengan sengaja. Yang
 * mengunggah dan yang menayangkan sering bukan orang yang sama — itu justru
 * alur yang dijanjikan tombol "Save Draft": satu orang menyiapkan, orang lain
 * menekan Publish. Menyatukannya membuat kolom Published menyebut nama orang
 * yang tidak pernah memutuskan apa pun soal penayangannya.
 *
 * Keduanya `nullOnDelete`: menghapus akun tidak boleh ikut menghapus asetnya.
 * Yang hilang cuma namanya, dan itu memang jawaban yang jujur.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gallery_items', function (Blueprint $table) {
            $table->foreignId('created_by_id')->nullable()->after('updated_by_id')
                ->constrained('users')->nullOnDelete();
            $table->foreignId('published_by_id')->nullable()->after('created_by_id')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('gallery_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('published_by_id');
            $table->dropConstrainedForeignId('created_by_id');
        });
    }
};
