<?php

namespace App\Http\Resources;

use App\Support\Media\StoredFile;
use Illuminate\Http\Request;

/**
 * `Partner` — satu logo di strip beranda.
 *
 * `websiteUrl` dihilangkan selama kosong: marknya lalu dirender sebagai
 * gambar biasa, bukan tautan mati (pertanyaan terbuka #6).
 */
class PartnerResource extends PublicResource
{
    /** @return array<string, mixed> */
    protected function payload(Request $request): array
    {
        return [
            'id' => $this->idString(),
            'name' => $this->name,
            'logoUrl' => StoredFile::url($this->logo_path),
            'websiteUrl' => $this->website_url,
        ];
    }
}
