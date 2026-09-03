<?php

namespace App\Support\Media;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Menyimpan dan mengganti berkas unggahan.
 *
 * Ada supaya satu aturan berlaku di semua modul: berkas lama dihapus HANYA
 * setelah yang baru berhasil ditulis. Urutan sebaliknya — hapus dulu, simpan
 * kemudian — menghasilkan baris tanpa berkas kalau penyimpanannya gagal, dan
 * itu tidak bisa dipulihkan.
 *
 * DUA DISK, dan yang membedakannya bukan jenis berkasnya melainkan SIAPA YANG
 * BOLEH MEMBACANYA:
 *
 *   - `public` — media yang memang untuk dilihat: gambar berita, galeri, logo,
 *     bendera, foto orang. Disajikan langsung oleh web server lewat symlink
 *     `public/storage`, tanpa PHP ikut campur. Itu yang membuatnya cepat.
 *   - `local` — berkas yang tunduk pada sakelar Visibility. Disimpan di
 *     `storage/app/private`, TIDAK di-symlink, dan cuma bisa keluar lewat
 *     `MediaController` yang memeriksa dulu apakah barisnya sudah tayang.
 *
 * Nama berkas selalu acak (`store()` memakai `hashName()`), tapi nama acak
 * BUKAN kontrol akses: ia menahan tebakan, bukan tautan yang sudah beredar.
 * Yang menahan itu disk kedua.
 */
final class StoredFile
{
    public static function put(
        UploadedFile $file,
        string $folder,
        ?string $replacing = null,
        string $disk = 'public',
    ): string {
        $path = $file->store($folder, $disk);

        if ($replacing !== null && $replacing !== $path) {
            self::forget($replacing, $disk);
        }

        return $path;
    }

    public static function forget(?string $path, string $disk = 'public'): void
    {
        if ($path === null || $path === '') {
            return;
        }

        Storage::disk($disk)->delete($path);
    }

    /** URL publik, atau null kalau memang tidak ada berkasnya. */
    public static function url(?string $path): ?string
    {
        return $path === null || $path === '' ? null : Storage::disk('public')->url($path);
    }
}
