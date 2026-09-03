<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

/**
 * `OlympicResult` — satu baris tabel hasil.
 *
 * `year` string karena ia label, bukan angka yang dihitung halaman itu.
 */
class OlympicResultResource extends PublicResource
{
    /** @return array<string, mixed> */
    protected function payload(Request $request): array
    {
        return [
            'id' => $this->idString(),
            'year' => $this->year,
            'event' => $this->event,
            'category' => $this->category,
            'winners' => $this->winners,
            'federation' => $this->federation,
        ];
    }
}
