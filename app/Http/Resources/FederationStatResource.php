<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

/**
 * `FederationStat` — label dan nilai.
 *
 * `value` string karena "120+" dan "1.420" harus muat di slot yang sama
 * dengan "57".
 */
class FederationStatResource extends PublicResource
{
    /** @return array<string, mixed> */
    protected function payload(Request $request): array
    {
        return [
            'id' => $this->idString(),
            'label' => $this->label,
            'value' => $this->value,
        ];
    }
}
