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
 * Satu angka statistik federasi.
 *
 * Satu tabel untuk dua tempat — roda di beranda dan blok keanggotaan di
 * `/federation-members` — dibedakan `scope`. Alasannya di migrasinya.
 */
#[Fillable(['scope', 'label', 'value', 'is_active', 'position', 'updated_by_id'])]
class FederationStat extends Model
{
    use HasFactory, HasPosition, RecordsActivity, TracksEditor;

    public const SCOPE_HOME = 'home';

    public const SCOPE_MEMBERS = 'members';

    public const SCOPES = [self::SCOPE_HOME, self::SCOPE_MEMBERS];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'position' => 'integer'];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
