<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

/**
 * `SubCommittee` — nama dan tujuan, dan itu memang semuanya.
 */
class SubCommitteeResource extends PublicResource
{
    /** @return array<string, mixed> */
    protected function payload(Request $request): array
    {
        return [
            'id' => $this->idString(),
            'name' => $this->name,
            'href' => $this->href,
        ];
    }
}
