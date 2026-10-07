<?php

namespace Tests\Feature\Cms;

use App\Models\SiteSetting;
use App\Models\User;
use App\Support\PageContent;
use App\Support\PreviewToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Editor halaman — skema, draf vs terbit, token pratinjau, hak akses.
 *
 * Kunci field adalah kontrak dengan `landing-page-nuxt`: situs menandai tiap
 * elemen dengan kunci ini dan membaca nilainya lewat `/api/v1/pages/{page}`.
 * Karena itu dieja lengkap — yang mengubahnya dipaksa berhenti dan memutuskan
 * apakah nilai tersimpan ikut dipindah dan apakah situsnya ikut diperbarui.
 */
class PageContentTest extends TestCase
{
    use RefreshDatabase;

    private const ABOUT_KEYS = [
        'header.title', 'header.intro',
        'overview.eyebrow', 'overview.heading',
        'overview.cards.0.title', 'overview.cards.0.body',
        'overview.cards.1.title', 'overview.cards.1.body',
        'heritage.eyebrow', 'heritage.heading',
        'vision.eyebrow', 'vision.heading', 'vision.lead', 'vision.detail',
        'pillars.heading',
        'pillars.items.0.title', 'pillars.items.0.body',
        'pillars.items.1.title', 'pillars.items.1.body',
        'pillars.items.2.title', 'pillars.items.2.body',
        'mission.eyebrow', 'mission.heading', 'mission.intro',
        'mission.cards.0.title', 'mission.cards.0.body',
        'mission.cards.1.title', 'mission.cards.1.body',
        'mission.cards.2.title', 'mission.cards.2.body',
        'mission.cards.3.title', 'mission.cards.3.body',
        'frameworks.heading', 'frameworks.intro', 'frameworks.apex_short', 'frameworks.apex_name',
        'frameworks.federation', 'frameworks.countries', 'frameworks.members',
        'frameworks.members_detail', 'frameworks.caption',
        'boards.heading', 'boards.intro', 'boards.members_label', 'boards.members', 'boards.closing',
        'boards.officers.0.role', 'boards.officers.0.name',
        'boards.officers.1.role', 'boards.officers.1.name',
        'boards.officers.2.role', 'boards.officers.2.name',
        'boards.officers.3.role', 'boards.officers.3.name',
        'boards.officers.4.role', 'boards.officers.4.name',
        'headquarters.headline', 'headquarters.hours', 'headquarters.phone',
    ];

