<?php

namespace Tests\Feature\Api;

use App\Models\Champion;
use App\Models\Document;
use App\Models\FederationStat;
use App\Models\GalleryEvent;
use App\Models\GalleryItem;
use App\Models\MemberFederation;
use App\Models\NewsArticle;
use App\Models\Partner;
use App\Models\Tournament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * API publik — enam aturan lintas endpoint di `docs/PRD-API-PUBLIK.md` §5.
 *
 * Kelimanya gagal DIAM-DIAM kalau dilanggar: tidak ada galat, hanya halaman
 * yang salah di sisi seberang. Berkas ini yang membuat pelanggarannya berisik.
 */
class PublicApiTest extends TestCase
{
    use RefreshDatabase;

    // ------------------------------------------------------ §5.6 dan akses

    /** @return array<string, array{0: string}> */
    public static function listEndpoints(): array
    {
        return [
            'news' => ['/api/v1/news'],
            'news categories' => ['/api/v1/news/categories'],
            'resources' => ['/api/v1/resources'],
            'gallery' => ['/api/v1/gallery'],
            'gallery albums' => ['/api/v1/gallery/albums'],
            'faqs' => ['/api/v1/faqs'],
            'tournaments' => ['/api/v1/tournaments'],
            'champions' => ['/api/v1/champions'],
            'olympic results' => ['/api/v1/olympic-results'],
            'members' => ['/api/v1/members'],
            'stats' => ['/api/v1/stats'],
            'board members' => ['/api/v1/board-members'],
            'sub committees' => ['/api/v1/sub-committees'],
            'standing committees' => ['/api/v1/standing-committees'],
            'heritage' => ['/api/v1/heritage-milestones'],
            'partners' => ['/api/v1/partners'],
        ];
    }

    /**
     * §5.6 — daftar mengembalikan ARRAY TELANJANG.
     *
     * Pembungkus `{ data: [...] }` bawaan Laravel akan menghasilkan
     * `response.map is not a function` di setiap halaman sekaligus.
     */
    #[DataProvider('listEndpoints')]
    public function test_every_list_returns_a_bare_array(string $url): void
    {
        $response = $this->getJson($url);

        $response->assertOk();
        $this->assertIsList($response->json(), "{$url} tidak mengembalikan array telanjang");
    }

    /** Tanpa autentikasi — keputusan A4. */
    #[DataProvider('listEndpoints')]
    public function test_every_list_is_reachable_by_a_guest(string $url): void
    {
        $this->getJson($url)->assertOk();
    }

    // --------------------------------------------------------------- §5.3

    /** `id` selalu STRING — 29 tipe di `types.ts` menulis `id: string`. */
    public function test_ids_are_strings_not_numbers(): void
    {
        Partner::factory()->create();
        Champion::factory()->create();

        foreach (['/api/v1/partners', '/api/v1/champions'] as $url) {
            $first = $this->getJson($url)->json('0.id');

            $this->assertIsString($first, "{$url} mengirim id sebagai angka");
        }
    }

    // --------------------------------------------------------------- §5.4

    /**
     * Field opsional DIHILANGKAN, bukan dikirim `null`.
     *
     * Kontraknya `field?: string`. Komponen menulis `v-if="item.field"`, dan
     * sejumlah guard membedakan `undefined` dari `null`.
     */
    public function test_optional_fields_are_omitted_rather_than_null(): void
    {
        Champion::factory()->create(['portrait_path' => null, 'portrait_alt' => null]);

        $row = $this->getJson('/api/v1/champions')->json('0');

        $this->assertArrayNotHasKey('portraitUrl', $row);
        $this->assertArrayNotHasKey('portraitAlt', $row);
        $this->assertArrayHasKey('name', $row);
    }

