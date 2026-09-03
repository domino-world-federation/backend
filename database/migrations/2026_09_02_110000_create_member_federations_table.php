<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Federasi anggota — versi MINIMAL, sengaja.
 *
 * Ia lahir lebih awal dari jadwalnya karena satu field di layar Add Admin
 * (`529:9693`, "Federation Scope") memerlukan daftar untuk dipilih, dan field
 * itu tidak bisa dibangun di atas tabel yang belum ada.
 *
 * Yang ADA di sini cuma yang dibutuhkan pemilih itu: nama, negara, dan sakelar
 * aktif. Yang TIDAK ada — bendera, tier, presiden, kontak, koordinat peta —
 * bukan kelalaian: seluruhnya milik modul Members di fase D
 * (`../../docs/PRD-API-PUBLIK.md` §6b), dan menebaknya sekarang berarti menulis
 * kolom yang akan dibongkar sebelum sempat dipakai.
 *
 * Karena itu tabel ini BELUM punya layar CMS. Isinya lewat seeder sampai modul
 * Members dibangun; catatannya di docs/PROGRESS.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_federations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('country');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_federations');
    }
};
