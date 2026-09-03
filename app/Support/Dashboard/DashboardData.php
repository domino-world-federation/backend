<?php

namespace App\Support\Dashboard;

use App\Models\ContactMessage;
use App\Models\Document;
use App\Models\FederationStat;
use App\Models\NewsArticle;
use App\Models\Partner;
use App\Models\SiteSetting;
use App\Models\Tournament;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;

/**
 * Isi dashboard — seluruhnya dari query sungguhan.
 *
 * Versi sebelumnya mengarang angka dan menandainya `isDemo`. Begitu tabel
 * kontennya ada, karangan itu berhenti jujur: penandanya mati sementara
 * angkanya tetap karangan. Jadi sekarang tidak ada jalur data palsu sama
 * sekali — dashboard kosong kalau isinya memang kosong, dan `php artisan
 * db:seed` yang mengisinya untuk keperluan pengembangan.
 */
final class DashboardData
{
    public const RANGES = ['30d' => 30, '90d' => 90, '12m' => 365];

    public function __construct(private readonly string $range = '30d') {}

    private function days(): int
    {
        return self::RANGES[$this->range] ?? 30;
    }

    /** Benar-benar belum ada apa pun untuk ditampilkan. */
    public function isEmpty(): bool
    {
        return NewsArticle::query()->doesntExist()
            && Document::query()->doesntExist()
            && ContactMessage::query()->doesntExist();
    }

    /** @return array<int, array<string, mixed>> */
    public function stats(): array
    {
        $days = $this->days();
        $since = CarbonImmutable::now()->subDays($days);
        $previous = $since->subDays($days);

        $published = fn ($from, $to) => NewsArticle::query()->live()
            ->whereBetween('published_at', [$from, $to])->count();

        $byStatus = fn (string $status, $from, $to) => NewsArticle::query()
            ->where('status', $status)->whereBetween('created_at', [$from, $to])->count();

        $unread = fn ($from, $to) => ContactMessage::query()->unread()
            ->whereBetween('created_at', [$from, $to])->count();

        $now = CarbonImmutable::now();

        return [
            $this->tile('published', __('backoffice.dashboard.stat_published'), $published($since, $now), $published($previous, $since),
                $this->sparkFor(fn ($f, $t) => $published($f, $t))),
            $this->tile('drafts', __('backoffice.dashboard.stat_drafts'), $byStatus('draft', $since, $now), $byStatus('draft', $previous, $since),
                $this->sparkFor(fn ($f, $t) => $byStatus('draft', $f, $t)), upIsGood: false),
            $this->tile('scheduled', __('backoffice.dashboard.stat_scheduled'), $byStatus('scheduled', $since, $now), $byStatus('scheduled', $previous, $since),
                $this->sparkFor(fn ($f, $t) => $byStatus('scheduled', $f, $t))),
            $this->tile('unread', __('backoffice.dashboard.stat_unread'), $unread($since, $now), $unread($previous, $since),
                $this->sparkFor(fn ($f, $t) => $unread($f, $t)), upIsGood: false),
        ];
    }

