<?php

namespace App\Models;

use App\Models\Concerns\HasPosition;
use App\Models\Concerns\HasSlug;
use App\Models\Concerns\RecordsActivity;
use App\Models\Concerns\TracksEditor;
use App\Models\Concerns\TracksPublication;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'gallery_event_id', 'kind', 'path', 'slug', 'alt',
    'status', 'published_at', 'position', 'updated_by_id',
    'created_by_id', 'published_by_id',
])]
class GalleryItem extends Model
{
    use HasFactory, HasPosition, HasSlug, RecordsActivity, TracksEditor, TracksPublication;

    public const KINDS = ['image', 'video'];

    /*
     * Status yang SAMA PERSIS dengan `NewsArticle`.
     *
     * Bukan kebetulan: layar Add Gallery (`478:6930`) meminta "Publish Time:
     * Now / Schedule" dan tombol Save Draft · Publish — pertanyaan yang sama
     * dengan yang dijawab News. Menjawabnya dengan kata yang berbeda berarti
     * orang harus belajar dua kosakata untuk satu konsep.
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
        return ['published_at' => 'datetime'];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(GalleryEvent::class, 'gallery_event_id');
    }

    /**
     * Yang benar-benar tampil di situs publik.
     *
     * Sama seperti di News: status `published`, ATAU `scheduled` yang waktunya
     * sudah lewat — dihitung saat query, bukan dititipkan ke cron yang bisa
     * mati tanpa ada yang tahu.
     */
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

    /** Kunci status untuk `StatusPill` — bukan teks jadi. Lihat `NewsArticle`. */
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
}