    /**
     * Halaman di luar About (Tahap 2, 2026-09-29) — dieja lengkap dengan alasan
     * yang sama. Diverifikasi terhadap kunci yang benar-benar dibaca situs di
     * mode pratinjau.
     */
    private const OTHER_PAGE_KEYS = [
        'home' => [
            'hero.tagline',
            'hero.headline',
            'hero.mission',
            'hero.accountability',
            'hero.primary_cta',
            'hero.primary_cta_url',
            'hero.secondary_cta',
            'hero.secondary_cta_url',
            'countdown.cta',
            'countdown.days',
            'countdown.hours',
            'countdown.mins',
            'feature.headline',
            'feature.body',
            'feature.cta',
            'partners.heading',
            'resources.heading',
            'resources.intro',
            'faq.heading',
            'faq.view_more',
            'closing.headline',
            'closing.body',
            'closing.cta',
            'closing.cta_url',
        ],
        'domino' => [
            'header.title', 'header.subtitle', 'header.intro', 'formats.panels.0.eyebrow',
            'formats.panels.0.heading', 'formats.panels.0.body', 'formats.panels.0.players_label',
            'formats.panels.0.players_value', 'formats.panels.0.hand_size_label',
            'formats.panels.0.hand_size_value', 'formats.panels.1.eyebrow', 'formats.panels.1.heading',
            'formats.panels.1.body', 'formats.panels.1.players_label', 'formats.panels.1.players_value',
            'formats.panels.1.hand_size_label', 'formats.panels.1.hand_size_value', 'rulebook.heading',
            'rulebook.sets.0.tab', 'rulebook.sets.0.title', 'rulebook.sets.0.body',
            'rulebook.sets.0.quote', 'rulebook.sets.0.cite', 'rulebook.sets.1.tab',
            'rulebook.sets.1.title', 'rulebook.sets.1.body', 'rulebook.sets.1.quote',
            'rulebook.sets.1.cite', 'rulebook.sets.2.tab', 'rulebook.sets.2.title',
            'rulebook.sets.2.body', 'rulebook.sets.2.quote', 'rulebook.sets.2.cite',
            'regulations.rulebook_blurb', 'regulations.heading', 'regulations.intro',
            'regulations.duties.0.text', 'regulations.duties.1.text', 'regulations.duties.2.text',
            'regulations.duties.3.text', 'faq.heading', 'faq.view_more',
        ],
        'tournaments' => [
            'hero.watermark', 'hero.watch_live', 'rail.heading', 'rail.view_all', 'regulations.heading',
            'champions.heading', 'results.heading', 'results.col_year', 'results.col_event',
            'results.col_category', 'results.col_winners', 'results.col_federation', 'results.more',
            'faq.heading', 'faq.view_more',
        ],
        'federation-members' => [
            'hero.title', 'hero.intro', 'hero.cta', 'map.show_all', 'map.tiers.0.label',
            'map.tiers.1.label', 'map.tiers.2.label', 'map.tiers.3.label', 'directory.heading',
            'directory.president_label', 'directory.headquarters_label', 'directory.contact_label',
            'benefits.heading', 'benefits.cards.0.title', 'benefits.cards.0.body',
            'benefits.cards.1.title', 'benefits.cards.1.body', 'benefits.cards.2.title',
            'benefits.cards.2.body', 'process.heading', 'process.intro', 'process.steps.0.title',
            'process.steps.0.body', 'process.steps.1.title', 'process.steps.1.body',
            'process.steps.2.title', 'process.steps.2.body', 'process.steps.3.title',
            'process.steps.3.body', 'cta.heading', 'cta.intro', 'cta.button',
        ],
        'player-membership' => [
            'hero.title', 'hero.body', 'hero.cta', 'what_is.heading', 'what_is.lead', 'what_is.body',
            'benefits.heading', 'benefits.cards.0.title', 'benefits.cards.0.body',
            'benefits.cards.1.title', 'benefits.cards.1.body', 'benefits.cards.2.title',
            'benefits.cards.2.body', 'benefits.cards.3.title', 'benefits.cards.3.body',
            'benefits.cards.4.title', 'benefits.cards.4.body', 'benefits.cards.5.title',
            'benefits.cards.5.body', 'apply.eligibility_heading', 'apply.eligibility_intro',
            'apply.requirements', 'apply.process_heading', 'apply.process_intro', 'apply.steps.0.title',
            'apply.steps.0.body', 'apply.steps.1.title', 'apply.steps.1.body', 'apply.steps.2.title',
            'apply.steps.2.body', 'apply.steps.3.title', 'apply.steps.3.body', 'cta.headline',
            'cta.body',
        ],
        'development' => [
            'header.title', 'header.intro', 'youth.eyebrow', 'youth.heading', 'youth.intro',
            'youth.download_cta', 'youth.stats.0.figure', 'youth.stats.0.label', 'youth.stats.1.figure',
            'youth.stats.1.label', 'certifications.eyebrow', 'certifications.heading',
            'certifications.grade_word', 'certifications.grades.0.name',
            'certifications.grades.0.scope', 'certifications.grades.1.name',
            'certifications.grades.1.scope', 'certifications.grades.2.name',
            'certifications.grades.2.scope', 'certifications.c_levels.0.marker',
            'certifications.c_levels.0.title', 'certifications.c_levels.0.body',
            'certifications.c_levels.1.marker', 'certifications.c_levels.1.title',
            'certifications.c_levels.1.body', 'certifications.c_levels.2.marker',
            'certifications.c_levels.2.title', 'certifications.c_levels.2.body',
            'certifications.b_levels.0.marker', 'certifications.b_levels.0.title',
            'certifications.b_levels.0.body', 'certifications.b_levels.1.marker',
            'certifications.b_levels.1.title', 'certifications.b_levels.1.body',
            'certifications.b_levels.2.marker', 'certifications.b_levels.2.title',
            'certifications.b_levels.2.body', 'certifications.a_levels.0.marker',
            'certifications.a_levels.0.title', 'certifications.a_levels.0.body',
            'certifications.a_levels.1.marker', 'certifications.a_levels.1.title',
            'certifications.a_levels.1.body', 'certifications.a_levels.2.marker',
            'certifications.a_levels.2.title', 'certifications.a_levels.2.body', 'library.eyebrow',
            'library.heading', 'grassroots.eyebrow', 'grassroots.heading', 'grassroots.view_all',
            'grassroots.cards.0.title', 'grassroots.cards.0.body', 'grassroots.cards.1.title',
            'grassroots.cards.1.body', 'grassroots.cards.2.title', 'grassroots.cards.2.body',
            'support.eyebrow', 'support.heading', 'support.intro', 'support.form_heading',
            'support.form_intro', 'support.federation_label', 'support.email_label',
            'support.needs_label', 'support.submit', 'support.benefits.0.text',
            'support.benefits.1.text', 'support.benefits.2.text', 'cta.heading', 'cta.body',
            'cta.button',
        ],
        'governance' => [
            'header.title', 'header.eyebrow', 'header.intro', 'overview.eyebrow', 'overview.heading',
            'overview.role_label', 'overview.role', 'overview.commitments_label',
            'overview.commitments', 'committees.heading', 'documents.heading', 'documents.intro',
            'strategy.heading', 'strategy.intro',
        ],
        'integrity' => [
            'header.title', 'header.eyebrow', 'header.intro', 'principles.heading',
            'principles.items.0.label', 'principles.items.0.detail', 'principles.items.1.label',
            'principles.items.1.detail', 'principles.items.2.label', 'principles.items.2.detail',
            'principles.items.3.label', 'principles.items.3.detail', 'ethics.heading',
            'ethics.clauses.0.title', 'ethics.clauses.0.body', 'ethics.clauses.1.title',
            'ethics.clauses.1.body', 'ethics.clauses.2.title', 'ethics.clauses.2.body',
            'measures.heading', 'measures.intro', 'measures.cards.0.title', 'measures.cards.0.detail',
            'measures.cards.1.title', 'measures.cards.1.detail', 'measures.cards.2.title',
            'measures.cards.2.detail', 'measures.cards.3.title', 'measures.cards.3.detail',
            'flow.heading', 'flow.intro', 'flow.steps.0.title', 'flow.steps.0.detail',
            'flow.steps.1.title', 'flow.steps.1.detail', 'flow.steps.2.title', 'flow.steps.2.detail',
            'flow.steps.3.title', 'flow.steps.3.detail', 'report.heading', 'report.intro',
            'report.form_heading', 'report.type_label', 'report.type_placeholder',
            'report.description_label', 'report.description_placeholder', 'report.submit',
        ],
    ];

