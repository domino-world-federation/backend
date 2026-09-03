<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

/**
 * `GalleryAlbum` — satu event beserta gambarnya.
 *
 * `heldOn` ISO, tidak diformat: ia tanggal EVENTNYA, bukan tanggal berkasnya
 * difilekan, dan dua halaman menampilkannya berbeda.
 */
class GalleryAlbumResource extends PublicResource
{
    /** @return array<string, mixed> */
    protected function payload(Request $request): array
    {
        return [
            'id' => $this->idString(),
            'slug' => $this->slug,
            'title' => $this->name,
            'heldOn' => $this->held_on?->toIso8601String(),
            'items' => GalleryItemResource::bare($this->items),
        ];
    }
}
