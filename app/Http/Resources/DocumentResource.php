<?php

namespace App\Http\Resources;

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
            // Lewat route berpenjaga, bukan symlink: berkas dokumen tunduk
            // pada sakelar Visibility, dan symlink tidak pernah memeriksanya.
            'fileUrl' => route('media.document', $this->resource),
            'fileType' => 'pdf',
            'fileSize' => $this->file_size_label,
        ];
    }
}
