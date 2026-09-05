<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BoardMemberResource;
use App\Http\Resources\ChampionResource;
use App\Http\Resources\DocumentResource;
use App\Http\Resources\FederationStatResource;
use App\Http\Resources\GalleryAlbumResource;
use App\Http\Resources\GalleryItemResource;
use App\Http\Resources\HeritageMilestoneResource;
use App\Http\Resources\MemberFederationResource;
use App\Http\Resources\NewsArticleResource;
use App\Http\Resources\OlympicResultResource;
use App\Http\Resources\PartnerResource;
use App\Http\Resources\StandingCommitteeResource;
use App\Http\Resources\SubCommitteeResource;
use App\Http\Resources\TournamentDetailResource;
use App\Http\Resources\TournamentResource;
use App\Models\BoardMember;
use App\Models\Champion;
use App\Models\Document;
use App\Models\Faq;
use App\Models\FederationStat;
use App\Models\GalleryEvent;
use App\Models\GalleryItem;
use App\Models\HeritageMilestone;
use App\Models\LegalPage;
use App\Models\MemberFederation;
use App\Models\NewsArticle;
use App\Models\OlympicResult;
use App\Models\PageMeta;
use App\Models\Partner;
use App\Models\SiteSetting;
use App\Models\StandingCommittee;
use App\Models\SubCommittee;
use App\Models\Tournament;
use App\Support\Media\StoredFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API publik yang dikonsumsi `../landing-page-nuxt`.
 *
 * Read-only, tanpa autentikasi (keputusan A4 di
 * `../../../docs/PRD-API-PUBLIK.md`): isinya memang publik — semuanya sudah
 * tayang di situs. Menambah kunci berarti menambah rahasia ke build frontend
 * tanpa melindungi apa pun.
 *
 * ── Satu aturan yang berlaku di SETIAP method di bawah. ──
 *
 * Hanya baris yang benar-benar tayang yang keluar (§5.5). Modul isi punya
 * `live()`; daftar sederhana punya `active()`. Draf dan baris terjadwal yang
 * belum waktunya TIDAK boleh bocor — penjadwalan di backoffice kehilangan
 * gunanya kalau API mengabaikannya.
 */
class PublicController extends Controller
{
    // ------------------------------------------------------------ News

    public function news(Request $request): JsonResponse
    {
        $limit = $this->limit($request, default: 12, max: 48);

        $articles = NewsArticle::query()
            ->with('category:id,name')
            ->live()
            ->when(
                $request->string('category')->toString() !== '',
                fn ($q) => $q->whereHas('category', fn ($c) => $c->where('slug', $request->string('category'))),
            )
            ->when($request->boolean('featured'), fn ($q) => $q->where('is_highlighted', true))
            ->latest('published_at')
            ->limit($limit)
            ->get();

        return $this->list(NewsArticleResource::bare($articles));
    }

    public function newsArticle(string $slug): JsonResponse
    {
        $article = NewsArticle::query()->with('category:id,name')->live()->where('slug', $slug)->firstOrFail();

        return response()->json((new NewsArticleResource($article, withBody: true))->resolve());
    }

    public function newsCategories(): JsonResponse
    {
        // String telanjang, bukan objek: `getNewsCategories(): Promise<string[]>`.
        return $this->list(
            NewsArticle::query()->live()->with('category:id,name')->get()
                ->pluck('category.name')->filter()->unique()->sort()->values()->all(),
        );
    }

    // ------------------------------------------------------- Documents

    public function resources(Request $request): JsonResponse
    {
        $documents = Document::query()
            ->live()
            ->when(
                $request->string('category')->toString() !== '',
                fn ($q) => $q->where('category', $request->string('category')),
            )
            ->latest('published_at')
            ->limit($this->limit($request, default: 24, max: 48))
            ->get();

        return $this->list(DocumentResource::bare($documents));
    }

    // --------------------------------------------------------- Gallery

    public function gallery(Request $request): JsonResponse
    {
        $items = GalleryItem::query()
            ->with('event:id,name')
            ->live()
            ->ordered()
            ->limit($this->limit($request, default: 24, max: 60))
            ->get();

        return $this->list(GalleryItemResource::bare($items));
    }

