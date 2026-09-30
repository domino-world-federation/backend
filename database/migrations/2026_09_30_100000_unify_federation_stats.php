<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * Statistik federasi jadi SATU daftar untuk beranda dan `/federation-members`.
 *
 * Dua daftar (`home`, `members`) berjalan terpisah dan berbeda angka di prod —
 * yang satu diperbarui, yang lain terlupa. Pemenangnya daftar yang PALING BARU
 * disunting (`updated_at` terbaru di antara barisnya; `id` terbesar sebagai
 * penentu kalau sama), keputusan pemilik repo 2026-09-30. Ia berakhir di
 * lingkup `home`, satu-satunya yang dibaca sekarang.
 *
 * Yang kalah TIDAK dihapus: pindah ke lingkup `archive`, tidak tayang, tetap
 * ada kalau ternyata angkanya yang benar.
 */
return new class extends Migration
{
    public function up(): void
    {
        $latest = fn (string $scope) => DB::table('federation_stats')
            ->where('scope', $scope)
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->first(['updated_at', 'id']);

        $home = $latest('home');
        $members = $latest('members');

        if ($members === null) {
            return;
        }

        $membersWins = $home === null
            || $members->updated_at > $home->updated_at
            || ($members->updated_at == $home->updated_at && $members->id > $home->id);

        if ($membersWins) {
            DB::table('federation_stats')->where('scope', 'home')->update(['scope' => 'archive']);
            DB::table('federation_stats')->where('scope', 'members')->update(['scope' => 'home']);
        } else {
            DB::table('federation_stats')->where('scope', 'members')->update(['scope' => 'archive']);
        }
    }

    public function down(): void
    {
        // Tidak dibalik: setelah penyatuan tidak ada lagi yang tahu daftar
        // mana yang dulu milik halaman mana. Arsipnya tetap ada di `archive`.
    }
};
