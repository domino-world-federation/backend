<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SEO & Social — judul, deskripsi, dan gambar bagikan per halaman publik.
 *
 * Sebelum ini ke-18 halaman menanam `useSeoMeta({ title, description })` di
 * berkas Vue-nya masing-masing, jadi mengubah satu kalimat deskripsi menuntut
 * deploy. Dan tidak ada satu pun `og:image` di seluruh repo — tiap tautan DWF
 * yang dibagikan ke WhatsApp, X, atau Facebook tampil tanpa gambar.
 *
 * ── Baris bawaan memakai `route = '*'`. ──
 *
 * Bukan tabel kedua dan bukan kolom `is_default`: yang dicari halaman selalu
 * "meta untuk rute ini, atau bawaannya", dan satu tabel menjawab keduanya
 * dengan satu query. Tanda bintang dipilih karena ia bukan rute yang sah, jadi
 * ia tidak akan pernah bertabrakan dengan halaman sungguhan.
 *
 * ── Rute DINAMIS tidak masuk sini. ──
 *
 * `/news/[slug]` dan `/tournaments/[slug]` menyusun metanya dari isi barisnya
 * sendiri — judul artikel adalah judul halamannya. Menyimpannya di sini berarti
 * satu baris untuk ribuan halaman yang semuanya berbeda.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('page_meta', function (Blueprint $table) {
            $table->id();
            // "/", "/about", "/tournaments/all" — persis seperti path Nuxt-nya.
            $table->string('route', 160)->unique();
            $table->string('label', 120);
            $table->string('title', 200)->nullable();
            $table->string('description', 320)->nullable();
            $table->string('og_image_path')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->foreignId('updated_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_meta');
    }
};
