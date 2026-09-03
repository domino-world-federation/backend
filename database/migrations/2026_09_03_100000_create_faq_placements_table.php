<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Urutan FAQ jadi milik HALAMAN, bukan milik daftar global.
 *
 * Sebelum ini `faqs.position` cuma satu angka. Ia dipakai bersamaan oleh layar
 * daftar, layar "Manage Order", DAN `/api/v1/faqs?page=…` — jadi urutan di Home
 * dan urutan di Domino adalah urutan yang sama, disaring. Satu pertanyaan yang
 * menempel di dua halaman punya satu peringkat untuk keduanya, dan menggesernya
 * demi Home ikut menggeser Domino tanpa ada satu pun layar yang memberi tahu.
 * Tidak ada tempat untuk menyimpan "di Home dia nomor 1, di Domino nomor 3".
 *
 * Kolom `faqs.pages` (JSON) ikut dibuang, bukan dibiarkan berdampingan: dua
 * sumber untuk pertanyaan "halaman mana" persis jenis kesalahan yang sedang
 * diperbaiki di sini.
 *
 * `faqs.position` TETAP ADA dan sekarang punya arti yang lebih sempit — urutan
 * di halaman FAQ lengkap (`/page/faq`), tempat pertanyaan dikelompokkan per
 * kategori dan bukan per halaman.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('faq_placements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('faq_id')->constrained()->cascadeOnDelete();

            // Kunci halaman (`home`, `domino`, `tournament`) — daftarnya di
            // `Faq::PAGES` dan label tampilannya di `config('dwf.faq_pages')`.
            $table->string('page');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            // Satu FAQ menempel di satu halaman paling banyak SEKALI. Tanpa
            // batasan ini, "tambahkan ke Home" yang tertekan dua kali
            // menghasilkan dua baris dengan peringkat berbeda untuk pertanyaan
            // yang sama, dan halaman publiknya mencetaknya dua kali.
            $table->unique(['faq_id', 'page']);
            $table->index(['page', 'position']);
        });

        // Pindahkan penempatan yang sudah ada. Urutan awal per halaman diambil
        // dari `faqs.position` yang lama, jadi pada hari migrasi tidak ada satu
        // halaman pun yang berubah tampilannya — yang berubah cuma kemampuan
        // untuk mengubahnya sendiri-sendiri sesudah ini.
        $counters = [];

        foreach (DB::table('faqs')->orderBy('position')->orderBy('id')->get(['id', 'pages']) as $faq) {
            foreach (json_decode($faq->pages ?? '[]', true) ?: [] as $page) {
                $counters[$page] = ($counters[$page] ?? 0) + 1;

                DB::table('faq_placements')->insert([
                    'faq_id' => $faq->id,
                    'page' => $page,
                    'position' => $counters[$page],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        Schema::table('faqs', function (Blueprint $table) {
            $table->dropColumn('pages');
        });
    }

    public function down(): void
    {
        Schema::table('faqs', function (Blueprint $table) {
            $table->json('pages')->default('[]');
        });

        foreach (DB::table('faq_placements')->orderBy('position')->get(['faq_id', 'page']) as $row) {
            $current = DB::table('faqs')->where('id', $row->faq_id)->value('pages');
            $pages = json_decode($current ?? '[]', true) ?: [];
            $pages[] = $row->page;

            DB::table('faqs')->where('id', $row->faq_id)->update(['pages' => json_encode($pages)]);
        }

        Schema::dropIfExists('faq_placements');
    }
};
