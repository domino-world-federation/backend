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
 * Satu komite tetap (`613:24909`).
 *
 * Berbeda dari `SubCommittee`: yang ini membawa APA yang jadi tanggung
 * jawabnya. `remit` array karena desainnya menggambar tiga pil terpisah.
 */
#[Fillable(['name', 'remit', 'icon_path', 'is_active', 'position', 'updated_by_id'])]
class StandingCommittee extends Model
{
    use HasFactory, HasPosition, RecordsActivity, TracksEditor;

    protected function casts(): array
    {
        return ['remit' => 'array', 'is_active' => 'boolean', 'position' => 'integer'];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
