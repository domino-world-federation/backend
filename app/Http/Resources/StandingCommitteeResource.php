<?php

namespace App\Http\Resources;

use App\Support\Media\StoredFile;
use Illuminate\Http\Request;

/**
 * `StandingCommittee` — komite tetap berikut tiga pil tanggung jawabnya.
 */
class StandingCommitteeResource extends PublicResource
{
    /** @return array<string, mixed> */
    protected function payload(Request $request): array
    {
        return [
            'id' => $this->idString(),
            'name' => $this->name,
            'remit' => $this->remit ?? [],
            'iconUrl' => StoredFile::url($this->icon_path),
        ];
    }
}
