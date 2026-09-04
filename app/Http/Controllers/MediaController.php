<?php

namespace App\Http\Controllers;

use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Satu-satunya pintu keluar untuk berkas yang tunduk pada sakelar Visibility.
 *
 * Sebelum 2026-09-03 dokumen tinggal di disk `public` dan disajikan langsung
 * web server lewat symlink — jadi mengubah sebuah dokumen jadi draft atau
 * unpublished **tidak menurunkan berkasnya**. Yang diatur sakelarnya cuma
 * daftarnya. Nama berkas memang acak, tapi nama acak menahan TEBAKAN, bukan
 * tautan yang sudah beredar: sekali sebuah URL keluar, ia berlaku selamanya.
 *
 * Sengaja BUKAN URL bertanda tangan. Tanda tangan yang diterbitkan saat sebuah
 * dokumen masih tayang tetap sah setelah dokumennya diturunkan, sampai
 * kedaluwarsanya sendiri — jadi ia memindahkan pemeriksaan ke masa lalu.
 * Di sini keadaannya diperiksa pada tiap permintaan.
 */
class MediaController extends Controller
{
    public function document(Request $request, Document $document): StreamedResponse
    {
        $isLive = $document->newQuery()->live()->whereKey($document->getKey())->exists();

        /*
         * Yang belum tayang bukan 403 melainkan 404.
         *
         * 403 mengakui bahwa berkasnya ADA dan cuma sedang ditahan — untuk
         * dokumen yang belum dirilis, keberadaannya sendiri kadang yang
         * rahasia. Admin yang memang boleh melihat modulnya tetap bisa
         * mengunduhnya, karena ia perlu memeriksa isinya sebelum menayangkan.
         */
        abort_unless($isLive || $request->user()?->can('documents.view'), 404);

        abort_unless(Storage::disk('local')->exists($document->file_path), 404);

        /*
         * TIDAK BOLEH di-cache — dan ini disetel dengan sengaja, bukan
         * dibiarkan kebetulan benar.
         *
         * Sebelum baris ini, headernya `no-cache, private` yang datang dari
         * middleware sesi: benar hasilnya, tapi karena alasan yang tidak ada
         * hubungannya, dan hilang begitu route ini pindah ke luar grup `web`.
         *
         * Berkas yang tersimpan di cache tetap terunduh SETELAH dokumennya
         * diturunkan — yang membatalkan seluruh guna pemeriksaan di atas.
         * `no-store` melarang menyimpannya sama sekali, termasuk oleh proxy
         * perantara.
         */
        return Storage::disk('local')
            ->download($document->file_path, $document->downloadName(), [
                'Cache-Control' => 'private, no-store, max-age=0',
            ]);
    }
}