    public function galleryAlbums(Request $request): JsonResponse
    {
        $albums = GalleryEvent::query()
            // Hanya aset yang tayang yang ikut; album yang jadi kosong karena
            // itu dibuang, bukan dikirim sebagai kotak tanpa gambar.
            ->with(['items' => fn ($q) => $q->live()->ordered()])
            ->when(
                $request->string('slug')->toString() !== '',
                fn ($q) => $q->where('slug', $request->string('slug')),
            )
            ->orderByDesc('held_on')
            ->get()
            ->filter(fn (GalleryEvent $e) => $e->items->isNotEmpty())
            ->values();

        return $this->list(GalleryAlbumResource::bare($albums));
    }

    // ------------------------------------------------------------- FAQ

    /**
     * Tanpa `?placement=`, seluruh daftar keluar — itu yang dibaca `/page/faq`,
     * yang mengelompokkannya sendiri per kategori.
     *
     * Namanya `placement`, BUKAN `page`: `page` adalah nama universal untuk
     * "halaman ke berapa", dan orang pertama yang menambahkan pagination di
     * sini akan menabraknya. Yang dimaksud memang penempatan — tabelnya pun
     * bernama `faq_placements`.
     *
     * DENGAN `?placement=`, urutannya diambil dari `faq_placements.position`, BUKAN
     * `faqs.position`. Itu inti perbaikan 2026-09-03: peringkat di Home dan
     * peringkat di Domino adalah dua angka yang berbeda, dan mengurutkan salah
     * satu tidak boleh menggeser yang lain.
     */
    public function faqs(Request $request): JsonResponse
    {
        $placement = $this->enum($request, 'placement', Faq::PAGES);

        $faqs = Faq::query()
            ->with('category:id,slug,name')
            // Dikualifikasi: cabang `?placement=` melakukan join, dan kolom tanpa
            // nama tabel di dalam join adalah galat "ambiguous" yang menunggu
            // kolom bernama sama ditambahkan di tabel sebelah.
            ->where('faqs.is_active', true)
            ->when(
                $placement !== '',
                fn ($q) => $q
                    ->join('faq_placements', 'faq_placements.faq_id', '=', 'faqs.id')
                    ->where('faq_placements.page', $placement)
                    ->orderBy('faq_placements.position')
                    ->select('faqs.*'),
                fn ($q) => $q->orderBy('faqs.position'),
            )
            ->get();

        return $this->list($faqs->map(fn (Faq $f) => [
            'id' => (string) $f->id,
            'question' => $f->question,
            'answer' => $f->answer,
            // Tab kategori di `/page/faq` lahir dari pertanyaannya sendiri —
            // laci yang kosong tidak digambar — jadi kategorinya menempel di
            // tiap item, bukan dikirim sebagai daftar terpisah.
            'category' => $f->category === null ? null : [
                'slug' => $f->category->slug,
                'name' => $f->category->name,
            ],
        ])->all());
    }

    // ----------------------------------------------------- Legal pages

    public function legal(string $key): JsonResponse
    {
        $page = LegalPage::query()
            ->with(['blocks' => fn ($q) => $q->where('is_active', true)->orderBy('position')])
            ->where('key', $key)
            ->firstOrFail();

        return response()->json([
            'key' => $page->key,
            'title' => $page->title,
            'slug' => $page->slug,
            'lastUpdatedAt' => $page->last_updated_at?->toIso8601String(),
            'sections' => $page->blocks->map(fn ($b) => [
                'id' => (string) $b->id,
                'title' => $b->title,
                'description' => $b->description,
            ])->all(),
        ]);
    }

