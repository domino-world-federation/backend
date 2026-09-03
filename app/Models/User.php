<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Concerns\RecordsActivity;
use App\Support\Access;
use App\Support\Security\TwoFactor;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Traits\HasRoles;

// `two_factor_enabled` WAJIB ada di sini: tanpanya `fill()` membuangnya tanpa
// suara, dan admin yang mematikan 2FA seseorang akan melihat "tersimpan"
// sementara tidak ada yang berubah. `two_factor_secret` sengaja TIDAK ikut —
// ia hanya boleh ditulis lewat `forceFill()` di alur pendaftaran.
#[Fillable([
    'name', 'email', 'password', 'avatar_path', 'locale', 'two_factor_enabled',
    'is_active', 'member_federation_id', 'last_login_at',
])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable, RecordsActivity;

    public function isSuperAdmin(): bool
    {
        return $this->hasRole(Access::SUPER_ADMIN);
    }

    /** Sudah memindai QR dan membuktikannya dengan satu kode yang benar. */
    public function hasConfirmedTwoFactor(): bool
    {
        return filled($this->two_factor_secret) && $this->two_factor_confirmed_at !== null;
    }

    /** 2FA berlaku untuk akun ini — sakelar global DAN sakelar per pengguna. */
    public function requiresTwoFactor(): bool
    {
        return TwoFactor::isEnabled() && $this->two_factor_enabled;
    }

    /**
     * Pakai satu kode pemulihan. Mengembalikan false kalau kodenya tidak ada.
     *
     * Kode yang terpakai LANGSUNG dibuang dan disimpan sebelum apa pun yang
     * lain terjadi — kode pemulihan sekali pakai, dan menundanya sampai akhir
     * request berarti dua permintaan bersamaan bisa memakai kode yang sama.
     */
    public function consumeRecoveryCode(string $code): bool
    {
        $codes = $this->two_factor_recovery_codes ?? [];
        $normalised = trim(strtolower($code));

        $index = array_search($normalised, array_map('strtolower', $codes), strict: true);

        if ($index === false) {
            return false;
        }

        unset($codes[$index]);
        $this->forceFill(['two_factor_recovery_codes' => array_values($codes)])->save();

        return true;
    }

    /** Lingkup federasi akun ini — field "Federation Scope" (`529:9693`). */
    public function federation(): BelongsTo
    {
        return $this->belongsTo(MemberFederation::class, 'member_federation_id');
    }

    /** @return HasMany<AdminInvitation, $this> */
    public function invitations(): HasMany
    {
        return $this->hasMany(AdminInvitation::class);
    }

    /**
     * Kolom "MFA Status" di `528:8821` — "Enrolled" atau "Setup Required".
     *
     * Ia BUKAN `two_factor_enabled`. Yang itu menjawab "apakah akun ini dituntut
     * 2FA"; yang ini menjawab "apakah orangnya sudah benar-benar memindai QR
     * dan membuktikannya". Sebuah akun bisa dituntut 2FA dan belum mendaftar —
     * dan justru baris itulah yang perlu terlihat di daftar.
     */
    public function getMfaStatusAttribute(): string
    {
        return $this->hasConfirmedTwoFactor() ? 'enrolled' : 'setup_required';
    }

    /**
     * Akun yang belum pernah menerima undangannya.
     *
     * Sandinya masih kosong, jadi ia tidak bisa login sama sekali. Daftar admin
     * mencetaknya sebagai "First login pending" (`528:8909`).
     */
    public function isPendingInvitation(): bool
    {
        return blank($this->password);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            // Terenkripsi at-rest: keduanya setara kunci masuk.
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
            'two_factor_enabled' => 'boolean',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    /**
     * URL avatar, atau `null` kalau belum ada.
     *
     * `null` sengaja tidak diganti gambar placeholder di sini: sidebar yang
     * memutuskan apa yang digambar di tempat kosong itu (inisial nama), dan
     * URL bohongan dari server akan membuatnya mustahil membedakan
     * "belum unggah" dari "file hilang".
     */
    protected function avatarUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->avatar_path === null
            ? null
            : Storage::disk('public')->url($this->avatar_path));
    }
}
