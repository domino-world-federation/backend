<?php

namespace App\Http\Resources;

use App\Support\Media\StoredFile;
use Illuminate\Http\Request;

/**
 * `Champion` — kartu Champions Hall.
 *
 * `portraitUrl` opsional dan sering kosong, dan itu disengaja: kartu tanpa
 * potret jatuh ke panel gradien alih-alih menempelkan wajah seseorang pada
 * gelar yang tidak pernah ada (R16 di PRD situs publik).
 */
class ChampionResource extends PublicResource
{
    /** @return array<string, mixed> */
    protected function payload(Request $request): array
    {
        return [
            'id' => $this->idString(),
            'event' => $this->event,
            'name' => $this->name,
            'portraitUrl' => StoredFile::url($this->portrait_path),
            'portraitAlt' => $this->portrait_alt,
        ];
    }
}
