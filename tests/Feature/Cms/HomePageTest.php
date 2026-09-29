<?php

namespace Tests\Feature\Cms;

use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Naskah beranda — sejak 2026-09-29 disunting di Editor Halaman (`/pages/home`).
 *
 * Yang dijaga di sini adalah perpindahannya: layar lama mengalihkan, isi lama
 * ikut pindah, dan `/api/v1/home` tetap menjawab dengan bentuk lamanya untuk
 * situs yang belum di-deploy ulang.
 */
class HomePageTest extends TestCase
{
    use RefreshDatabase;

    private function editor(): User
    {
        return User::factory()->withRole('editor')->create();
    }

    public function test_the_old_screen_sends_people_to_the_page_editor(): void
    {
        $this->actingAs($this->editor())
            ->get('/home-page')
            ->assertRedirect('/pages/home');
    }

    public function test_the_stored_copy_moves_to_the_page_editor_keys(): void
    {
        SiteSetting::putMany([
            'hero_headline' => 'Dominoes Without Borders',
            'closing_headline' => "Join DWF Through\nYour National Federation",
            'hero_primary_cta_url' => '/federation-members',
            'hero_tagline' => '',
        ], SiteSetting::GROUP_HOME);

        (require database_path('migrations/2026_09_29_120000_move_home_copy_to_page_editor.php'))->up();

        $this->assertSame('Dominoes Without Borders', SiteSetting::query()->find('home.hero.headline')->value);
        $this->assertSame('page.home', SiteSetting::query()->find('home.hero.headline')->group);
        $this->assertSame("Join DWF Through\nYour National Federation", SiteSetting::query()->find('home.closing.headline')->value);
        // Terbit, bukan draf: itulah yang sedang tayang.
        $this->assertNull(SiteSetting::query()->find('home.hero.headline')->draft);
        // Yang kosong tidak disalin — situs memakai bawaannya.
        $this->assertNull(SiteSetting::query()->find('home.hero.tagline'));
    }

    public function test_the_move_does_not_overwrite_what_the_editor_already_holds(): void
    {
        SiteSetting::putMany(['hero_headline' => 'Old'], SiteSetting::GROUP_HOME);
        SiteSetting::query()->create(['key' => 'home.hero.headline', 'group' => 'page.home', 'value' => 'Newer']);

        (require database_path('migrations/2026_09_29_120000_move_home_copy_to_page_editor.php'))->up();

        $this->assertSame('Newer', SiteSetting::query()->find('home.hero.headline')->value);
    }

    public function test_the_old_endpoint_keeps_its_shape_for_a_site_not_yet_redeployed(): void
    {
        $this->actingAs($this->editor())
            ->post('/pages/home/publish', ['values' => [
                'hero.headline' => 'Dominoes Without Borders',
                'hero.primary_cta_url' => '/federation-members',
                'closing.headline' => "Bring Your Nation\nTo The World Stage",
            ]])
            ->assertRedirect();

        $this->getJson('/api/v1/home')
            ->assertOk()
            ->assertJsonPath('hero.headline', 'Dominoes Without Borders')
            ->assertJsonPath('hero.primaryCtaUrl', '/federation-members')
            ->assertJsonPath('closing.headline', ['Bring Your Nation', 'To The World Stage']);
    }

    public function test_the_old_endpoint_survives_an_empty_table(): void
    {
        $this->getJson('/api/v1/home')->assertOk()->assertExactJson(['hero' => [], 'closing' => []]);
    }

    public function test_a_button_link_must_be_a_path_an_anchor_or_a_url(): void
    {
        $this->actingAs($this->editor())
            ->put('/pages/home/draft', ['values' => ['hero.primary_cta_url' => 'federation members']])
            ->assertSessionHasErrors('values.hero.primary_cta_url');

        foreach (['/federation-members', '#', 'https://example.org/x'] as $ok) {
            $this->put('/pages/home/draft', ['values' => ['hero.primary_cta_url' => $ok]])
                ->assertSessionHasNoErrors();
        }
    }

    public function test_home_copy_does_not_leak_into_the_settings_endpoint(): void
    {
        $this->actingAs($this->editor())
            ->post('/pages/home/publish', ['values' => ['hero.headline' => 'Dominoes Without Borders']]);

        $this->getJson('/api/v1/settings')->assertOk()->assertJsonMissing(['Dominoes Without Borders']);
    }
}
