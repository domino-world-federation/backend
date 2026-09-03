<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `attendance` — "Offline" atau "Online".
 *
 * Tidak ada di desain Add Tournament (`585:11241`), tapi kontrak frontend
 * memintanya: `Tournament.attendance` di
 * `../../landing-page-nuxt/app/lib/api/types.ts` digambar sebagai pil di
 * sebelah kategori (`592:16886`).
 *
 * Ditambahkan sebagai kolom, bukan dikembalikan sebagai konstanta dari API.
 * Konstanta akan membuat setiap turnamen online tercetak "Offline" — field
 * kontrak yang selalu salah lebih buruk daripada field yang belum ada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->string('attendance', 16)->default('Offline')->after('rules_format');
        });
    }

    public function down(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropColumn('attendance');
        });
    }
};
