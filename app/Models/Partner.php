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
 * Satu logo di strip partner beranda.
 *
 * `website_url` nullable — alamat tujuan delapan logo belum diketahui
 * (pertanyaan terbuka #6 di PRD situs publik), jadi marknya sengaja bukan
 * tautan sampai ada.
 */
#[Fillable(['name', 'logo_path', 'website_url', 'is_active', 'position', 'updated_by_id'])]
class Partner extends Model
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