    /**
     * Naskah halaman depan yang tidak dimiliki modul mana pun.
     *
     * Bersarang per section, bukan rata: halamannya membaca `hero` dan
     * `closing` sebagai dua blok terpisah, dan awalan `hero_` di kunci yang
     * rata cuma bersarang lewat konvensi penamaan — yang tidak bisa dipaksakan
     * siapa pun.
     *
     * `closing.headline` sebuah LARIK. Figma memutus barisnya secara eksplisit
     * (`56:4683`) dan putusan itu bagian dari komposisinya: dua baris berbobot
     * sama, di tengah. Disimpan dipisah baris baru, dikirim sudah terpecah —
     * `<br>` di dalam satu kalimat adalah sesuatu yang harus dibawa-bawa
     * penerjemah.
     */
    public function home(): JsonResponse
    {
        $values = SiteSetting::map(SiteSetting::GROUP_HOME);

        $pick = fn (string $prefix) => collect($values)
            ->filter(fn (?string $v, string $k) => str_starts_with($k, $prefix) && filled($v))
            ->mapWithKeys(fn (string $v, string $k) => [
                (string) str($k)->after($prefix)->camel() => $v,
            ])
            ->all();

        $hero = $pick('hero_');
        $closing = $pick('closing_');

        if (isset($closing['headline'])) {
            $closing['headline'] = preg_split('/\R/', trim($closing['headline'])) ?: [];
        }

        return response()->json(['hero' => $hero, 'closing' => $closing]);
    }

    /**
     * Kontak dan tautan sosial — pasangan kunci-nilai, bukan daftar.
     *
     * Kuncinya diubah ke camelCase agar sama dengan SELURUH API. Di database
     * ia snake_case (`primary_email`) karena itu kolom; yang dibaca situs
     * publik `primaryEmail`, sama seperti `publishedAt` dan `fileUrl` di mana
     * pun. Satu response yang berbeda gayanya memaksa pemakainya mengingat
     * pengecualian.
     *
     * Nilai kosong DIHILANGKAN, bukan dikirim string kosong (§5.4): footer bisa
     * memakai `??` tanpa memeriksa dua keadaan.
     *
     * **Kuncinya didaftar, bukan disapu dari kelompoknya.** Sampai 2026-09-05
     * endpoint ini mengirim SELURUH kelompok `contact`, dan di dalamnya ada
     * `form_recipient_email` — ke mana pemberitahuan formulir dirutekan. Itu
     * config internal, bukan naskah publik: hari ini nilainya kebetulan sama
     * dengan alamat yang memang tayang, tapi seseorang yang mengarahkannya ke
     * kotak masuk internal akan menerbitkannya ke internet tanpa pernah
     * diberi tahu.
     *
     * Daftar putih juga berarti kelompok ini boleh tumbuh tanpa endpoint publik
     * ikut membocorkan apa pun yang ditambahkan — pengaturan baru harus DIPILIH
     * untuk tayang, bukan tayang karena lupa dikecualikan.
     */
    private const PUBLIC_SETTINGS = [
        'primary_email',
        'footer_address_label',
        'headquarters_address',
        'social_instagram',
        'social_tiktok',
        'social_x',
        'social_facebook',
        'social_youtube',
    ];

    public function settings(): JsonResponse
    {
        return response()->json(
            SiteSetting::query()
                ->where('group', SiteSetting::GROUP_CONTACT)
                ->whereIn('key', self::PUBLIC_SETTINGS)
                ->pluck('value', 'key')
                ->reject(fn ($value) => blank($value))
                ->mapWithKeys(fn (string $value, string $key) => [str($key)->camel()->toString() => $value])
                ->all(),
        );
    }

    // ----------------------------------------------------- Tournaments

    public function tournaments(Request $request): JsonResponse
    {
        $tournaments = Tournament::query()->live()->orderByDesc('starts_on')->get();

        // Disaring SETELAH query: `registration` diturunkan dari tanggal dan
        // tidak punya kolom untuk di-`where` (lihat `Tournament`).
        $filter = $this->enum($request, 'registration', Tournament::REGISTRATION_STATES);

        if ($filter !== '') {
            $tournaments = $tournaments->filter(
                fn (Tournament $t) => $t->registration_state === $filter,
            )->values();
        }

        return $this->list(TournamentResource::bare($tournaments));
    }

    public function tournament(string $slug): JsonResponse
    {
        $tournament = Tournament::query()
            ->with(['officials', 'scheduleEntries', 'winners', 'documents' => fn ($q) => $q->live()])
            ->live()
            ->where('slug', $slug)
            ->firstOrFail();

        return response()->json((new TournamentDetailResource($tournament))->resolve());
    }