    /** `false` dan `0` BUKAN null — keduanya jawaban yang sah. */
    public function test_false_and_zero_survive_the_filter(): void
    {
        NewsArticle::factory()->create(['is_highlighted' => false]);

        $row = $this->getJson('/api/v1/news')->json('0');

        $this->assertArrayHasKey('isFeatured', $row);
        $this->assertFalse($row['isFeatured']);
    }

    // --------------------------------------------------------------- §5.5

    /**
     * Draf dan jadwal yang belum waktunya TIDAK boleh bocor.
     *
     * Penjadwalan di backoffice kehilangan gunanya kalau API mengabaikannya.
     */
    public function test_drafts_and_future_schedules_never_leak(): void
    {
        NewsArticle::factory()->create(['title' => 'tayang']);
        NewsArticle::factory()->draft()->create(['title' => 'draf']);

        Tournament::factory()->create(['name' => 'turnamen tayang']);
        Tournament::factory()->draft()->create(['name' => 'turnamen draf']);

        Document::factory()->create(['title' => 'dokumen tayang']);
        Document::factory()->create([
            'title' => 'dokumen draf', 'status' => 'draft', 'published_at' => null,
        ]);

        $news = collect($this->getJson('/api/v1/news')->json())->pluck('title');
        $this->assertContains('tayang', $news);
        $this->assertNotContains('draf', $news);

        $tournaments = collect($this->getJson('/api/v1/tournaments')->json())->pluck('name');
        $this->assertContains('turnamen tayang', $tournaments);
        $this->assertNotContains('turnamen draf', $tournaments);

        $documents = collect($this->getJson('/api/v1/resources')->json())->pluck('title');
        $this->assertContains('dokumen tayang', $documents);
        $this->assertNotContains('dokumen draf', $documents);
    }

    /** Baris nonaktif juga tidak ikut. */
    public function test_inactive_rows_never_leak(): void
    {
        Partner::factory()->create(['name' => 'aktif']);
        Partner::factory()->create(['name' => 'mati', 'is_active' => false]);

        $names = collect($this->getJson('/api/v1/partners')->json())->pluck('name');

        $this->assertContains('aktif', $names);
        $this->assertNotContains('mati', $names);
    }

    /** Album yang seluruh asetnya draf dibuang, bukan dikirim kosong. */
    public function test_an_album_with_no_live_items_is_dropped(): void
    {
        $event = GalleryEvent::factory()->create(['name' => 'kosong']);
        GalleryItem::factory()->create([
            'gallery_event_id' => $event->id, 'status' => 'draft', 'published_at' => null,
        ]);

        $this->assertSame([], $this->getJson('/api/v1/gallery/albums')->json());
    }

    // --------------------------------------------------------------- §5.1

    /**
     * `dateLabel` sudah TEKS, bukan ISO — dan `registrationLabel` sepakat
     * dengan `registration`.
     *
     * Halaman yang menghitung "in 3 days" dari timestamp akan terus menulis
     * "3 days" sampai minggu berikutnya begitu responsnya masuk edge cache.
     */
    public function test_display_labels_are_preformatted_not_timestamps(): void
    {
        Tournament::factory()->create([
            'starts_on' => '2027-03-18',
            'ends_on' => '2027-03-21',
            'registration_starts_on' => now()->subWeek(),
            'registration_ends_on' => now()->addDays(3),
        ]);

        $row = $this->getJson('/api/v1/tournaments')->json('0');

        $this->assertSame('Mar 18 - 21, 2027', $row['dateLabel']);
        $this->assertStringNotContainsString('T00:00', $row['dateLabel']);

        $this->assertSame('open', $row['registration']);
        $this->assertSame('Registration ends in 3 days', $row['registrationLabel']);
    }

    /** Rentang yang melintasi tahun menyebut kedua tahunnya. */
    public function test_a_date_range_across_years_names_both(): void
    {
        Tournament::factory()->create(['starts_on' => '2026-12-30', 'ends_on' => '2027-01-02']);

        $this->assertSame(
            'Dec 30, 2026 - Jan 2, 2027',
            $this->getJson('/api/v1/tournaments')->json('0.dateLabel'),
        );
    }

