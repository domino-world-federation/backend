<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `site_settings` menampung lebih dari satu kelompok pengaturan.
 *
 * Sampai sekarang isinya cuma kontak dan tautan sosial, dan `/api/v1/settings`
 * mengembalikan SELURUH tabel apa adanya. Begitu naskah halaman depan ikut
 * masuk, endpoint itu akan mengirim headline hero ke footer yang cuma butuh
 * alamat surel — bukan kebocoran, tapi kontrak yang berhenti berarti apa-apa.
 *
 * Satu kolom lebih baik daripada tabel kedua dengan bentuk yang sama persis:
 * dua penyimpanan kunci-nilai berdampingan berarti dua tempat untuk mencari
 * saat sebuah nilai tidak muncul di situs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            // Bawaannya `contact` supaya baris yang sudah ada tetap terbaca
            // endpoint yang sudah ada, tanpa langkah backfill terpisah yang
            // bisa terlewat di mesin orang lain.
            $table->string('group')->default('contact')->after('key');
            $table->index('group');
        });

        DB::table('site_settings')->update(['group' => 'contact']);
    }

    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->dropIndex(['group']);
            $table->dropColumn('group');
        });
    }
};
