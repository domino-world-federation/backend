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
     * Topik dari layar Contact Messages (`258:8271`), ditambah dua.
     *
     * Yang menentukan daftar ini adalah apa yang BISA DIKIRIM orang, bukan apa
     * yang digambar penyaringnya. Topik yang bisa dikirim tapi tidak ada di
     * sini ditolak 422 oleh endpoint, dan yang terlihat pengirimnya cuma
     * formulir yang gagal tanpa sebab.
     *
     * "Tournament Support" datang dari formulir Contact di situs publik
     * (`content/contact/index.ts`). "Development Support" datang dari kartu
     * Federation Support Programs di halaman Development (`207:15156`) —
     * pengajuan dana pengembangan dari federasi nasional. Ia masuk ke kotak
     * masuk yang sama alih-alih ke tabel sendiri: yang datang adalah nama
     * badan, alamat surel, dan sebuah permintaan, yang persis bentuk sebuah
     * pesan. Tabel tersendiri baru berguna kalau pengajuannya punya keadaan
     * yang dilacak — diterima, ditolak, dicairkan — dan sampai ada yang memutus
     * itu, ia akan jadi kotak masuk kedua yang harus dibuka orang.
     */
    public const TOPICS = [
        'Media Requests',
        'General Enquiries',
        'Partnerships',
        'Membership Information',
        'Tournament Support',
        'Development Support',
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