    /**
     * Turnamen yang SEDANG BERLANGSUNG tidak punya `registrationLabel`.
     *
     * Kartunya membuang tabnya alih-alih mencetak tenggat untuk pendaftaran
     * yang sudah tidak menerima siapa pun.
     */
    public function test_an_ongoing_tournament_has_no_registration_label(): void
    {
        Tournament::factory()->live()->create([
            'registration_starts_on' => now()->subMonth(),
            'registration_ends_on' => now()->addWeek(),
        ]);

        $row = $this->getJson('/api/v1/tournaments')->json('0');

        $this->assertSame('ongoing', $row['registration']);
        $this->assertArrayNotHasKey('registrationLabel', $row);
    }

    /** Ukuran berkas sudah bersatuan — satuannya milik API. */
    public function test_file_size_is_preformatted(): void
    {
        Document::factory()->create(['file_size' => 2_411_724]);

        $this->assertSame('2.3 MB', $this->getJson('/api/v1/resources')->json('0.fileSize'));
    }

    // --------------------------------------------------------------- §5.2

    /** URL gambar ABSOLUT — path relatif rusak di domain frontend. */
    public function test_image_urls_are_absolute(): void
    {
        Partner::factory()->create(['logo_path' => 'partners/logo.webp']);

        $url = $this->getJson('/api/v1/partners')->json('0.logoUrl');

        $this->assertStringStartsWith('http', $url);
    }

    // ------------------------------------------------------------- detail

    public function test_the_article_detail_carries_its_body_and_the_list_does_not(): void
    {
        $article = NewsArticle::factory()->create(['body' => '<p>Isi lengkap.</p>']);

        $this->assertArrayNotHasKey('body', $this->getJson('/api/v1/news')->json('0'));

        $detail = $this->getJson("/api/v1/news/{$article->slug}")->json();
        $this->assertSame('<p>Isi lengkap.</p>', $detail['body']);
    }

    public function test_a_draft_article_is_not_reachable_by_slug(): void
    {
        $article = NewsArticle::factory()->draft()->create();

        $this->getJson("/api/v1/news/{$article->slug}")->assertNotFound();
    }

    /** Blok kosong DIHILANGKAN — halaman menggambar yang ada, bukan judul hampa. */
    public function test_a_tournament_without_extras_omits_those_blocks(): void
    {
        $tournament = Tournament::factory()->create();

        $detail = $this->getJson("/api/v1/tournaments/{$tournament->slug}")->json();

        $this->assertArrayHasKey('summary', $detail);
        $this->assertArrayNotHasKey('schedule', $detail);
        $this->assertArrayNotHasKey('officials', $detail);
        $this->assertArrayNotHasKey('winners', $detail);
        $this->assertArrayNotHasKey('prize', $detail);
    }

    public function test_the_tier_filter_narrows_the_member_directory(): void
    {
        MemberFederation::factory()->create(['name' => 'nasional', 'tier' => 'national']);
        MemberFederation::factory()->create(['name' => 'klub', 'tier' => 'club']);

        $names = collect($this->getJson('/api/v1/members?tier=club')->json())->pluck('name');

        $this->assertSame(['klub'], $names->all());
    }

    /** Dua lingkup statistik tidak boleh tercampur. */
    public function test_stats_are_separated_by_scope(): void
    {
        FederationStat::factory()->create(['label' => 'beranda']);
        FederationStat::factory()->members()->create(['label' => 'anggota']);

        $this->assertSame(['beranda'], collect($this->getJson('/api/v1/stats?scope=home')->json())->pluck('label')->all());
        $this->assertSame(['anggota'], collect($this->getJson('/api/v1/stats?scope=members')->json())->pluck('label')->all());
    }
}
