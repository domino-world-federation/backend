<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu ofisial atau wasit — kelompok berulang `596:11241`.
 *
 * Tidak memakai `RecordsActivity`: yang dicatat jejak audit adalah TURNAMENNYA.
 * Menambah satu wasit ke daftar bukan peristiwa yang berdiri sendiri, dan
 * mencatatnya begitu membuat satu penyuntingan formulir menghasilkan belasan
 * baris audit yang tidak menjawab pertanyaan siapa pun.
 */
#[Fillable(['tournament_id', 'photo_path', 'name', 'role', 'country', 'position'])]
class TournamentOfficial extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return ['position' => 'integer'];
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }
}
