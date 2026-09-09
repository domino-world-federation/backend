<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\DocumentPlacement;
use App\Models\User;
use App\Support\DocumentCategories;
use App\Support\DocumentSections;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Rak dokumen per halaman.
 *
 * **Kunci rak adalah kontrak antar-repo, sama seperti nama kategori.** Situs
 * publik memintanya mentah-mentah (`/api/v1/resources?section=news.publications`)
 * dan `document_placements.section` menyimpannya apa adanya, jadi satu huruf
 * yang berbeda membuat rak di sana ditolak 422 — atau, kalau penolakannya
 * dilonggarkan suatu saat, kosong tanpa satu pun galat. Karena itu kuncinya
 * dieja lengkap di sini: yang mengubahnya dipaksa berhenti dan memutuskan
 * apakah barisnya ikut dipindah dan apakah `landing-page-nuxt` ikut diperbarui.
 */
class DocumentSectionTest extends TestCase
{
    use RefreshDatabase;

    /** Kunci yang dikirim situs publik. Mengubahnya menuntut migrasi + perubahan di landing-page. */
    private const EXPECTED = [
        'home.resources',
        'domino.rulebook',
        'governance.statutes',
        'governance.repository',
        'development.library',
        'development.youth',
        'tournaments.regulations',
        'news.press',
        'news.publications',
    ];

    public function test_the_shelf_keys_are_exactly_what_the_public_site_asks_for(): void
    {
        $this->assertSame(self::EXPECTED, DocumentSections::keys());
    }

    /**
     * Tiap rak menyebut kategori yang benar-benar ada — kecuali Home, yang
     * memang menarik seluruh perpustakaan.
     */
    public function test_every_shelf_draws_from_a_category_that_exists(): void
    {
        $categories = DocumentCategories::names();

        foreach (DocumentSections::all() as $section) {
            if ($section['category'] === null) {
                $this->assertSame('home.resources', $section['key'], 'Hanya Home yang tanpa kategori.');

                continue;
            }

            $this->assertContains(
                $section['category'],
                $categories,
                "Rak `{$section['key']}` menarik kategori yang tidak ada di daftar.",
            );
        }
    }

    /** Rak yang belum dikurasi tetap terisi — kalau tidak, hari fitur ini menyala adalah hari seluruh rak kosong. */
    public function test_an_untouched_shelf_falls_back_to_the_newest_in_its_category(): void
    {
        Document::factory()->count(3)->create([
            'category' => 'Publication',
            'status' => Document::STATUS_PUBLISHED,
        ]);

        $this->assertCount(3, DocumentSections::documents('news.publications'));
    }

    /** Fallback menghormati kategori raknya, bukan menarik apa saja. */
    public function test_the_fallback_does_not_reach_into_another_category(): void
    {
        Document::factory()->create([
            'category' => 'Rules & Regulations',
            'status' => Document::STATUS_PUBLISHED,
        ]);

        $this->assertCount(0, DocumentSections::documents('news.publications'));
    }

    /** Kurasi menang, dan urutannya urutan admin — bukan urutan terbit. */
    public function test_curation_wins_and_keeps_its_own_order(): void
    {
        $older = Document::factory()->create([
            'category' => 'Publication',
            'status' => Document::STATUS_PUBLISHED,
            'published_at' => now()->subYear(),
        ]);
        $newer = Document::factory()->create([
            'category' => 'Publication',
            'status' => Document::STATUS_PUBLISHED,
            'published_at' => now(),
        ]);

        DocumentPlacement::create(['document_id' => $older->id, 'section' => 'news.publications', 'position' => 1]);
        DocumentPlacement::create(['document_id' => $newer->id, 'section' => 'news.publications', 'position' => 2]);

        $this->assertSame(
            [$older->id, $newer->id],
            DocumentSections::documents('news.publications')->pluck('id')->all(),
        );
    }

    /**
     * Rak yang dikurasi lalu isinya diturunkan jadi KOSONG, bukan terisi ulang.
     *
     * Ini bedanya memeriksa "ada baris penempatan" dan "ada dokumen tayang".
     * Yang kedua akan memunculkan dokumen yang tidak pernah dipilih siapa pun di
     * rak yang justru baru saja dikosongkan dengan sengaja.
     */
    public function test_a_curated_shelf_whose_pick_is_pulled_stays_empty(): void
    {
        $picked = Document::factory()->create([
            'category' => 'Publication',
            'status' => Document::STATUS_UNPUBLISHED,
        ]);
        Document::factory()->create([
            'category' => 'Publication',
            'status' => Document::STATUS_PUBLISHED,
        ]);

        DocumentPlacement::create(['document_id' => $picked->id, 'section' => 'news.publications', 'position' => 1]);

        $this->assertCount(0, DocumentSections::documents('news.publications'));
    }