    private function editor(): User
    {
        return User::factory()->withRole('editor')->create();
    }

    // ---------------------------------------------------------------- skema

    public function test_the_about_keys_are_exactly_what_the_public_site_marks(): void
    {
        $this->assertSame(self::ABOUT_KEYS, array_keys(PageContent::fields('about')));
    }

    public function test_every_other_page_keys_are_exactly_what_the_public_site_marks(): void
    {
        foreach (self::OTHER_PAGE_KEYS as $page => $keys) {
            $this->assertSame($keys, array_keys(PageContent::fields($page)), $page);
        }
    }

    public function test_the_editor_lists_every_page_the_site_reads(): void
    {
        $this->assertSame(
            ['home', 'about', 'domino', 'tournaments', 'federation-members', 'player-membership', 'development', 'governance', 'integrity'],
            array_keys(PageContent::pages()),
        );
    }

    public function test_every_field_has_a_known_type_and_a_limit(): void
    {
        foreach (array_keys(PageContent::pages()) as $page) {
            foreach (PageContent::fields($page) as $key => $field) {
                $this->assertContains($field['type'], ['text', 'textarea', 'lines', 'url'], "{$page}.{$key}");
                $this->assertGreaterThan(0, $field['max'], "{$page}.{$key}");
            }
        }
    }

