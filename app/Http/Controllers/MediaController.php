<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Support\Media\StoredFile;
use Illuminate\Http\RedirectResponse;

/**
 * Peninggalan: URL unduhan dokumen yang lama.
 *
 * Sampai 2026-09-06 rute ini yang MENYAJIKAN berkasnya, dari disk privat, dan
 * memeriksa sakelar Visibility pada tiap permintaan. Dokumen federasi ternyata
 * seluruhnya memang untuk dibagikan — keputusan pemilik repo — jadi berkasnya
 * pindah ke media publik dan disajikan nginx langsung, seperti gambar.
 *
 * Rutenya TIDAK dihapus, dan itu bukan kehati-hatian yang berlebihan: URL
 * `/media/documents/{id}` sudah tercetak di situs publik, dikirim `/api/v1/resources`,
 * dan mungkin sudah tersimpan di bookmark orang. Menghapusnya berarti tautan
 * mati yang tidak bisa ditarik kembali. Ia mengalihkan ke URL barunya.
 *
 * `301`, bukan `302`: perpindahan ini permanen, dan status yang benar membuat
 * mesin pencari dan cache memperbarui catatannya alih-alih terus menanyakan
 * rute ini selamanya.
 *
 * **Tidak ada lagi pemeriksaan status tayang di sini**, dan tidak boleh
 * berpura-pura ada: berkasnya bisa diambil langsung dari host media tanpa
 * melewati rute ini sama sekali. Menaruh `abort_unless` di sini hanya akan
 * membuat kode ini terbaca seolah menjaga sesuatu yang sudah tidak dijaga.
 */
class MediaController extends Controller
{
    public function document(Document $document): RedirectResponse
    {
        return redirect()->away(StoredFile::url($document->file_path) ?? '/', 301);
    }
}
