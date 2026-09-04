<?php

namespace App\Models;

use App\Models\Concerns\HasSlug;
use App\Models\Concerns\RecordsActivity;
use App\Models\Concerns\TracksEditor;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'news_category_id', 'author_id', 'updated_by_id', 'title', 'slug', 'excerpt', 'body',
    'hero_image_path', 'landscape_image_path',
    'is_highlighted', 'status', 'published_at',
])]
class NewsArticle extends Model
{
    use HasFactory, HasSlug, RecordsActivity, TracksEditor;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_PUBLISHED = 'published';

    /**
     * Pernah tayang, sekarang disembunyikan.
     *
     * Berbeda dari `draft`, dan bedanya bukan kosmetik: draft belum pernah
     * dibaca siapa pun, sedangkan artikel yang di-unpublish sudah punya URL yang
     * dibagikan orang. Menggabungkan keduanya menghilangkan satu-satunya
     * keterangan yang menjelaskan kenapa sebuah tautan tiba-tiba mati.
     */
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
        return [
            'is_highlighted' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(NewsCategory::class, 'news_category_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * Yang sudah benar-benar tayang.
     *
     * `status` saja tidak cukup: sebuah berita bisa berstatus `scheduled`
     * dengan `published_at` yang sudah lewat karena tidak ada proses yang
     * memindahkannya. Jadi "terbit" adalah status published ATAU jadwal yang
     * waktunya sudah tiba — dihitung saat query, bukan dititipkan ke cron.
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

    /**
     * Kunci status untuk kolom "Visibility" pada daftar.
     *
     * Mengembalikan KUNCI (`draft`/`scheduled`/`posted`), bukan teks jadi —
     * yang menerjemahkannya `StatusPill` di sisi Vue. Mengirim "Draft" dari
     * sini berarti kolom itu tetap Inggris walau bahasanya diganti.
     */
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
