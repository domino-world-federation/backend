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
 * Satu anggota Executive Board (`112:3590`).
 *
 * `name` boleh memuat baris baru — kartunya merender nama dua baris
 * kalau ada `\n` di dalamnya, dan itu keputusan orang yang mengetiknya.
 */
#[Fillable(['name', 'role', 'portrait_path', 'portrait_alt', 'is_active', 'position', 'updated_by_id'])]
class BoardMember extends Model
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
