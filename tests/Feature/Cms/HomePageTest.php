<?php

namespace Tests\Feature\Cms;

use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Halaman Depan.
 *
 * Layar ini menggantikan grup "Landing Page" berisi delapan submenu placeholder
 * (`252:3403`). Yang dikunci di sini: batasnya (hanya naskah yang tidak dimiliki
 * modul lain), pemisahan kelompok dari pengaturan kontak, dan bentuk response
 * yang dibaca situs publik.
 */
class HomePageTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, string> */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'hero_tagline' => 'Domino World Federation',
            'hero_headline' => 'Dominoes Without Borders',
            'hero_mission' => 'To unite the world through dominoes.',
            'hero_accountability' => 'Designed and operates under a rigorous framework.',
            'hero_primary_cta' => 'Explore Membership',
            'hero_primary_cta_url' => '/federation-members',
            'hero_secondary_cta' => 'Official Rules',
            'hero_secondary_cta_url' => '#',
            'closing_headline' => "Bring Your Nation\nTo The World Stage",
            'closing_body' => 'We are accepting applications.',
            'closing_cta' => 'Get In Touch',
            'closing_cta_url' => '/contact',
        ], $overrides);
    }

    public function test_the_copy_round_trips(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create())
            ->put('/home-page', $this->payload())
            ->assertSessionHasNoErrors();

        $stored = SiteSetting::map(SiteSetting::GROUP_HOME);

        $this->assertSame('Dominoes Without Borders', $stored['hero_headline']);
        $this->assertSame('/contact', $stored['closing_cta_url']);
    }

    /**
     * Tautan yang salah ketik gagal DIAM-DIAM: tombolnya tetap tergambar dan
     * tetap bisa ditekan, cuma tidak sampai ke mana-mana.
     */
    public function test_a_button_link_must_be_a_path_an_anchor_or_a_url(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create())
            ->put('/home-page', $this->payload(['hero_primary_cta_url' => 'federation members']))
            ->assertSessionHasErrors('hero_primary_cta_url');
    }

    /**
     * Naskah halaman depan dan pengaturan kontak tinggal di TABEL yang sama,
     * dipisah kolom `group`. Tanpa pemisahan itu, `/api/v1/settings` akan
     * mengirim headline hero ke footer yang cuma butuh alamat surel.
     */
    public function test_home_copy_does_not_leak_into_the_settings_endpoint(): void
    {
        SiteSetting::putMany(['primary_email' => 'contact@dwf-domino.org'], SiteSetting::GROUP_CONTACT);

        $this->actingAs(User::factory()->superAdmin()->create())->put('/home-page', $this->payload());

        $settings = $this->getJson('/api/v1/settings')->assertOk()->json();

        $this->assertArrayHasKey('primary_email', $settings);
        $this->assertArrayNotHasKey('hero_headline', $settings);
    }

    /**
     * Headline penutup dipecah jadi LARIK.
     *
     * Figma memutus barisnya secara eksplisit (`56:4683`) dan putusan itu bagian
     * dari komposisinya — dua baris berbobot sama, di tengah. Mengirimnya
     * sebagai satu kalimat berarti `<br>` yang harus dibawa-bawa penerjemah.
     */
    public function test_the_api_splits_the_closing_headline_into_lines(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create())->put('/home-page', $this->payload());

        $body = $this->getJson('/api/v1/home')->assertOk()->json();

        $this->assertSame('Dominoes Without Borders', $body['hero']['headline']);
        $this->assertSame('/federation-members', $body['hero']['primaryCtaUrl']);
        $this->assertSame(['Bring Your Nation', 'To The World Stage'], $body['closing']['headline']);
    }

    /** Tanpa satu baris pun, bentuknya tetap dua objek — bukan larik kosong. */
    public function test_the_api_shape_survives_an_empty_table(): void
    {
        $body = $this->getJson('/api/v1/home')->assertOk()->json();

        $this->assertSame([], $body['hero']);
        $this->assertSame([], $body['closing']);
    }

    public function test_a_viewer_cannot_change_the_home_page(): void
    {
        $this->actingAs(User::factory()->withRole('viewer')->create())
            ->put('/home-page', $this->payload())
            ->assertForbidden();
    }
}