    /** Batas rak ditegakkan saat membaca juga, bukan cuma saat menyimpan. */
    public function test_a_shelf_never_returns_more_than_its_maximum(): void
    {
        $documents = Document::factory()->count(3)->create([
            'category' => 'Rules & Regulations',
            'status' => Document::STATUS_PUBLISHED,
        ]);

        foreach ($documents as $index => $document) {
            DocumentPlacement::create([
                'document_id' => $document->id,
                'section' => 'domino.rulebook',
                'position' => $index + 1,
            ]);
        }

        // `domino.rulebook` = 1: desainnya menggambar satu kartu Official Rulebook.
        $this->assertCount(1, DocumentSections::documents('domino.rulebook'));
    }

    // ------------------------------------------------------------ layar CMS

    /**
     * Layarnya menggambar SEMBILAN raknya sekaligus, dan menandai mana yang
     * masih otomatis.
     *
     * Tanda itu bukan hiasan: rak yang belum dikurasi tampil kosong di layar ini
     * sementara di situs publik ia penuh, dan tanpa kalimat yang menjelaskannya
     * orang menyimpulkan fiturnya rusak.
     */
    public function test_the_screen_lists_every_shelf(): void
    {
        $curated = Document::factory()->create(['category' => 'Publication']);
        DocumentPlacement::create([
            'document_id' => $curated->id,
            'section' => 'news.publications',
            'position' => 1,
        ]);

        $this->actingAs(User::factory()->superAdmin()->create())
            ->get('/documents/sections')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->has('sections', count(self::EXPECTED))
                ->where('sections.0.key', 'home.resources')
                ->where('sections.0.isAuto', true)
                ->where('sections.8.key', 'news.publications')
                ->where('sections.8.isAuto', false)
                ->etc());
    }

    /** Memilih isi rak adalah MENGUBAH — `viewer` tidak boleh. */
    public function test_a_viewer_cannot_change_a_shelf(): void
    {
        $document = Document::factory()->create(['category' => 'Publication']);

        $this->actingAs(User::factory()->withRole('viewer')->create())
            ->put('/documents/sections', ['section' => 'news.publications', 'ids' => [$document->id]])
            ->assertForbidden();

        $this->assertDatabaseCount('document_placements', 0);
    }

    public function test_saving_a_shelf_writes_the_placements_in_order(): void
    {
        $documents = Document::factory()->count(2)->create(['category' => 'Publication']);

        $this->actingAs(User::factory()->superAdmin()->create())
            ->put('/documents/sections', [
                'section' => 'news.publications',
                'ids' => [$documents[1]->id, $documents[0]->id],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(
            [$documents[1]->id, $documents[0]->id],
            DocumentPlacement::query()->where('section', 'news.publications')
                ->orderBy('position')->pluck('document_id')->all(),
        );
    }

    /** Dokumen dari kategori lain ditolak di server, bukan cuma disembunyikan dari picker. */
    public function test_a_document_from_another_category_is_refused(): void
    {
        $document = Document::factory()->create(['category' => 'Rules & Regulations']);

        $this->actingAs(User::factory()->superAdmin()->create())
            ->put('/documents/sections', ['section' => 'news.publications', 'ids' => [$document->id]])
            ->assertSessionHasErrors('ids');

        $this->assertDatabaseCount('document_placements', 0);
    }

    /** Batasnya berbeda per rak, jadi tidak bisa jadi aturan `max:` yang statis. */
    public function test_a_shelf_refuses_more_than_its_own_maximum(): void
    {
        $documents = Document::factory()->count(2)->create(['category' => 'Rules & Regulations']);

        $this->actingAs(User::factory()->superAdmin()->create())
            ->put('/documents/sections', [
                'section' => 'domino.rulebook',
                'ids' => $documents->pluck('id')->all(),
            ])
            ->assertSessionHasErrors('ids');
    }

    // ------------------------------------------------------------ API publik

    public function test_the_public_api_serves_a_shelf_by_key(): void
    {
        $document = Document::factory()->create([
            'category' => 'Publication',
            'status' => Document::STATUS_PUBLISHED,
        ]);

        DocumentPlacement::create([
            'document_id' => $document->id,
            'section' => 'news.publications',
            'position' => 1,
        ]);

        $this->getJson('/api/v1/resources?section=news.publications')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', (string) $document->id);
    }

    /**
     * Section yang tidak dikenal ditolak, bukan dijawab array kosong.
     *
     * Rak kosong menyembunyikan dirinya di situs publik, jadi salah ketik pada
     * `?section=` tidak akan terlihat di satu layar pun.
     */
    public function test_an_unknown_shelf_is_refused_instead_of_answered_empty(): void
    {
        $this->getJson('/api/v1/resources?section=news.publication')
            ->assertStatus(422);
    }

    /** Cara lama tetap hidup — arsip press memintanya. */
    public function test_asking_by_category_still_works(): void
    {
        Document::factory()->count(2)->create([
            'category' => 'Press Releases',
            'status' => Document::STATUS_PUBLISHED,
        ]);

        $this->getJson('/api/v1/resources?category='.urlencode('Press Releases'))
            ->assertOk()
            ->assertJsonCount(2);
    }
}