    public function test_every_elsewhere_link_is_a_real_backoffice_screen(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());

        foreach (array_keys(PageContent::pages()) as $page) {
            foreach (PageContent::sections($page) as $section) {
                foreach ($section['elsewhere'] as $link) {
                    $this->get($link['href'])->assertOk();
                }
            }
        }
    }

    public function test_every_page_editor_screen_opens(): void
    {
        $this->actingAs($this->editor());

        foreach (PageContent::pages() as $page => $meta) {
            $this->get("/pages/{$page}")->assertOk();
            $this->getJson("/api/v1/pages/{$page}")->assertOk();
        }
    }

    // -------------------------------------------------- draf dan terbit

    public function test_a_draft_does_not_reach_the_public_endpoint(): void
    {
        $this->actingAs($this->editor())
            ->put('/pages/about/draft', ['values' => ['heritage.heading' => 'Draft heading']])
            ->assertRedirect();

        $this->getJson('/api/v1/pages/about')
            ->assertOk()
            ->assertExactJson(['values' => []]);
    }

    public function test_publishing_puts_the_text_on_the_public_endpoint(): void
    {
        $this->actingAs($this->editor())
            ->post('/pages/about/publish', ['values' => [
                'heritage.heading' => 'A Global Movement',
                'header.title' => "Connecting the World\nThrough Dominoes",
            ]])
            ->assertRedirect();

        $values = $this->getJson('/api/v1/pages/about')->assertOk()->json('values');

        $this->assertSame('A Global Movement', $values['heritage.heading']);
        // `lines` sampai di situs sebagai larik, satu baris per unsur.
        $this->assertSame(['Connecting the World', 'Through Dominoes'], $values['header.title']);

        $this->assertNull(SiteSetting::query()->find('about.heritage.heading')->draft);
    }

    public function test_publishing_is_logged_once_with_what_changed(): void
    {
        $this->actingAs($this->editor())
            ->post('/pages/about/publish', ['values' => [
                'heritage.heading' => 'A Global Movement',
                'heritage.eyebrow' => 'Our Journey',
            ]]);

        $this->assertSame(1, \DB::table('activity_log')->where('log_name', 'page-editor')->where('event', 'published')->count());
    }

    public function test_text_equal_to_what_is_live_leaves_no_draft(): void
    {
        SiteSetting::query()->create(['key' => 'about.heritage.heading', 'group' => 'page.about', 'value' => 'Same']);

        $this->actingAs($this->editor())
            ->put('/pages/about/draft', ['values' => ['heritage.heading' => '  Same  ']]);

        $this->assertSame([], PageContent::drafts('about'));
    }

    public function test_emptying_a_field_falls_back_to_the_built_in_text_once_published(): void
    {
        SiteSetting::query()->create(['key' => 'about.heritage.heading', 'group' => 'page.about', 'value' => 'Old']);

        $this->actingAs($this->editor())
            ->post('/pages/about/publish', ['values' => ['heritage.heading' => '']]);

        $this->getJson('/api/v1/pages/about')->assertExactJson(['values' => []]);
    }

    public function test_discarding_returns_the_page_to_what_is_live(): void
    {
        $this->actingAs($this->editor())
            ->put('/pages/about/draft', ['values' => ['heritage.heading' => 'Draft']]);

        $this->delete('/pages/about/draft')->assertRedirect();

        $this->assertSame([], PageContent::drafts('about'));
    }

    // ------------------------------------------------------------ pratinjau

    public function test_a_valid_preview_token_opens_the_draft(): void
    {
        $this->actingAs($this->editor())
            ->put('/pages/about/draft', ['values' => ['heritage.heading' => 'Draft heading']]);

        $response = $this->getJson('/api/v1/pages/about?preview='.urlencode(PreviewToken::make('about')))
            ->assertOk();

        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertSame('Draft heading', $response->json('values')['heritage.heading']);
    }

    public function test_a_token_for_another_page_or_a_forged_one_shows_the_live_text(): void
    {
        $this->actingAs($this->editor())
            ->put('/pages/about/draft', ['values' => ['heritage.heading' => 'Draft heading']]);

        [$page, $expires] = explode('.', PreviewToken::make('about'));

        foreach ([
            PreviewToken::make('integrity'),
            "{$page}.{$expires}.forged",
            'garbage',
        ] as $token) {
            $this->getJson('/api/v1/pages/about?preview='.urlencode($token))
                ->assertOk()
                ->assertExactJson(['values' => []]);
        }
    }

    public function test_an_expired_token_shows_the_live_text(): void
    {
        $token = PreviewToken::make('about');

        $this->travel(PreviewToken::MINUTES + 1)->minutes();

        $this->assertFalse(PreviewToken::valid($token, 'about'));
    }

    // ------------------------------------------------------------- validasi

    /**
     * Batas di skema adalah panduan (2026-10-07): layar editor minta konfirmasi,
     * tapi teks yang lebih panjang tetap tersimpan — termasuk baris judul.
     */
    public function test_text_over_the_guide_limit_still_saves(): void
    {
        $this->actingAs($this->editor())
            ->put('/pages/about/draft', ['values' => [
                'heritage.heading' => str_repeat('x', 41),
                'header.title' => str_repeat('y', 60),
            ]])
            ->assertSessionHasNoErrors();

        $this->assertSame(str_repeat('x', 41), PageContent::drafts('about')['heritage.heading']);
    }

    public function test_text_past_the_safety_cap_is_refused(): void
    {
        $this->actingAs($this->editor())
            ->put('/pages/about/draft', ['values' => ['heritage.heading' => str_repeat('x', PageContent::HARD_MAX + 1)]])
            ->assertSessionHasErrors('values.heritage.heading');
    }

    public function test_a_heading_with_too_many_lines_is_refused(): void
    {
        $this->actingAs($this->editor())
            ->put('/pages/about/draft', ['values' => ['header.title' => "One\nTwo\nThree\nFour"]])
            ->assertSessionHasErrors('values.header.title');
    }

    public function test_the_chart_needs_exactly_three_countries(): void
    {
        $this->actingAs($this->editor())
            ->put('/pages/about/draft', ['values' => ['frameworks.countries' => "A\nB"]])
            ->assertSessionHasErrors('values.frameworks.countries');
    }

    // --------------------------------------------------------------- layar

    public function test_the_editor_screen_carries_the_preview_and_the_elsewhere_links(): void
    {
        $this->actingAs($this->editor())
            ->get('/pages/about')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->component('Pages/Edit')
                ->where('page.path', '/about')
                ->where('previewUrl', fn (string $url) => str_contains($url, '/about?cms-preview=about.'))
                ->where('sections.2.key', 'heritage')
                ->where('sections.2.elsewhere.0.href', '/blocks/heritage')
                ->etc());
    }

    public function test_an_unknown_page_is_a_404_on_both_sides(): void
    {
        $this->actingAs($this->editor())->get('/pages/nope')->assertNotFound();
        $this->getJson('/api/v1/pages/nope')->assertNotFound();
    }

    // ----------------------------------------------------------- hak akses

    public function test_a_viewer_can_look_but_not_publish(): void
    {
        $viewer = User::factory()->withRole('viewer')->create();

        $this->actingAs($viewer)->get('/pages/about')->assertOk();
        $this->actingAs($viewer)
            ->post('/pages/about/publish', ['values' => ['heritage.heading' => 'X']])
            ->assertForbidden();
    }
}
