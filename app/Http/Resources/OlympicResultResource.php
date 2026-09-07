<?php

namespace App\Http\Resources;

use App\Support\Media\StoredFile;
use Illuminate\Http\Request;

/**
 * `OlympicResult` — satu baris tabel hasil.
 *
 * `year` string karena ia label, bukan angka yang dihitung halaman itu.
 *
 * Lima field pertama dicetak tabel di halaman turnamen. Sisanya hanya terpakai
 * saat barisnya dibuka di halaman "More Olympics Results" (`648:30473`), dan
 * semuanya opsional: §5.4 menghilangkan yang kosong, jadi baris lama — yang
 * diisi lewat layar bulk sebelum kolom-kolom ini ada — sampai di situs tanpa
 * field-nya sama sekali dan akordeonnya mencetak fakta yang ada saja.
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

            'eventDate' => $this->event_date,
            'location' => $this->location,
            'format' => $this->format,
            'championPhotoUrl' => StoredFile::url($this->champion_photo_path),
            'championPhotoAlt' => $this->champion_photo_alt,
        ];
    }
}
