<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'email', 'country', 'topic', 'subject', 'message', 'read_at'])]
class ContactMessage extends Model
{
    use HasFactory, RecordsActivity;

    /**
     * Topik dari layar Contact Messages (`258:8271`), ditambah satu.
     *
     * "Tournament Support" TIDAK ada di layar itu, tapi ada di formulir situs
     * publik (`content/contact/index.ts`) — dan yang menentukan daftar ini
     * adalah apa yang bisa dikirim orang, bukan apa yang digambar penyaringnya.
     * Tanpa baris ini setiap pesan bertopik itu ditolak 422 oleh endpoint, dan
     * yang terlihat pengirimnya cuma formulir yang gagal tanpa sebab.
     */
    public const TOPICS = [
        'Media Requests',
        'General Enquiries',
        'Partnerships',
        'Membership Information',
        'Tournament Support',
    ];

    /**
     * Menyamakan ejaan topik yang datang dari luar.
     *
     * Situs publik mengetiknya dalam sentence case ("General enquiries"),
     * layar CMS dalam title case ("General Enquiries"). Keduanya topik yang
     * SAMA, dan menyimpan dua ejaan berarti penyaring inbox diam-diam
     * kehilangan separuh pesannya. Yang tidak dikenali mengembalikan `null`,
     * yang lalu gagal di aturan `required` pemanggilnya.
     */
    public static function canonicalTopic(?string $topic): ?string
    {
        if ($topic === null) {
            return null;
        }

        foreach (self::TOPICS as $known) {
            if (mb_strtolower(trim($topic)) === mb_strtolower($known)) {
                return $known;
            }
        }

        return null;
    }

    protected function casts(): array
    {
        return ['read_at' => 'datetime'];
    }

    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }

    public function markRead(): void
    {
        if ($this->read_at === null) {
            $this->forceFill(['read_at' => now()])->save();
        }
    }
}
