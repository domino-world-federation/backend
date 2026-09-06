<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Storage;

/**
 * Berkas dokumen pindah dari disk privat ke media publik.
 *
 * Kebalikan dari `move_documents_to_the_private_disk`, dan bukan karena yang itu
 * keliru. Migrasi itu benar untuk premis yang dipegangnya: dokumen bisa ditarik
 * kembali, jadi berkasnya harus punya satu pintu berpenjaga. Premisnya yang
 * berubah — pemilik repo memutuskan seluruh dokumen federasi memang untuk
 * dibagikan, jadi penjagaan itu tidak membeli apa pun dan hanya menambah cara
 * untuk salah: disk kedua, izin direktori tersendiri, dan satu host lagi yang
 * harus disetel benar. Ketiganya sudah menghasilkan kegagalan nyata dalam dua
 * hari.
 *
 * Yang dilepas dicatat di sini supaya tidak ada yang mengira ia gratis:
 * menurunkan sebuah dokumen menyembunyikan BARISNYA dari situs, bukan berkasnya.
 * Yang sudah memegang tautannya tetap bisa mengunduh, dan cache CDN bisa
 * menyimpannya lama sesudahnya.
 *
 * ── Kalau kedua disk menunjuk folder yang SAMA ──
 *
 * Itu keadaan produksi saat migrasi ini ditulis: `MEDIA_ROOT` dan
 * `MEDIA_PRIVATE_ROOT` sama-sama `.../dwf-media`, jadi berkasnya sudah berada di
 * tujuan. Menyalin berkas ke atas dirinya sendiri lalu menghapus sumbernya akan
 * MENGHAPUS berkasnya. Karena itu path sungguhannya dibandingkan lebih dulu, dan
 * kalau sama migrasinya tidak menyentuh apa pun.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->move(from: 'local', to: 'public');
    }

    /**
     * Mengembalikan berkasnya ke disk privat.
     *
     * Rollback TIDAK menutup kembali apa yang sudah terlanjur terbagi: URL yang
     * sudah beredar tetap beredar, dan salinan di cache CDN tetap di sana. Yang
     * dikembalikannya letak berkasnya, bukan kerahasiaannya.
     */
    public function down(): void
    {
        $this->move(from: 'public', to: 'local');
    }

    private function move(string $from, string $to): void
    {
        $source = Storage::disk($from);
        $target = Storage::disk($to);

        // Path yang SUDAH DIRESOLVE, bukan nilai config: keduanya bisa ditulis
        // berbeda di `.env` dan tetap menunjuk direktori yang sama.
        $sourceRoot = realpath((string) config("filesystems.disks.{$from}.root"));
        $targetRoot = realpath((string) config("filesystems.disks.{$to}.root"));

        if ($sourceRoot !== false && $sourceRoot === $targetRoot) {
            return;
        }

        foreach ($source->files('documents') as $path) {
            if ($target->exists($path)) {
                $source->delete($path);

                continue;
            }

            // Disalin dulu, baru yang lama dihapus. Urutan sebaliknya
            // menghasilkan baris tanpa berkas kalau penyalinannya gagal di
            // tengah — aturan yang sama dengan `StoredFile::put()`.
            if ($target->put($path, $source->get($path)) !== false) {
                $source->delete($path);
            }
        }
    }
};
