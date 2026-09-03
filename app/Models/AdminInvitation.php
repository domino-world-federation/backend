<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Satu undangan admin — panel "Invitation Flow" (`529:9714`).
 *
 * Model ini SENGAJA tidak memakai `RecordsActivity`. Undangan sudah jadi
 * catatannya sendiri (siapa mengundang siapa, kapan, diterima atau dicabut),
 * dan mencatatnya lagi di jejak audit berarti `token_hash` ikut tersalin ke
 * tabel kedua — memperbanyak tempat rahasia itu berada tanpa menambah satu pun
 * fakta baru. Peristiwa yang MEMANG masuk audit (undangan dikirim, diterima,
 * dicabut) dicatat controller sebagai entri bernama.
 */
#[Fillable(['user_id', 'token_hash', 'expires_at', 'accepted_at', 'revoked_at', 'created_by_id'])]
#[Hidden(['token_hash'])]
class AdminInvitation extends Model
{
    use HasFactory;

    /** 72 jam, langsung dari kalimat desain. */
    public const TTL_HOURS = 72;

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
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
     * Menerbitkan undangan baru dan mengembalikan token MENTAHNYA.
     *
     * Token mentah tidak pernah disimpan — hanya dikembalikan sekali, untuk
     * dirangkai jadi tautan. Kalau pemanggil membuangnya, satu-satunya jalan
     * adalah menerbitkan yang baru, dan itu memang perilaku yang benar.
     *
     * @return array{0: self, 1: string}
     */
    public static function issue(User $user, ?int $createdById = null): array
    {
        // Undangan lama untuk orang yang sama dicabut lebih dulu. Dua tautan
        // hidup sekaligus berarti "Revoke" hanya mematikan salah satunya, dan
        // yang tertinggal tidak terlihat di layar mana pun.
        self::query()->where('user_id', $user->id)->pending()->update(['revoked_at' => now()]);

        $token = Str::random(64);

        $invitation = self::create([
            'user_id' => $user->id,
            'token_hash' => self::hash($token),
            'expires_at' => now()->addHours(self::TTL_HOURS),
            'created_by_id' => $createdById,
        ]);

        return [$invitation, $token];
    }

    /**
     * SHA-256, bukan bcrypt.
     *
     * Bcrypt disengaja lambat supaya sandi manusia mahal ditebak. Token ini 64
     * karakter acak dari `Str::random()` — ruangnya sudah di luar jangkauan
     * tebakan, jadi yang tersisa dari kelambatan bcrypt cuma biayanya, dan ia
     * ditanggung setiap kali seseorang membuka tautannya. Ini pilihan yang sama
     * dengan token reset sandi bawaan Laravel.
     */
    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    /** Belum diterima, belum dicabut, belum kedaluwarsa. */
    public function scopePending(Builder $query): Builder
    {
        return $query
            ->whereNull('accepted_at')
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now());
    }

    public function isPending(): bool
    {
        return $this->accepted_at === null
            && $this->revoked_at === null
            && $this->expires_at->isFuture();
    }

    /**
     * Keadaan yang dicetak daftar admin — satu kata, bukan tiga boolean.
     *
     * Urutannya berarti: "revoked" menang atas "expired" karena pencabutan
     * adalah keputusan orang, sedangkan kedaluwarsa cuma waktu yang lewat.
     */
    public function getStateAttribute(): string
    {
        if ($this->accepted_at !== null) {
            return 'accepted';
        }

        if ($this->revoked_at !== null) {
            return 'revoked';
        }

        return $this->expires_at->isPast() ? 'expired' : 'pending';
    }
}
