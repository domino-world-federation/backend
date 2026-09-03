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
 * Satu kartu Champions Hall (`381:17645`).
 *
 * Potretnya OPSIONAL dengan sengaja — lihat R16 di PRD situs publik. Kartu
 * tanpa potret jatuh ke panel gradien, jadi bloknya tetap lengkap tanpa
 * menempelkan wajah seseorang pada gelar yang tidak pernah ada.
 */
#[Fillable(['event', 'name', 'portrait_path', 'portrait_alt', 'is_active', 'position', 'updated_by_id'])]
class Champion extends Model
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
