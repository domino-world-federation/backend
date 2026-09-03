<?php

namespace App\Models;

use App\Models\Concerns\HasPosition;
use App\Models\Concerns\RecordsActivity;
use App\Models\Concerns\TracksEditor;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Satu baris tabel hasil Olympic.
 *
 * `year` string: kontraknya menyebutnya label, dan halaman itu tidak pernah
 * menghitung apa pun dengannya.
 */
#[Fillable(['year', 'event', 'category', 'winners', 'federation', 'is_active', 'position', 'updated_by_id'])]
class OlympicResult extends Model
{
    use HasFactory, HasPosition, RecordsActivity, TracksEditor;

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'position' => 'integer'];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
