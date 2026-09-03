<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Storage;

/**
 * Memindahkan berkas dokumen dari disk `public` ke disk privat.
 *
 * Sebelum ini PDF dokumen tinggal di `storage/app/public/documents`, yang
 * disajikan web server langsung lewat symlink — jadi mengubah sebuah dokumen
 * jadi draft atau unpublished TIDAK menurunkan berkasnya. Nama berkasnya acak,
 * tapi nama acak menahan tebakan, bukan tautan yang sudah beredar.
 *
 * Setelah migrasi ini satu-satunya jalan keluar adalah `MediaController`, yang
 * memeriksa keadaan dokumennya pada tiap permintaan.
 *
 * Bytenya DISALIN dulu, baru yang lama dihapus. Urutan sebaliknya menghasilkan
 * baris tanpa berkas kalau penyalinannya gagal di tengah — aturan yang sama
 * dengan `StoredFile::put()`.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->move(from: 'public', to: 'local');
    }

    public function down(): void
    {
        $this->move(from: 'local', to: 'public');
    }

    private function move(string $from, string $to): void
    {
        $source = Storage::disk($from);
        $target = Storage::disk($to);

        foreach ($source->files('documents') as $path) {
            if ($target->exists($path)) {
                $source->delete($path);

                continue;
            }

            if ($target->put($path, $source->get($path)) !== false) {
                $source->delete($path);
            }
        }
    }
};
