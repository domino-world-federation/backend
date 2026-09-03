<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu baris jadwal — kelompok berulang `596:11361`.
 *
 * "Admin can add, remove, and reorder schedule items", jadi urutannya kolom
 * sendiri dan bukan turunan tanggal: dua sesi di hari yang sama punya urutan
 * yang ditentukan orang, bukan jam mulainya.
 *
 * Alasan tidak memakai `RecordsActivity` sama dengan `TournamentOfficial`.
 */
#[Fillable(['tournament_id', 'held_on', 'starts_at', 'activity', 'area', 'position'])]
class TournamentScheduleEntry extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return ['held_on' => 'date', 'position' => 'integer'];
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    /** "Aug 28. 09:00" — sudah diformat, seperti yang diminta kontrak frontend. */
    public function getTimeLabelAttribute(): string
    {
        return $this->held_on->format('M j').'. '.substr((string) $this->starts_at, 0, 5);
    }
}