    /**
     * Turnamen yang disorot — kartu besar di `/tournaments`.
     *
     * Yang paling dekat akan datang, bukan kolom "highlighted": sorotan yang
     * dipatok tangan akan menunjuk turnamen yang sudah lewat begitu tidak ada
     * yang ingat menggesernya.
     *
     * Bentuknya `ShowcaseEvent`, SAMA dengan `/tournaments/showcase` — itu yang
     * diterima `Hero.vue` di situs publik. Sebelumnya ia mengirim `Tournament`
     * penuh, bentuk yang berbeda untuk komponen yang sama.
     */
    public function highlightedTournament(): JsonResponse
    {
        $tournament = Tournament::query()->live()
            ->whereDate('ends_on', '>=', now()->startOfDay())
            ->orderBy('starts_on')
            ->first();

        return response()->json(
            $tournament === null ? null : $this->showcaseEvent($tournament),
        );
    }

    /** `FeaturedEvent` — enam field untuk countdown beranda. */
    public function featuredEvent(): JsonResponse
    {
        $tournament = Tournament::query()->live()
            ->whereDate('starts_on', '>=', now()->startOfDay())
            ->orderBy('starts_on')
            ->first();

        if ($tournament === null) {
            return response()->json(null);
        }

        return response()->json(array_filter([
            'id' => (string) $tournament->id,
            'name' => $tournament->name,
            'startsAt' => $tournament->starts_on?->toIso8601String(),
            'location' => $tournament->location,
            'country' => $tournament->country,
            'ctaUrl' => "/tournaments/{$tournament->slug}",
        ], static fn ($v) => $v !== null));
    }

    /** `ShowcaseEvent` — kartu event di beranda, sepuluh field. */
    public function showcaseEvents(): JsonResponse
    {
        $tournaments = Tournament::query()->live()
            ->whereDate('ends_on', '>=', now()->startOfDay())
            ->orderBy('starts_on')
            ->limit(6)
            ->get();

        return $this->list($tournaments->map(fn (Tournament $t) => $this->showcaseEvent($t))->all());
    }

    /**
     * Bentuk `ShowcaseEvent` — SATU tempat, dipakai `/tournaments/showcase`
     * DAN `/tournaments/highlighted`.
     *
     * Keduanya menggambar kartu yang sama; sebelum ini `highlighted` mengirim
     * `Tournament` penuh sementara `Hero.vue` di situs publik menerima
     * `ShowcaseEvent`. Bentuk yang berbeda untuk komponen yang sama adalah
     * ketidakcocokan yang tidak akan ketahuan sampai halamannya dirender.
     *
     * @return array<string, mixed>
     */
    private function showcaseEvent(Tournament $t): array
    {
        $card = (new TournamentResource($t))->resolve();

        return array_filter([
            'id' => $card['id'],
            'slug' => $t->slug,
            'name' => $t->name,
            'dateLabel' => $card['dateLabel'],
            'location' => $t->location,
            'summary' => str($t->overview)->stripTags()->limit(200)->toString(),
            'imageUrl' => $card['imageUrl'] ?? null,
            'imageAlt' => $t->name,
            'registrationLabel' => $card['registrationLabel'] ?? '',
            'detailsUrl' => "/tournaments/{$t->slug}",
        ], static fn ($v) => $v !== null);
    }

    // ------------------------------------------------ Results & winners

    public function champions(): JsonResponse
    {
        return $this->list(ChampionResource::bare(Champion::query()->active()->ordered()->get()));
    }

    public function olympicResults(): JsonResponse
    {
        return $this->list(OlympicResultResource::bare(OlympicResult::query()->active()->ordered()->get()));
    }

    // ----------------------------------------------- Federations & people

    public function members(Request $request): JsonResponse
    {
        $federations = MemberFederation::query()
            ->active()
            ->when(
                ($tier = $this->enum($request, 'tier', array_keys(config('dwf.membership_tiers')))) !== '',
                fn ($q) => $q->where('tier', $tier),
            )
            ->ordered()
            ->get();

        return $this->list(MemberFederationResource::bare($federations));
    }

    public function stats(Request $request): JsonResponse
    {
        $scope = $this->enum($request, 'scope', FederationStat::SCOPES) ?: FederationStat::SCOPE_HOME;

        return $this->list(FederationStatResource::bare(
            FederationStat::query()->where('scope', $scope)->active()->ordered()->get(),
        ));
    }

    public function boardMembers(): JsonResponse
    {
        return $this->list(BoardMemberResource::bare(BoardMember::query()->active()->ordered()->get()));
    }

