<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Naskah halaman yang disunting di editor halaman (`/pages`) menyimpan DRAF di
 * samping nilai yang sudah terbit.
 *
 * Di `site_settings`, bukan tabel baru: aturan repo ini satu penyimpanan
 * kunci-nilai dengan kolom `group` (lihat CONVENTIONS), dan naskah halaman
 * persis bentuk itu — satu kunci, satu nilai, dikelompokkan per halaman
 * (`page.about`). Yang baru hanya keadaannya: `value` adalah yang tayang,
 * `draft` yang sedang dikerjakan. `draft` null berarti tidak ada perubahan
 * tertunda; string kosong berarti "kembalikan ke bawaan kode" begitu diterbitkan.
 *
 * `updated_by_id` diisi trait `TracksEditor`, sama seperti modul lain.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->text('draft')->nullable()->after('value');
            $table->foreignId('updated_by_id')->nullable()->after('draft')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('updated_by_id');
            $table->dropColumn('draft');
        });
    }
};
