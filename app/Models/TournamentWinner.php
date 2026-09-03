<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu pemenang satu turnamen — blok `517:2180`.
 *
 * Alasan tidak memakai `RecordsActivity` sama dengan `TournamentOfficial`: yang
 * dicatat jejak audit adalah turnamennya, dan satu penyuntingan formulir tidak
 * seharusnya menghasilkan belasan baris audit.
 */
#[Fillable(['tournament_id', 'rank_label', 'names', 'country', 'portrait_paths', 'position'])]
class TournamentWinner extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return ['portrait_paths' => 'array', 'position' => 'integer'];
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }
}