    public function subCommittees(): JsonResponse
    {
        return $this->list(SubCommitteeResource::bare(SubCommittee::query()->active()->ordered()->get()));
    }

    public function standingCommittees(): JsonResponse
    {
        return $this->list(StandingCommitteeResource::bare(
            StandingCommittee::query()->active()->ordered()->get(),
        ));
    }

    public function heritageMilestones(): JsonResponse
    {
        return $this->list(HeritageMilestoneResource::bare(
            HeritageMilestone::query()->active()->ordered()->get(),
        ));
    }

    public function partners(): JsonResponse
    {
        return $this->list(PartnerResource::bare(Partner::query()->active()->ordered()->get()));
    }

    /**
     * Meta SEO seluruh halaman, dalam SATU response.
     *
     * Satu panggilan, bukan satu per rute: datanya belasan baris pendek dan
     * situs publik membutuhkannya di tiap render halaman. Memecahnya per rute
     * berarti satu request tambahan di setiap navigasi demi menghemat dua
     * kilobyte.
     *
     * Bentuknya `{ default: {...}, pages: { "/about": {...} } }` — halaman
     * mencari rutenya sendiri, lalu jatuh ke `default` untuk tiap field yang
     * kosong.
     */
    public function seo(): JsonResponse
    {
        $rows = PageMeta::query()->ordered()->get();

        $shape = fn (?PageMeta $m) => $m === null ? null : array_filter([
            'title' => $m->title,
            'description' => $m->description,
            'ogImageUrl' => StoredFile::url($m->og_image_path),
        ], static fn ($v) => $v !== null && $v !== '');

        return response()->json([
            'default' => $shape($rows->firstWhere('route', PageMeta::DEFAULT_ROUTE)) ?? new \stdClass,
            'pages' => $rows
                ->reject(fn (PageMeta $m) => $m->isDefault())
                ->mapWithKeys(fn (PageMeta $m) => [$m->route => $shape($m)])
                ->all() ?: new \stdClass,
        ]);
    }

    /**
     * Membungkus daftar sebagai ARRAY TELANJANG (§5.6).
     *
     * `client.ts` membacanya begitu (`request<NewsArticle[]>`); pembungkus
     * `{ data: [...] }` bawaan Laravel akan menghasilkan `response.map is not a
     * function` di setiap halaman sekaligus.
     *
     * @param  array<int, mixed>  $items
     */
    /**
     * `?limit=` dijepit ke rentang yang masuk akal — satu aturan untuk semua daftar.
     *
     * Sebelum ini tiap endpoint menuliskannya sendiri dan hasilnya berbeda-beda:
     * ada yang punya bawaan, ada yang tidak, dan `min((int) $x, 48)` meloloskan
     * NOL dan angka negatif — `limit(0)` mengembalikan daftar kosong, yang
     * terbaca seperti "tidak ada isinya" padahal permintaannya yang salah.
     */
    /**
     * Nilai penyaring yang tidak dikenal DITOLAK, bukan diabaikan.
     *
     * Ini beda perlakuan yang disengaja, dan garisnya jelas:
     *
     *   - Penyaring BERDAFTAR TERTUTUP (`scope`, `tier`, `registration`,
     *     `placement`) — salah ketik = 422. `?scope=member` (kurang satu huruf)
     *     dulu diam-diam membalas statistik BERANDA: data yang masuk akal,
     *     dari daftar yang salah, tanpa satu pun tanda.
     *   - Penyaring BERISI TEKS BEBAS (`category`, `slug`, `q`) — nilainya
     *     diketik orang di CMS, jadi yang tidak cocok memang wajar
     *     mengembalikan daftar kosong. Itu jawaban, bukan galat.
     *
     * @param  array<int, string>  $allowed
     */
    private function enum(Request $request, string $key, array $allowed): string
    {
        $value = $request->string($key)->toString();

        abort_if(
            $value !== '' && ! in_array($value, $allowed, true),
            422,
            "The {$key} field must be one of: ".implode(', ', $allowed).'.',
        );

        return $value;
    }

    private function limit(Request $request, int $default, int $max): int
    {
        return min(max((int) $request->integer('limit', $default), 1), $max);
    }

    private function list(array $items): JsonResponse
    {
        return response()->json(array_values($items));
    }
}
