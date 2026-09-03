<?php

namespace App\Http\Controllers;

use App\Support\Media\StoredFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Unggahan gambar dari dalam editor teks kaya.
 *
 * Terpisah dari modulnya (News, FAQ, Legal Pages) dengan sengaja: gambar yang
 * disisipkan di tengah tulisan tidak dimiliki satu baris database mana pun — ia
 * hidup di dalam HTML, dan HTML itu bisa disalin dari satu artikel ke artikel
 * lain. Menitipkannya ke `NewsArticleController` berarti menghapus artikel
 * ikut menghapus gambar yang mungkin sedang dipakai FAQ.
 *
 * Konsekuensinya jujur dan perlu diketahui: berkas di sini TIDAK ikut terhapus
 * saat artikelnya dihapus. Membersihkannya menuntut penelusuran seluruh kolom
 * HTML di seluruh modul; itu pekerjaan penyapu terjadwal, bukan pekerjaan
 * request. Dicatat sebagai pekerjaan terbuka di `docs/PROGRESS.md`.
 */
class EditorImageController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $uploads = config('dwf.uploads');
        $min = $uploads['editor_image_min_dimension'];

        $request->validate([
            'image' => [
                'required',
                'image',
                'mimes:'.implode(',', $uploads['image_mimes']),
                'max:'.$uploads['image_max_kb'],
                // Tanpa rasio: ini ilustrasi di tengah tulisan, bentuknya
                // memang bermacam-macam. Yang dijaga cuma agar bukan ikon
                // 16×16 yang tidak sengaja terseret.
                "dimensions:min_width={$min},min_height={$min}",
            ],
        ]);

        $path = StoredFile::put($request->file('image'), 'editor');

        return response()->json(['url' => StoredFile::url($path)]);
    }
}
