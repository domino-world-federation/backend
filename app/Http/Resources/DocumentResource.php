<?php

namespace App\Http\Resources;

use App\Support\Media\StoredFile;
use Illuminate\Http\Request;

/**
 * `ResourceDocument` — satu dokumen.
 *
 * `fileSize` sudah TERFORMAT ("2.4 MB"), sesuai §5.1: satuannya milik API,
 * dan halaman yang menghitungnya sendiri akan memilih satuan yang berbeda
 * dari halaman sebelahnya.
 *
 * `publishedAt` justru TIDAK diformat — dua halaman menampilkannya dengan
 * format berbeda, dan memilihkannya di sini mengunci keduanya.
 */
class DocumentResource extends PublicResource
{
    /** @return array<string, mixed> */
    protected function payload(Request $request): array
    {
        return [
            'id' => $this->idString(),
            'title' => $this->title,
            'category' => $this->category,
            'publishedAt' => $this->published_at?->toIso8601String(),
            /*
             * URL statis di host media, sama seperti gambar.
             *
             * Sampai 2026-09-06 ini `route('media.document')` — sebuah rute PHP
             * yang memeriksa sakelar Visibility pada tiap permintaan, karena
             * dokumen dianggap bisa ditarik kembali. Pemilik repo memutuskan
             * seluruh dokumen federasi memang untuk dibagikan, jadi penjagaan
             * itu tidak membeli apa pun dan hanya menambah cara untuk salah —
             * lihat migrasi `publish_document_files`.
             *
             * Konsekuensinya tercatat di layar Documents: sakelar Visibility
             * menyembunyikan BARISNYA dari situs, bukan berkasnya.
             */
            'fileUrl' => StoredFile::url($this->file_path),
            'fileType' => 'pdf',
            'fileSize' => $this->file_size_label,
        ];
    }
}
