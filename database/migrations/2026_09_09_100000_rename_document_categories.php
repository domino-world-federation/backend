<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Kosakata kategori dipendekkan jadi enam — dan barisnya ikut pindah.
 *
 * Alasan migrasinya sama persis dengan
 * `2026_09_05_150000_remap_document_categories`, dan ditulis ulang di sini
 * karena yang membacanya belum tentu membuka yang itu: kolom
 * `documents.category` menyimpan NAMA kategorinya apa adanya, jadi mengganti
 * daftar di `config/dwf.php` tanpa memindahkan barisnya meninggalkan dokumen
 * memegang nama yang tidak ada lagi di mana pun. Ia hilang dari filter layar
 * Documents, ditolak saat disunting, dan tidak tertarik section mana pun di
 * situs publik. Tanpa satu pun error — berkasnya cuma berhenti terlihat.
 *
 * Dua yang diganti ejaannya:
 *
 *   Reports & Publications → Publication
 *   Media & Press Releases → Press Releases
 *
 * Dua yang DIHAPUS dari daftar — `Integrity & Ethics` dan `Membership
 * Documents`. Keduanya tidak pernah punya rak: halaman Integrity dan Members
 * belum menggambar satu pun daftar dokumen, dan config lama menandainya
 * `planned` justru supaya layar Documents tidak menjanjikan tempat tayang yang
 * belum ada.
 *
 * Barisnya dipindahkan ke `Governance Documents`, bukan dibiarkan yatim dan
 * bukan dihapus. Saat migrasi ini ditulis TIDAK ADA satu baris pun memakai
 * keduanya (diperiksa langsung di basis data kerja: 3 dokumen, semuanya di
 * kategori lain), jadi pemetaan ini menjaga kemungkinan, bukan data — kalau
 * ada instalasi lain yang sudah memakainya, dokumennya mendarat di kategori
 * organisasi yang paling dekat alih-alih menghilang.
 *
 * `down()` TIDAK simetris, dan itu disengaja. Pembalikan ejaan bisa persis;
 * pemindahan dari dua kategori yang dihapus tidak bisa — begitu barisnya jadi
 * `Governance Documents`, tidak ada lagi yang membedakannya dari dokumen yang
 * memang selalu di sana. Membalikkannya dengan tebakan akan memindahkan baris
 * yang bukan miliknya.
 */
return new class extends Migration
{
    /** Ejaan yang berubah — dan hanya ini yang bisa dibalik dengan tepat. */
    private const RENAMED = [
        'Reports & Publications' => 'Publication',
        'Media & Press Releases' => 'Press Releases',
    ];

    /** Kategori yang dihapus, dan ke mana barisnya ditampung. */
    private const RETIRED = [
        'Integrity & Ethics' => 'Governance Documents',
        'Membership Documents' => 'Governance Documents',
    ];

    public function up(): void
    {
        $this->remap(self::RENAMED + self::RETIRED);
    }

    public function down(): void
    {
        // Dibalik, bukan ditulis ulang: dua daftar yang harus dijaga tetap
        // cerminan satu sama lain adalah dua daftar yang suatu saat tidak lagi.
        $this->remap(array_flip(self::RENAMED));
    }

    /** @param  array<string, string>  $map */
    private function remap(array $map): void
    {
        foreach ($map as $from => $to) {
            DB::table('documents')->where('category', $from)->update(['category' => $to]);
        }
    }
};
