<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Isi tiap rak dokumen jadi sesuatu yang bisa DIPILIH, bukan diturunkan.
 *
 * Sebelum ini tiap rak di situs publik menarik "N terbaru dari kategori X"
 * sendiri. Akibatnya dua: tidak ada seorang pun yang bisa memutuskan dokumen
 * mana yang tampil di rak mana, dan dua rak yang menarik kategori yang sama
 * menampilkan isi yang identik — persis keadaan Statutes & Constitution dan
 * Governance Repository, yang dua-duanya `Governance Documents`.
 *
 * Bentuknya sengaja meniru `faq_placements`, sampai ke nama kolomnya. Masalahnya
 * memang masalah yang sama (satu entitas menempel di banyak tempat, dengan
 * urutan yang berbeda di tiap tempat), dan dua tabel yang menyelesaikan masalah
 * yang sama dengan bentuk yang berbeda adalah dua tabel yang harus dipelajari
 * dua kali.
 *
 * Tabel ini TIDAK menyimpan kategori maupun batasnya. Keduanya sifat RAK-nya,
 * bukan sifat penempatan, dan tempatnya di `config('dwf.document_sections')` —
 * menyalinnya ke sini berarti angka yang harus diubah di dua tempat setiap
 * desainer menggeser satu grid.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_placements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();

            // Kunci rak (`news.publications`, `domino.rulebook`) — daftarnya di
            // `config('dwf.document_sections')`. Disimpan sebagai string, bukan
            // FK ke tabel section: daftarnya milik kode, dan tabel referensi
            // yang isinya di-seed dari config adalah satu daftar yang sama
            // disimpan dua kali.
            $table->string('section');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            // Satu dokumen menempel di satu rak paling banyak SEKALI. Tanpa
            // ini, "tambahkan" yang tertekan dua kali menghasilkan dua baris
            // dengan peringkat berbeda, dan rak publiknya mencetak dokumen itu
            // dua kali.
            $table->unique(['document_id', 'section']);
            $table->index(['section', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_placements');
    }
};
