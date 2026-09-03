<?php

namespace App\Http\Resources;

use App\Support\Media\StoredFile;
use Illuminate\Http\Request;

/**
 * `BoardMember` — anggota Executive Board.
 *
 * `name` boleh memuat baris baru; `BoardCard` merendernya dua baris.
 */
class BoardMemberResource extends PublicResource
{
    /** @return array<string, mixed> */
    protected function payload(Request $request): array
    {
        return [
            'id' => $this->idString(),
            'name' => $this->name,
            'role' => $this->role,
            'portraitUrl' => StoredFile::url($this->portrait_path),
            'portraitAlt' => $this->portrait_alt,
        ];
    }
}
