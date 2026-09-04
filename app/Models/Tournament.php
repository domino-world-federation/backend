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
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Satu turnamen — formulir `585:11241`.
 *
 * Sepuluh section desain jadi satu baris di sini ditambah dua relasi berulang
 * (ofisial, jadwal) dan satu pivot ke dokumen. Alasannya di migrasinya.
 */
#[Fillable([
    'name', 'slug', 'coverage', 'starts_on', 'ends_on', 'city', 'country',
    'rules_format', 'attendance', 'hero_image_path', 'overview',
    'venue_name', 'venue_address', 'venue_lat', 'venue_lng',
    'prize_amount', 'prize_currency', 'prize_description', 'prize_image_path',
    'contact_email', 'contact_phone',
    'registration_starts_on', 'registration_ends_on', 'dwf_id_requirement',
    'eligibility', 'registration_method',
    'game_format', 'participant_count', 'participant_type', 'competition_system', 'scoring',
    'status', 'published_at', 'created_by_id', 'published_by_id', 'updated_by_id',
])]
class Tournament extends Model
{
    use HasFactory, HasSlug, RecordsActivity, TracksEditor, TracksPublication;

    /** Kosakata penayangan yang sama dengan News, Gallery, dan Documents. */
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
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'registration_starts_on' => 'date',
            'registration_ends_on' => 'date',
            'published_at' => 'datetime',
            'prize_amount' => 'decimal:2',
            'venue_lat' => 'decimal:7',
            'venue_lng' => 'decimal:7',
            'participant_count' => 'integer',
        ];
    }

    /** @return HasMany<TournamentOfficial, $this> */
    public function officials(): HasMany
    {
        return $this->hasMany(TournamentOfficial::class)->orderBy('position');
    }

    /**
     * Orang yang minta dikabari saat pendaftaran dibuka.
     *
     * Barisnya dibuat pengunjung situs publik lewat `POST
     * /api/v1/tournaments/{id}/subscribe`, bukan oleh admin mana pun.
     *
     * @return HasMany<TournamentNotification, $this>
     */
    public function notifications(): HasMany
    {
        return $this->hasMany(TournamentNotification::class);
    }

    /** @return HasMany<TournamentScheduleEntry, $this> */
    public function scheduleEntries(): HasMany
    {
        return $this->hasMany(TournamentScheduleEntry::class)->orderBy('position');
    }

    /**
     * Pemenang — diisi lewat menu Results & Winners, BUKAN formulir turnamen.
     *
     * "Results & Winners is not part of Create Tournament" (`596:11483`).
     *
     * @return HasMany<TournamentWinner, $this>
     */
    public function winners(): HasMany
    {
        return $this->hasMany(TournamentWinner::class)->orderBy('position');
    }

    /**
     * Dokumen regulasi — MENUNJUK baris `documents` yang sudah ada.
     *
     * "files are not re-uploaded here" (`596:11467`): tanggal terbit, jenis,
     * dan ukuran berkasnya tetap satu sumber.
     *
     * @return BelongsToMany<Document, $this>
     */
    public function documents(): BelongsToMany
    {
        return $this->belongsToMany(Document::class)->withPivot('position')->orderBy('position');
    }

    /** Yang benar-benar tampil di situs publik — sama seperti modul lain. */
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

    /**
     * Keadaan turnamennya sendiri — `upcoming`, `live`, atau `completed`.
     *
     * DITURUNKAN dari tanggal, bukan kolom. Frontend memintanya sebagai
     * `Tournament.status` (`landing-page-nuxt/app/lib/api/types.ts`), tapi
     * menyimpannya berarti kolom yang basi setiap tengah malam kecuali ada cron
     * yang menyegarkannya — dan cron yang mati tidak memberi tahu siapa pun.
     *
     * Perhatikan ia BERBEDA dari `visibility`: yang itu menjawab "apakah
     * halamannya tayang", yang ini "apakah pertandingannya sedang berlangsung".
     * Turnamen bisa `published` dan `completed` sekaligus.
     */
    public function getStageAttribute(): string
    {
        $today = now()->startOfDay();

        if ($this->starts_on->gt($today)) {
            return 'upcoming';
        }

        return $this->ends_on->lt($today) ? 'completed' : 'live';
    }

    /**
     * Keadaan pendaftaran — `upcoming`, `open`, `ongoing`, atau `closed`.
     *
     * Empat anggota, dan yang keempat harus ada. `types.ts` di frontend
     * menjelaskan kenapa: tanpa `upcoming`, dua fakta yang berbeda —
     * "pendaftaran belum dibuka" dan "pendaftaran sudah berakhir" — sama-sama
     * dipikul `closed`, dan kartunya mencetak pil CLOSED di atas tab
     * "Registration opens Nov 1". Kartu yang membantah dirinya sendiri di dua
     * tempat yang terbaca sekaligus.
     *
     * Turnamen tanpa tanggal pendaftaran sama sekali dianggap `closed`:
     * pendaftaran yang tidak pernah dibuka memang tidak menerima siapa pun.
     */
    /**
     * Keadaan pendaftaran yang mungkin — daftarnya di sini, bukan diketik ulang
     * di controller yang menyaringnya.
     */
    public const REGISTRATION_STATES = ['open', 'upcoming', 'ongoing', 'closed'];

    public function getRegistrationStateAttribute(): string
    {
        $start = $this->registration_starts_on;
        $end = $this->registration_ends_on;

        if ($start === null && $end === null) {
            return 'closed';
        }

        $today = now()->startOfDay();

        if ($start !== null && $start->gt($today)) {
            return 'upcoming';
        }

        if ($end !== null && $end->lt($today)) {
            return 'closed';
        }

        // Sudah dibuka dan belum ditutup. `ongoing` dipakai saat turnamennya
        // sendiri sudah berjalan — pendaftarannya masih menerima, tapi
        // menyebutnya "open" di samping pertandingan yang sedang berlangsung
        // membaca seperti undangan yang sudah terlambat.
        return $this->stage === 'live' ? 'ongoing' : 'open';
    }

    /** "Bangkok, Thailand" — satu baris, seperti yang dicetak kartunya. */
    public function getLocationAttribute(): string
    {
        return "{$this->city}, {$this->country}";
    }
}
