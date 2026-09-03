<?php

namespace App\Http\Resources;

use App\Support\Media\StoredFile;
use Illuminate\Http\Request;

/**
 * `GalleryItem` — satu ubin di kolase.
 *
 * `kind` union tertutup (`photo` | `video`): ia menentukan BENTUK ubinnya, bukan
 * cuma lencananya, jadi anggota tak terduga akan menghasilkan ubin tanpa ukuran.
 * Kolom database memakai `image`/`video`; peta di bawah menerjemahkannya.
 */
class GalleryItemResource extends PublicResource
{
    /** @return array<string, mixed> */
    protected function payload(Request $request): array
    {
        return [
            'id' => $this->idString(),
            // Tidak dicetak di kolase — kolase itu gambar saja — tapi dibacakan
            // pembaca layar. Alt yang kosong jatuh ke nama eventnya.
            'title' => $this->alt ?? $this->event?->name ?? '',
            'imageUrl' => StoredFile::url($this->path),
            'imageAlt' => $this->alt ?? '',
            'kind' => $this->kind === 'video' ? 'video' : 'photo',
        ];
    }
}
