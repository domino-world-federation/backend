<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Turnamen bisa DITANDAI unggulan — "Set Featured" (`585:11241`, kartu terakhir).
 *
 * Sampai sekarang pita "Featured Event" di beranda memilih sendiri: enam
 * turnamen terdekat yang belum berakhir. Tidak ada cara memberi tahu pita itu
 * bahwa sebuah turnamen kecil tidak layak mewakili federasi di halaman depan,
 * dan tidak ada cara mengangkat satu yang layak ke depan.
 *
 * Flagnya menyaring, seperti `news_articles.is_highlighted` — dan seperti di
 * News, yang tidak ditandai memang tidak muncul.
 *
 * ── Flag ini SENDIRIAN, tanpa saringan tanggal ──
 *
 * Turnamen yang sudah berakhir BOLEH diunggulkan; keputusan pemilik repo.
 * Pitanya karena itu menjawab "apa yang dipilih federasi", bukan "apa yang
 * akan datang", dan kejuaraan yang baru usai adalah pilihan yang sah untuk
 * halaman depan.
 *
 * Harganya dicatat di sini supaya tidak ditemukan sebagai kejutan: yang
 * menahan turnamen 2023 tetap terpampang cuma seseorang yang ingat mencabut
 * centangnya. Tidak ada layar yang mengingatkan, dan halamannya terlihat
 * normal sepenuhnya. Yang mengembalikan lantai tanggalnya satu baris
 * `whereDate()` di `PublicController::showcaseEvents()` — urutan dua
 * tingkat di sana juga lahir dari keputusan ini.
 *
 * ── Isi awalnya mempertahankan beranda apa adanya ──
 *
 * Yang ditandai `true` HANYA turnamen yang belum berakhir, karena itu PERSIS
 * yang dipilih pita itu hari ini — jadi pada hari migrasi beranda tidak
 * berubah sedikit pun. Turnamen yang sudah lewat sengaja dibiarkan `false`
 * meski sekarang berhak: mengangkat seluruh arsip sekaligus adalah perubahan
 * yang tidak diminta siapa pun, dan mencentangnya satu per satu justru
 * pilihan editorial yang jadi alasan kolom ini ada.
 *
 * Membiarkan semuanya `false` bukan pilihan: pita yang kosong berarti
 * SECTION-NYA HILANG dari beranda sampai ada yang mencentang.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            // Diindeks: `showcaseEvents()` menyaringnya tiap kali beranda
            // dimuat, dan Postgres tidak mengindeks apa pun tanpa diminta.
            $table->boolean('is_featured')->default(false)->index()->after('attendance');
        });

        DB::table('tournaments')
            ->whereDate('ends_on', '>=', now()->startOfDay())
            ->update(['is_featured' => true]);
    }

    public function down(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropColumn('is_featured');
        });
    }
};
