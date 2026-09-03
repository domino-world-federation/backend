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
 * Satu sub-komite (`114:3667`) — nama dan tujuan, dan itu memang semuanya.
 *
 * `href` nullable karena halaman yang ditunjuknya belum ada; panah yang
 * berujung 404 lebih buruk daripada kartu tanpa panah.
 */
#[Fillable(['name', 'href', 'is_active', 'position', 'updated_by_id'])]
class SubCommittee extends Model
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
