<?php

namespace App\Http\Resources;

use App\Support\Media\StoredFile;
use Illuminate\Http\Request;

/**
 * `HeritageMilestone` — satu langkah di timeline `/about`.
 */
class HeritageMilestoneResource extends PublicResource
{
    /** @return array<string, mixed> */
    protected function payload(Request $request): array
    {
        return [
            'id' => $this->idString(),
            'year' => $this->year,
            'title' => $this->title,
            'summary' => $this->summary,
            'imageUrl' => StoredFile::url($this->image_path),
            'imageAlt' => $this->image_alt,
        ];
    }
}
