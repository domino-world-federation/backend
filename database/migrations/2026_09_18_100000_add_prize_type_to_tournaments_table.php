<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Hadiah utama punya JENIS — tunai, barang, atau tidak ada (`700:10891`).
 *
 * Sebelum ini kartu Prize hanya bisa menyatakan hadiah tunai: nominal, mata
 * uang, keterangan, gambar. Turnamen yang hadiahnya sebuah barang ("Handphone")
 * tidak punya tempat untuk menuliskannya, dan turnamen tanpa hadiah dinyatakan
 * dengan cara mengosongkan semuanya — keadaan yang tidak bisa dibedakan dari
 * "lupa mengisi".
 *
 * `prize_type` disimpan sebagai kata (`none` | `cash` | `item`), daftarnya di
 * `config('dwf.tournaments.prize_types')`. `prize_name` hanya berarti untuk
 * `item`.
 *
 * ── Isi awalnya mempertahankan situs publik apa adanya ──
 *
 * Turnamen yang sudah punya nominal jadi `cash`; sisanya `none`. Itu persis
 * aturan yang dipakai `TournamentDetailResource::prize()` sampai hari ini —
 * blok hadiah hanya dikirim kalau nominalnya ada — jadi pada hari migrasi ini
 * tidak satu pun halaman turnamen berubah tampilannya. Turnamen yang punya
 * keterangan atau gambar hadiah tanpa nominal ikut jadi `none`: situs publik
 * memang tidak pernah menampilkannya, dan menebak ia sebenarnya hadiah barang
 * berarti mengarang `prize_name` yang tidak pernah diketik siapa pun. Datanya
 * tidak dibuang — tinggal dipilih "Physical Item" di formulirnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->string('prize_type', 16)->default('none')->after('prize_image_path');
            $table->string('prize_name', 120)->nullable()->after('prize_type');
        });

        DB::table('tournaments')->whereNotNull('prize_amount')->update(['prize_type' => 'cash']);
    }

    public function down(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropColumn(['prize_type', 'prize_name']);
        });
    }
};
