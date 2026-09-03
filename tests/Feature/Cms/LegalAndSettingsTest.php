<?php

namespace Tests\Feature\Cms;

use App\Models\ContactMessage;
use App\Models\LegalPage;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class LegalAndSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_legal_page_is_created_on_first_visit(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create())
            ->get('/legal-pages/privacy-policy')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p->component('Legal/Form'));

        $this->assertDatabaseHas('legal_pages', ['key' => 'privacy-policy']);
    }

    public function test_an_unknown_legal_page_is_a_404(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create())
            ->get('/legal-pages/refund-policy')
            ->assertNotFound();
    }

    /**
     * Cookie Policy halaman ketiga, bukan kategori di dalam salah satu yang
     * lain: rute publiknya `/page/cookie-policy` berdiri sendiri.
     */
    public function test_the_cookie_policy_page_exists(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create())
            ->get('/legal-pages/cookie-policy')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p->component('Legal/Form'));

        $this->assertDatabaseHas('legal_pages', ['key' => 'cookie-policy']);
    }

    public function test_saving_blocks_replaces_them_in_order(): void
    {
        $user = User::factory()->superAdmin()->create();
        $this->actingAs($user)->get('/legal-pages/terms');

        $this->actingAs($user)->put('/legal-pages/terms', [
            'slug' => 'terms',
            'last_updated_at' => '2026-08-01',
            'blocks' => [
                ['title' => 'Acceptance', 'description' => '<p>Satu</p>', 'is_active' => true],
                ['title' => 'Usage', 'description' => '<p>Dua</p>', 'is_active' => false],
            ],
        ])->assertRedirect();

        $page = LegalPage::query()->where('key', 'terms')->first();
        $blocks = $page->blocks;

        $this->assertCount(2, $blocks);
        $this->assertSame('Acceptance', $blocks[0]->title);
        $this->assertSame(1, $blocks[0]->position);
        $this->assertSame(2, $blocks[1]->position);
        $this->assertFalse($blocks[1]->is_active);
    }

    public function test_block_descriptions_keep_basic_formatting(): void
    {
        $user = User::factory()->superAdmin()->create();
        $this->actingAs($user)->get('/legal-pages/terms');

        $this->actingAs($user)->put('/legal-pages/terms', [
            'slug' => 'terms',
            'last_updated_at' => '2026-08-01',
            'blocks' => [[
                'title' => 'X',
                'description' => '<p>Isi <strong>tebal</strong> dan <em>miring</em>.</p><ul><li>Butir</li></ul>',
                'is_active' => true,
            ]],
        ]);

        $description = LegalPage::query()->where('key', 'terms')->first()->blocks[0]->description;

        /*
         * Kontrolnya editor teks kaya DASAR, jadi tag penandanya bertahan —
         * membalik keputusan lama yang menyimpannya sebagai teks polos lewat
         * `strip_tags()`. Alasan lama (textarea + `AutoParagraph` menaruh `<p>`
         * yang terketik di dalam kotaknya sendiri) tidak berlaku lagi begitu
         * kotaknya bukan textarea.
         */
        $this->assertStringContainsString('<strong>tebal</strong>', $description);
        $this->assertStringContainsString('<em>miring</em>', $description);
        $this->assertStringContainsString('<li>Butir</li>', $description);
    }

    /**
     * Yang boleh lewat cuma penanda dasar.
     *
     * Profil `legal` menyempit ke daftar yang SAMA dengan tombol yang digambar
     * `RichTextEditor variant="basic"`. Tombol yang ada di editor tapi tidak di
     * daftar ini akan menghapus pekerjaan penulisnya saat disimpan, tanpa satu
     * pun pesan — karena itu judul, gambar, dan sorot dibuang dari kedua sisi
     * sekaligus.
     */
    public function test_block_descriptions_drop_scripts_and_anything_beyond_basic_marks(): void
    {
        $user = User::factory()->superAdmin()->create();
        $this->actingAs($user)->get('/legal-pages/terms');

        $this->actingAs($user)->put('/legal-pages/terms', [
            'slug' => 'terms',
            'last_updated_at' => '2026-08-01',
            'blocks' => [[
                'title' => 'X',
                'description' => '<h2>Judul</h2><script>alert(1)</script><img src="x.png">'
                    .'<mark>sorot</mark><p onclick="steal()">Aman</p>',
                'is_active' => true,
            ]],
        ]);

        $description = LegalPage::query()->where('key', 'terms')->first()->blocks[0]->description;

        $this->assertStringNotContainsString('<script', $description);
        $this->assertStringNotContainsString('<img', $description);
        $this->assertStringNotContainsString('<h2', $description);
        $this->assertStringNotContainsString('<mark', $description);
        $this->assertStringNotContainsString('onclick', $description);
        $this->assertStringContainsString('Aman', $description);
    }

    public function test_the_page_remembers_who_changed_it_last(): void
    {
        $rina = User::factory()->superAdmin()->create(['name' => 'Rina']);
        $this->actingAs($rina)->get('/legal-pages/terms');

        $this->actingAs($rina)->put('/legal-pages/terms', [
            'slug' => 'terms',
            'last_updated_at' => '2026-08-01',
            'blocks' => [['title' => 'X', 'description' => 'Isi.', 'is_active' => true]],
        ])->assertRedirect();

        $this->assertSame($rina->id, LegalPage::query()->where('key', 'terms')->first()->updated_by_id);

        // Dan namanya sampai ke layar, di bawah judulnya.
        $this->actingAs($rina)
            ->get('/legal-pages/terms')
            ->assertInertia(fn (AssertableInertia $p) => $p->where('page.lastModifiedBy', 'Rina')
                ->has('page.lastModifiedAt'));
    }

    public function test_last_modified_moves_when_only_the_blocks_change(): void
    {
        $user = User::factory()->superAdmin()->create();
        $this->actingAs($user)->get('/legal-pages/terms');

        $page = LegalPage::query()->where('key', 'terms')->firstOrFail();
        $page->forceFill(['updated_at' => now()->subDay()])->saveQuietly();
        $before = $page->fresh()->updated_at;

        // Slug dan tanggalnya SENGAJA tidak diubah. Tanpa `touch()` di
        // controller, Eloquent melewati baris induknya karena tidak ada satu
        // kolom pun yang kotor — dan "Last Modified" membeku persis pada
        // penyuntingan yang paling sering terjadi.
        $this->actingAs($user)->put('/legal-pages/terms', [
            'slug' => $page->slug,
            'last_updated_at' => $page->last_updated_at?->toDateString() ?? '2026-08-01',
            'blocks' => [['title' => 'Isi baru', 'description' => 'Teks.', 'is_active' => true]],
        ])->assertRedirect();

        $this->assertTrue($page->fresh()->updated_at->greaterThan($before));
    }

    public function test_contact_settings_round_trip(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create())->put('/contact-social', [
            'primary_email' => 'contact@dwf-domino.org',
            'footer_address_label' => 'Headquarters, Lausanne, CH',
            'headquarters_address' => 'Maison du Sport International',
            'form_recipient_email' => 'inbox@dwf-domino.org',
            'social_instagram' => 'dwf',
            'social_tiktok' => null,
            'social_x' => null,
            'social_facebook' => null,
            'social_youtube' => null,
        ])->assertRedirect();

        $this->assertSame('contact@dwf-domino.org', SiteSetting::map(SiteSetting::GROUP_CONTACT)['primary_email']);
        $this->assertSame('dwf', SiteSetting::map(SiteSetting::GROUP_CONTACT)['social_instagram']);
    }

    public function test_an_invalid_email_is_rejected(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create())
            ->put('/contact-social', [
                'primary_email' => 'bukan-email',
                'footer_address_label' => 'X',
                'headquarters_address' => 'Y',
                'form_recipient_email' => 'inbox@dwf-domino.org',
            ])
            ->assertSessionHasErrors('primary_email');
    }

    public function test_opening_a_message_marks_it_read(): void
    {
        $message = ContactMessage::query()->create([
            'name' => 'Kenji Mori', 'email' => 'kenji@example.com', 'country' => 'Japan',
            'topic' => 'Media Requests', 'subject' => 'Interview request', 'message' => 'Halo',
        ]);

        $this->assertNull($message->read_at);

        $this->actingAs(User::factory()->superAdmin()->create())
            ->get("/contact-messages/{$message->id}")
            ->assertOk();

        $this->assertNotNull($message->fresh()->read_at);
    }

    public function test_the_unread_filter_and_counter_agree(): void
    {
        ContactMessage::query()->create([
            'name' => 'A', 'email' => 'a@example.com', 'subject' => 'S1', 'message' => 'm',
        ]);
        ContactMessage::query()->create([
            'name' => 'B', 'email' => 'b@example.com', 'subject' => 'S2', 'message' => 'm',
            'read_at' => now(),
        ]);

        $this->actingAs(User::factory()->superAdmin()->create())
            ->get('/contact-messages?status=unread')
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->has('messages.data', 1)
                ->where('unreadCount', 1));
    }
}
