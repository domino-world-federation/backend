<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

/**
 * `press_releases` → `documents`.
 *
 * Desain (`369:5236`) menamai layarnya "Document List" dan menaruh "Press
 * Releases" sebagai salah satu NILAI kolom Category (`478:5386`) — jadi
 * modulnya memang Documents, dan Press Releases cuma kategorinya. Layar dan
 * labelnya sudah ikut lebih dulu; migrasi ini menyusulkan tabel, izin, dan
 * jejak auditnya.
 *
 * ── Izin DIGANTI NAMA, bukan dibuat ulang. ──
 *
 * `AccessSeeder` akan membuat `documents.*` sendiri, tapi baris `permissions`
 * yang baru punya `id` baru — dan `role_has_permissions` menunjuk `id`, bukan
 * nama. Membuat ulang berarti setiap peran KUSTOM kehilangan aksesnya ke modul
 * ini sampai seseorang mencentangnya lagi satu per satu, tanpa satu pun galat
 * yang memberi tahu. Mengganti namanya membuat seluruh penugasan tetap utuh.
 *
 * ── Jejak audit ikut dipindahkan. ──
 *
 * `log_name` dan `subject_type` merekam nama kelas lama. Membiarkannya berarti
 * entri lama tidak bisa lagi menemukan barisnya (subjeknya "hilang") dan
 * penyaring modul di layar Audit Log memperlihatkan dua modul untuk satu hal.
 *
 * Yang TIDAK dipindahkan: berkas di storage. Baris lama menyimpan path
 * `press-releases/…` dan file-nya benar-benar ada di sana; unggahan baru masuk
 * ke `documents/`. Memindahkan file di dalam migrasi berarti operasi disk yang
 * tidak bisa di-rollback bersama transaksinya — dan path disimpan per baris,
 * jadi keduanya tetap terbuka dengan benar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('press_releases', 'documents');

        DB::table('permissions')
            ->where('name', 'like', 'press-releases.%')
            ->update(['name' => DB::raw("replace(name, 'press-releases.', 'documents.')")]);

        DB::table('activity_log')->where('log_name', 'press-release')->update(['log_name' => 'document']);
        DB::table('activity_log')
            ->where('subject_type', 'App\\Models\\PressRelease')
            ->update(['subject_type' => 'App\\Models\\Document']);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::rename('documents', 'press_releases');

        DB::table('permissions')
            ->where('name', 'like', 'documents.%')
            ->update(['name' => DB::raw("replace(name, 'documents.', 'press-releases.')")]);

        DB::table('activity_log')->where('log_name', 'document')->update(['log_name' => 'press-release']);
        DB::table('activity_log')
            ->where('subject_type', 'App\\Models\\Document')
            ->update(['subject_type' => 'App\\Models\\PressRelease']);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
