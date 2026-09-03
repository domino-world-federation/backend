<?php

namespace App\Http\Resources;

use App\Support\Media\StoredFile;
use Illuminate\Http\Request;

/**
 * `MemberFederation` — satu badan nasional di direktori.
 *
 * Sembilan dari sebelas field opsional, dan itu KONTRAK: kartunya mencetak
 * baris yang ada dan membuang yang tidak, jadi baris setengah terisi
 * menghasilkan kartu lebih pendek — bukan kartu penuh ruang kosong.
 */
class MemberFederationResource extends PublicResource
{
    /** @return array<string, mixed> */
    protected function payload(Request $request): array
    {
        return [
            'id' => $this->idString(),
            'name' => $this->name,
            'country' => $this->country,
            'flagUrl' => StoredFile::url($this->flag_path),
            'tierId' => $this->tier,
            'joinedYear' => $this->joined_year,
            'president' => $this->president,
            'headquarters' => $this->headquarters,
            'email' => $this->email,
            'phone' => $this->phone,
            'websiteUrl' => $this->website_url,
        ];
    }
}
