<?php

namespace App\Models;

use App\Models\Concerns\HasSlug;
use App\Models\Concerns\RecordsActivity;
use App\Models\Concerns\TracksEditor;
use App\Models\Concerns\TracksPublication;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Satu dokumen — layar `369:5236` (daftar) dan `262:3449` (Add Document).
 *
 * Modul ini sempat bernama "Press Releases". Desain menunjukkan itu keliru:
 * Press Releases adalah salah satu NILAI kolom `category` (`478:5386`), bukan
 * nama modulnya. Tabel, route, izin, dan jejak auditnya dipindahkan oleh
 * migrasi `2026_09_02_140000_rename_press_releases_to_documents`.
 *
 * Berkas lama tetap tersimpan di `storage/app/public/press-releases/`; path
 * disimpan per baris, jadi keduanya tetap terbuka dengan benar. Unggahan baru
 * masuk ke `documents/`.
 */
#[Fillable([
    'title', 'slug', 'file_path', 'file_size', 'category',
    'status', 'published_at', 'updated_by_id', 'created_by_id', 'published_by_id',
])]
class Document extends Model
{
    use HasFactory, HasSlug, RecordsActivity, TracksEditor, TracksPublication;

    /*
     * Status yang SAMA PERSIS dengan `NewsArticle` dan `GalleryItem`.
     *
     * Bukan kebetulan: `262:3449` meminta "Publish Time: Now / Schedule" dan
     * daftarnya menggambar pemilih Visibility yang sama. Menjawab pertanyaan
     * yang sama dengan kata yang berbeda berarti orang harus belajar tiga
     * kosakata untuk satu konsep.
     */
    public const STATUS_DRAFT = 'draft';

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_UNPUBLISHED = 'unpublished';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_SCHEDULED,
        self::STATUS_PUBLISHED,
        self::STATUS_UNPUBLISHED,
    ];

    /**
     * Status yang bisa disetel LANGSUNG dari daftar — dan `draft` sengaja tidak
     * termasuk.
     *
     * Draft bukan tujuan yang dipilih, melainkan keadaan yang lahir dari satu
     * tombol: "Save Draft" di dalam formulirnya. Membuatnya bisa dipilih dari
     * daftar berarti ada dua jalan menuju satu keadaan, dan yang lewat daftar
     * itu jalan yang tidak pernah membuka isinya — menarik tulisan yang sudah
     * tayang kembali jadi draft tanpa seorang pun membacanya lebih dulu.
     * Menarik dari peredaran tetap bisa, dan namanya `unpublished`.
     *
     * Ditegakkan di sini, bukan cuma disembunyikan di layar: `visibility()`
     * memvalidasi dengan daftar ini, jadi permintaan yang dirakit tangan pun
     * ditolak 422. `QuickStatusTest` menyapu keempat modul.
     */
    public const QUICK_STATUSES = [
        self::STATUS_PUBLISHED,
        self::STATUS_UNPUBLISHED,
        self::STATUS_SCHEDULED,
    ];

    protected function casts(): array
    {
        return ['file_size' => 'integer', 'published_at' => 'datetime'];
    }

    /**
     * Yang benar-benar tampil di situs publik.
     *
     * Sama seperti News dan Gallery: `published`, ATAU `scheduled` yang waktunya
     * sudah lewat — dihitung saat query, bukan dititipkan ke cron yang bisa mati
     * tanpa ada yang tahu.
     */
    /**
     * Nama berkas saat diunduh — diturunkan dari JUDUL, bukan dari nama di disk.
     *
     * Nama di disk 40 karakter acak (`hashName()`): benar sebagai penyimpanan,
     * tapi berkas bernama `a1b2c3….pdf` di folder Downloads orang tidak bisa
     * dikenali lagi seminggu kemudian. Satu tempat, dipakai `MediaController`
     * DAN layar CMS-nya — kalau berbeda, yang tertulis di layar bukan nama yang
     * benar-benar didapat orang.
     */
    public function downloadName(): string
    {
        return Str::slug($this->title).'.'.pathinfo((string) $this->file_path, PATHINFO_EXTENSION);
    }

    public function scopeLive(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->where('status', self::STATUS_PUBLISHED)
                ->orWhere(fn (Builder $s) => $s
                    ->where('status', self::STATUS_SCHEDULED)
                    ->whereNotNull('published_at')
                    ->where('published_at', '<=', now()));
        });
    }

    /** Kunci status untuk `StatusPill` dan `VisibilitySelect` — bukan teks jadi. */
    public function getVisibilityAttribute(): string
    {
        if ($this->status === self::STATUS_DRAFT) {
            return 'draft';
        }

        if ($this->status === self::STATUS_UNPUBLISHED) {
            return 'unpublished';
        }

        if ($this->status === self::STATUS_SCHEDULED && $this->published_at?->isFuture()) {
            return 'scheduled';
        }

        return 'posted';
    }

    /** Sudah diformat untuk ditampilkan — API yang memiliki satuannya. */
    public function getFileSizeLabelAttribute(): string
    {
        $bytes = $this->file_size;

        if ($bytes >= 1_048_576) {
            return round($bytes / 1_048_576, 1).' MB';
        }

        return max(1, (int) round($bytes / 1024)).' KB';
    }
}
