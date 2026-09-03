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
 * Satu langkah di timeline sejarah `/about` (`88:1163`).
 *
 * `year` string: penanda di sumbu, bukan angka yang dihitung. "1974" dan
 * "1990s" sama-sama sah.
 */
#[Fillable(['year', 'title', 'summary', 'image_path', 'image_alt', 'is_active', 'position', 'updated_by_id'])]
class HeritageMilestone extends Model
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
