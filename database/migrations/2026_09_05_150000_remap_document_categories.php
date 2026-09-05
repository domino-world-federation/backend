<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Kosakata kategori dokumen diganti — dan barisnya ikut pindah.
 *
 * Kolom `documents.category` menyimpan nama kategorinya apa adanya, jadi
 * mengganti daftar di `config/dwf.php` tanpa migrasi ini akan meninggalkan
 * setiap dokumen yang sudah diunggah memegang nama yang tidak ada lagi di mana
 * pun: ia tidak muncul di filter layar Documents, tidak lolos validasi saat
 * disunting, dan tidak tertarik oleh section mana pun di situs publik. Tidak
 * ada yang error — berkasnya cuma berhenti terlihat.
 *
 * Pemetaan di bawah bukan tebakan tentang isi berkasnya, melainkan tentang di
 * mana berkas itu selama ini dimaksudkan tayang:
 *
 *   Annual Report      → Reports & Publications  (laporan tahunan disebut
 *                        namanya di kategori barunya)
 *   Media Release      → Media & Press Releases
 *   Regulation         → Rules & Regulations
 *   Tournament Toolkit → Tournament Documents
 *   Partnership        → Governance Documents    (satu-satunya yang benar-benar
 *                        pilihan: dokumen kemitraan adalah dokumen organisasi,
 *                        dan tidak ada kategori baru yang menyebut kemitraan.
 *                        Tidak ada baris yang memakainya saat migrasi ini
 *                        ditulis, jadi ia menjaga kemungkinan, bukan data.)
 *
 * `down()` mengembalikannya persis, supaya rollback tidak meninggalkan yatim
 * dalam arah sebaliknya.
 */
return new class extends Migration
{
    /** @var array<string, string> */
    private const FORWARD = [
        'Annual Report' => 'Reports & Publications',
        'Media Release' => 'Media & Press Releases',
        'Regulation' => 'Rules & Regulations',
        'Tournament Toolkit' => 'Tournament Documents',
        'Partnership' => 'Governance Documents',
    ];

    public function up(): void
    {
        $this->remap(self::FORWARD);
    }

    public function down(): void
    {
        // Dibalik, bukan ditulis ulang: dua daftar yang harus dijaga tetap
        // cerminan satu sama lain adalah dua daftar yang suatu saat tidak lagi.
        $this->remap(array_flip(self::FORWARD));
    }

    /** @param  array<string, string>  $map */
    private function remap(array $map): void
    {
        foreach ($map as $from => $to) {
            DB::table('documents')->where('category', $from)->update(['category' => $to]);
        }
    }
};