    /** @return array{points: array<int, array<string, mixed>>, series: array<int, array<string, string>>} */
    public function publications(): array
    {
        $days = $this->days();
        $monthly = $days > 90;
        $steps = $monthly ? 12 : ($days === 90 ? 13 : 30);
        $today = CarbonImmutable::now()->startOfDay();

        $news = $this->bucket(NewsArticle::query()->live()->getQuery(), 'published_at', $monthly);
        // `live()`, bukan `where('is_active')`: Documents naik dari sakelar dua
        // keadaan ke Visibility empat keadaan (`369:5236`), dan grafik ini
        // menghitung yang BENAR-BENAR tayang — termasuk yang terjadwal dan
        // waktunya sudah lewat. Dikelompokkan per `published_at` sekarang,
        // bukan `created_at`: yang dijawab grafik "kapan ia terbit", dan sejak
        // ada penjadwalan kedua tanggal itu bisa berbulan-bulan berbeda.
        $documents = $this->bucket(Document::query()->live()->getQuery(), 'published_at', $monthly);

        $points = [];

        for ($i = $steps - 1; $i >= 0; $i--) {
            $at = $monthly
                ? $today->subMonths($i)->startOfMonth()
                : $today->subDays($i * ($days === 90 ? 7 : 1));

            $key = $monthly ? $at->format('Y-m') : $at->toDateString();

            $points[] = [
                'label' => $monthly ? $at->translatedFormat('M') : $at->format($days === 90 ? 'j M' : 'j/n'),
                'iso' => $at->toDateString(),
                'values' => [(int) ($news[$key] ?? 0), (int) ($documents[$key] ?? 0)],
            ];
        }

        return [
            'points' => $points,
            'series' => [
                ['key' => 'news', 'label' => __('backoffice.dashboard.series_news')],
                ['key' => 'documents', 'label' => __('backoffice.dashboard.series_documents')],
            ],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function inboundMessages(): array
    {
        $today = CarbonImmutable::now()->startOfDay();
        $counts = $this->bucket(ContactMessage::query()->getQuery(), 'created_at', false);

        $points = [];
        for ($i = 13; $i >= 0; $i--) {
            $at = $today->subDays($i);
            $points[] = [
                'label' => $at->format('j/n'),
                'iso' => $at->toDateString(),
                'value' => (int) ($counts[$at->toDateString()] ?? 0),
            ];
        }

        return $points;
    }

    /**
     * Kelengkapan section landing page.
     *
     * Hanya "News Section" yang bisa dihitung sungguhan hari ini — ia
     * bergantung pada berita bertanda highlight, dan tabelnya sudah ada.
     * Sisanya menunggu modul Landing Page dan ditandai `unknown`, bukan
     * ditebak "siap": status hijau palsu justru menghentikan orang memeriksa.
     *
     * @return array<int, array<string, string>>
     */
    public function landingSections(): array
    {
        $highlighted = NewsArticle::query()->live()->where('is_highlighted', true)->count();
        $partners = Partner::query()->where('is_active', true)->count();
        $stats = FederationStat::query()->where('scope', 'home')->where('is_active', true)->count();
        // Aturan yang SAMA dengan `PublicController::featuredEvent()`: kartu
        // hitung mundur memilih turnamen tayang terdekat yang belum lewat,
        // bukan kolom "unggulan" yang harus disetel tangan.
        $featured = Tournament::query()->live()
            ->whereDate('starts_on', '>=', now()->startOfDay())
            ->count();
        $homeCopy = SiteSetting::map(SiteSetting::GROUP_HOME);

        /**
         * Tiap baris menaut ke layar yang BENAR-BENAR mengelolanya.
         *
         * Sebelum 2026-09-03 semuanya menunjuk `/landing-page/{key}` — delapan
         * placeholder yang isinya halaman kosong. Grup itu sudah diganti satu
         * layar "Home Page" untuk naskah yang tidak dimiliki modul lain;
         * sisanya tinggal di modulnya sendiri, dan ke sanalah tautannya
         * sekarang.
         */
        $count = fn (string $key, string $label, int $n, string $href) => [
            'key' => $key,
            'label' => $label,
            'status' => $n > 0 ? 'ready' : 'incomplete',
            'note' => $n > 0
                ? __('backoffice.section_status.filled', ['count' => $n])
                : __('backoffice.section_status.empty_note'),
            'href' => $href,
        ];

        return [
            [
                'key' => 'hero',
                'label' => 'Hero',
                'status' => filled($homeCopy['hero_headline'] ?? null) ? 'ready' : 'empty',
                'note' => filled($homeCopy['hero_headline'] ?? null)
                    ? $homeCopy['hero_headline']
                    : __('backoffice.section_status.empty_note'),
                'href' => '/home-page',
            ],
            $count('stats', 'Stats & Metrics', $stats, '/federations/stats'),
            $count('featured-event', 'Featured Event', $featured, '/tournaments'),
            [
                'key' => 'news-section',
                'label' => 'News Section',
                'status' => $highlighted > 0 ? 'ready' : 'incomplete',
                'note' => $highlighted > 0
                    ? __('backoffice.section_status.highlighted', ['count' => $highlighted])
                    : __('backoffice.section_status.no_highlight'),
                'href' => '/news?status=published',
            ],
            $count('federation-strip', 'Federation Strip', $partners, '/blocks'),
            [
                'key' => 'closing-cta',
                'label' => 'Closing CTA',
                'status' => filled($homeCopy['closing_headline'] ?? null) ? 'ready' : 'empty',
                'note' => filled($homeCopy['closing_headline'] ?? null)
                    ? str_replace("\n", ' ', $homeCopy['closing_headline'])
                    : __('backoffice.section_status.empty_note'),
                'href' => '/home-page',
            ],
        ];
    }

    /**
     * Aktivitas terbaru, digabung dari baris yang benar-benar ada.
     *
     * Bukan tabel audit — belum ada. Ini rekaman "apa yang terakhir berubah",
     * diambil dari `updated_at` tiap modul, jadi ia jujur tentang isi tanpa
     * mengklaim tahu siapa yang menekan tombolnya di masa lalu.
     *
     * @return array<int, array<string, mixed>>
     */
    public function recentActivity(): array
    {
        $news = NewsArticle::query()->with('author:id,name')->latest('updated_at')->limit(5)->get()
            ->map(fn (NewsArticle $a) => [
                'id' => "news-{$a->id}",
                'actor' => $a->author?->name ?? __('backoffice.activity.system'),
                'action' => match ($a->status) {
                    NewsArticle::STATUS_DRAFT => __('backoffice.activity.drafted'),
                    NewsArticle::STATUS_SCHEDULED => __('backoffice.activity.scheduled'),
                    default => __('backoffice.activity.published'),
                },
                'target' => $a->title,
                'at' => $a->updated_at?->toIso8601String(),
                'href' => "/news/{$a->id}/edit",
            ]);

        $messages = ContactMessage::query()->latest('created_at')->limit(5)->get()
            ->map(fn (ContactMessage $m) => [
                'id' => "msg-{$m->id}",
                'actor' => __('backoffice.activity.system'),
                'action' => __('backoffice.activity.message_from'),
                'target' => $m->name.($m->topic ? " — {$m->topic}" : ''),
                'at' => $m->created_at?->toIso8601String(),
                'href' => "/contact-messages/{$m->id}",
            ]);

        return $news->concat($messages)
            ->filter(fn (array $row) => $row['at'] !== null)
            ->sortByDesc('at')
            ->take(6)
            ->values()
            ->all();
    }

    // -----------------------------------------------------------------------

    /**
     * Menghitung baris per hari atau per bulan dalam SATU query.
     *
     * Alternatifnya — satu query per titik — berarti 30 round-trip untuk satu
     * grafik, dan itu terasa begitu tabelnya bertambah besar.
     *
     * @return Collection<string, int>
     */
    private function bucket(Builder $query, string $column, bool $monthly): Collection
    {
        $format = $monthly ? 'YYYY-MM' : 'YYYY-MM-DD';

        return $query
            ->whereNotNull($column)
            ->selectRaw("to_char({$column}, ?) as bucket, count(*) as total", [$format])
            ->groupBy('bucket')
            ->pluck('total', 'bucket');
    }

    /** @return array<int, int> */
    private function sparkFor(callable $count): array
    {
        $step = max(1, (int) round($this->days() / 12));
        $spark = [];

        for ($i = 11; $i >= 0; $i--) {
            $to = CarbonImmutable::now()->subDays($i * $step);
            $spark[] = (int) $count($to->subDays($step), $to);
        }

        return $spark;
    }

    /** @return array<string, mixed> */
    private function tile(string $key, string $label, int $value, int $previous, array $spark, bool $upIsGood = true): array
    {
        $delta = $previous === 0
            ? ($value === 0 ? 0.0 : 100.0)
            : round((($value - $previous) / $previous) * 100, 1);

        return [
            'key' => $key,
            'label' => $label,
            'value' => $value,
            'delta' => $delta,
            'deltaLabel' => 'vs periode sebelumnya',
            'upIsGood' => $upIsGood,
            'spark' => $spark,
        ];
    }
}
