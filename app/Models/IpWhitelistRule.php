<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use App\Models\Concerns\TracksEditor;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Permission\Models\Role;

/**
 * Satu aturan IP yang boleh membuka backoffice — Figma `527:7038`.
 *
 * Tidak ada kolom rahasia di sini, jadi `$hidden` sengaja dibiarkan kosong:
 * seluruh isi baris ini memang boleh muncul di jejak audit `RecordsActivity`,
 * dan justru itu yang dicari kalau suatu saat ada yang bertanya siapa membuka
 * akses dari mana.
 */
#[Fillable([
    'name', 'ip_range', 'scope', 'role_id', 'user_id',
    'validity', 'expires_at', 'notes', 'is_active',
    'created_by_id', 'updated_by_id',
])]
class IpWhitelistRule extends Model
{
    use HasFactory, RecordsActivity, TracksEditor;

    public const SCOPE_ALL = 'all_admins';

    public const SCOPE_ROLE = 'role';

    public const SCOPE_USER = 'user';

    public const SCOPES = [self::SCOPE_ALL, self::SCOPE_ROLE, self::SCOPE_USER];

    public const VALIDITY_PERMANENT = 'permanent';

    public const VALIDITY_TEMPORARY = 'temporary';

    public const VALIDITIES = [self::VALIDITY_PERMANENT, self::VALIDITY_TEMPORARY];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'expires_at' => 'datetime',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    /**
     * Aturan yang benar-benar DITEGAKKAN sekarang.
     *
     * Kedaluwarsa diperiksa di sini, bukan lewat job terjadwal yang mematikan
     * `is_active`. Sebuah aturan sementara yang lewat tanggalnya harus berhenti
     * berlaku pada detik itu juga — kalau bergantung pada scheduler, jendela
     * antara "kedaluwarsa" dan "job berikutnya jalan" adalah akses yang
     * seharusnya sudah dicabut, dan panjangnya tergantung cron yang tidak
     * kelihatan dari layar mana pun.
     *
     * Karena itu pula desain menyebut Validity dan Status sebagai dua kolom
     * berbeda (`527:7682`, `527:7687`): yang satu kapan ia berakhir, yang lain
     * apakah ia dinyalakan.
     */
    public function scopeEnforceable(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->where(fn (Builder $q) => $q
                ->where('validity', self::VALIDITY_PERMANENT)
                ->orWhere('expires_at', '>', now()));
    }

    /** Sudah lewat tanggalnya — dicetak berbeda di layar daftar. */
    public function getIsExpiredAttribute(): bool
    {
        return $this->validity === self::VALIDITY_TEMPORARY
            && $this->expires_at !== null
            && $this->expires_at->isPast();
    }

    /**
     * Label kolom "Access Scope" seperti yang dicetak desain — "All Admins",
     * nama peran, atau nama orang.
     */
    public function getScopeLabelAttribute(): string
    {
        return match ($this->scope) {
            self::SCOPE_ROLE => $this->role?->name ?? __('backoffice.ip_whitelist.scope_role'),
            self::SCOPE_USER => $this->user?->name ?? __('backoffice.ip_whitelist.scope_user'),
            default => __('backoffice.ip_whitelist.scope_all'),
        };
    }
}
