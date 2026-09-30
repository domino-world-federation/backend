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
 * **Satu daftar untuk dua tempat** — roda di beranda dan hero
 * `/federation-members` — sejak 2026-09-30. Dulu dua daftar dibedakan
 * `scope`, dan keduanya berbeda angka di prod (5 benua di beranda, 6 di halaman
 * anggota) karena yang satu diperbarui dan yang lain terlupa. Yang tayang
 * sekarang hanya `SCOPE_HOME`; migrasi `unify_federation_stats` memilih daftar
 * yang paling baru disunting dan mengarsipkan yang lain (`SCOPE_ARCHIVE`).
 */
#[Fillable(['scope', 'label', 'value', 'is_active', 'position', 'updated_by_id'])]
class FederationStat extends Model
{
    use HasFactory, HasPosition, RecordsActivity, TracksEditor;

    public const SCOPE_HOME = 'home';

    /** Lingkup lama, dipertahankan untuk migrasi dan factory. Tidak tayang. */
    public const SCOPE_MEMBERS = 'members';

    /** Daftar yang kalah saat penyatuan — disimpan, tidak dibaca siapa pun. */
    public const SCOPE_ARCHIVE = 'archive';

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
