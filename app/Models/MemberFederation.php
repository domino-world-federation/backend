<?php

namespace App\Models;

use App\Models\Concerns\HasPosition;
use App\Models\Concerns\RecordsActivity;
use App\Models\Concerns\TracksEditor;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Federasi anggota — register badan nasional yang diakui DWF.
 *
 * Bentuknya mengikuti kontrak `MemberFederation` di
 * `../../landing-page-nuxt/app/lib/api/types.ts`: sebelas field, sembilan di
 * antaranya opsional. Yang terakhir itu kontrak, bukan kelalaian — sebuah badan
 * bisa diakui jauh sebelum ia menyetorkan nama presiden atau nomor telepon, dan
 * kartunya mencetak baris yang ada alih-alih baris kosong.
 *
 * Dipakai dua tempat: direktori publik `/federation-members`, dan pemilih
 * "Federation Scope" di layar Add Admin (`529:9693`).
 */
#[Fillable([
    'name', 'country', 'flag_path', 'tier', 'joined_year', 'president',
    'headquarters', 'email', 'phone', 'website_url',
    'is_active', 'position', 'updated_by_id',
])]
class MemberFederation extends Model
{
    use HasFactory, HasPosition, RecordsActivity, TracksEditor;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'position' => 'integer',
            'joined_year' => 'integer',
        ];
    }

    /** Label tingkat keanggotaan yang bisa dibaca manusia. */
    public function getTierLabelAttribute(): ?string
    {
        return $this->tier === null
            ? null
            : (config('dwf.membership_tiers')[$this->tier] ?? $this->tier);
    }

    /** @return HasMany<User, $this> */
    public function admins(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** "Japan Federation — Japan", seperti yang dicetak pemilihnya. */
    public function getLabelAttribute(): string
    {
        return "{$this->name} — {$this->country}";
    }
}
