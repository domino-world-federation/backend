<?php

namespace Tests\Feature\Cms;

use App\Models\PageMeta;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * SEO & Social.
 *
 * Yang dikunci di sini: baris bawaan `*` yang tidak boleh hilang atau berpindah
 * rute, format rute yang salah ketiknya gagal DIAM-DIAM, dan bentuk response
 * API yang dibaca situs publik.
 */
class SeoTest extends TestCase
{
    use RefreshDatabase;

    private function actor(): User
    {
        return User::factory()->superAdmin()->create();
    }

    /** @param array<string, mixed> $overrides @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'route' => '/about',
            'label' => 'About Us',
            'title' => 'About Us | Domino World Federation',
            'description' => 'The international governing body for the sport of dominoes.',
        ], $overrides);
    }

    public function test_a_page_can_be_added(): void
    {
        $this->actingAs($this->actor())
            ->post('/seo-social', $this->payload())
            ->assertSessionHasNoErrors();

        $this->assertSame('/about', PageMeta::query()->sole()->route);
    }

    /**
     * Rute yang salah ketik gagal DIAM-DIAM: ia cuma baris yang tidak pernah
     * cocok, dan halamannya memakai bawaan selamanya. Regex-nya yang membuatnya
     * berisik.
     */
    public function test_a_route_must_be_a_path_not_a_url(): void
    {
        $this->actingAs($this->actor())
            ->post('/seo-social', $this->payload(['route' => 'https://dwf-domino.org/about']))
            ->assertSessionHasErrors('route');
    }

    public function test_a_route_with_spaces_is_refused(): void
    {
        $this->actingAs($this->actor())
            ->post('/seo-social', $this->payload(['route' => '/about us']))
            ->assertSessionHasErrors('route');
    }

    public function test_two_rows_cannot_claim_the_same_route(): void
    {
        $actor = $this->actor();

        $this->actingAs($actor)->post('/seo-social', $this->payload());
        $this->actingAs($actor)->post('/seo-social', $this->payload(['label' => 'Duplikat']))
            ->assertSessionHasErrors('route');
    }

    // ------------------------------------------------------ baris bawaan

    /** Bawaan situs tidak bisa dihapus — semua halaman bergantung padanya. */
    public function test_the_site_default_cannot_be_deleted(): void
    {
        $fallback = PageMeta::query()->create([
            'route' => PageMeta::DEFAULT_ROUTE, 'label' => 'Site default', 'title' => 'DWF',
        ]);

        $this->actingAs($this->actor())
            ->delete("/seo-social/{$fallback->id}")
            ->assertSessionHasErrors('page');

        $this->assertModelExists($fallback);
    }

    /**
     * Rutenya juga tidak bisa dipindah.
     *
     * Mengubah `*` jadi path biasa menghilangkan cadangan seluruh situs
     * sekaligus, dan tidak ada satu layar pun yang memberi tahu.
     */
    public function test_the_site_default_route_cannot_be_moved(): void
    {
        $fallback = PageMeta::query()->create([
            'route' => PageMeta::DEFAULT_ROUTE, 'label' => 'Site default', 'title' => 'DWF',
        ]);

        $this->actingAs($this->actor())->post("/seo-social/{$fallback->id}", [
            'route' => '/about',
            'label' => 'Dipindah',
            'title' => 'DWF',
        ])->assertSessionHasNoErrors();

        $this->assertSame(PageMeta::DEFAULT_ROUTE, $fallback->fresh()->route);
        $this->assertSame('Dipindah', $fallback->fresh()->label);
    }

    // -------------------------------------------------------------- berkas

    public function test_replacing_the_share_image_removes_the_old_file(): void
    {
        Storage::fake('public');
        $actor = $this->actor();

        $this->actingAs($actor)->post('/seo-social', $this->payload([
            'og_image' => UploadedFile::fake()->image('a.webp', 1200, 630),
        ]));

        $page = PageMeta::query()->sole();
        $first = $page->og_image_path;
        Storage::disk('public')->assertExists($first);

        $this->actingAs($actor)->post("/seo-social/{$page->id}", $this->payload([
            'og_image' => UploadedFile::fake()->image('b.webp', 1200, 630),
        ]));

        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($page->fresh()->og_image_path);
    }

    // ----------------------------------------------------------------- API

    /**
     * Bentuknya `{ default, pages }` — halaman mencari rutenya sendiri lalu
     * jatuh ke `default` untuk tiap field yang kosong.
     */
    public function test_the_api_separates_the_default_from_the_pages(): void
    {
        PageMeta::query()->create([
            'route' => PageMeta::DEFAULT_ROUTE, 'label' => 'Site default',
            'title' => 'Domino World Federation', 'description' => 'Bawaan.',
        ]);
        PageMeta::query()->create(['route' => '/about', 'label' => 'About', 'title' => 'About | DWF']);

        $body = $this->getJson('/api/v1/seo')->assertOk()->json();

        $this->assertSame('Domino World Federation', $body['default']['title']);
        $this->assertSame('About | DWF', $body['pages']['/about']['title']);
        $this->assertArrayNotHasKey(PageMeta::DEFAULT_ROUTE, $body['pages']);

        // Field kosong DIHILANGKAN, bukan `null` — halaman lalu bisa memakai
        // `??` untuk jatuh ke bawaannya tanpa memeriksa dua keadaan.
        $this->assertArrayNotHasKey('description', $body['pages']['/about']);
    }

    /** Tanpa satu baris pun, bentuknya tetap objek — bukan array kosong. */
    public function test_the_api_shape_survives_an_empty_table(): void
    {
        $body = $this->getJson('/api/v1/seo')->assertOk()->json();

        $this->assertSame([], $body['default']);
        $this->assertSame([], $body['pages']);
    }

    // ---------------------------------------------------------------- izin

    // ------------------------------------------------------ layar sendiri

    /**
     * Tambah dan ubah punya layarnya SENDIRI, bukan formulir yang terbuka di
     * atas daftarnya (permintaan pemilik repo 2026-09-03).
     */
    public function test_the_add_screen_opens_empty(): void
    {
        $this->actingAs($this->actor())
            ->get('/seo-social/create')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->component('Settings/SeoForm')
                ->where('page', null));
    }

    public function test_the_edit_screen_carries_the_row(): void
    {
        $page = PageMeta::query()->create(['route' => '/about', 'label' => 'About', 'title' => 'About | DWF']);

        $this->actingAs($this->actor())
            ->get("/seo-social/{$page->id}/edit")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->component('Settings/SeoForm')
                ->where('page.route', '/about')
                ->where('page.isDefault', false));
    }

    /**
     * Baris bawaan memakai layar yang sama, dan `isDefault` yang memberi tahu
     * layar itu untuk TIDAK menggambar field Route.
     */
    public function test_the_fallback_uses_the_same_screen(): void
    {
        $fallback = PageMeta::query()->create([
            'route' => PageMeta::DEFAULT_ROUTE, 'label' => 'Site default', 'title' => 'DWF',
        ]);

        $this->actingAs($this->actor())
            ->get("/seo-social/{$fallback->id}/edit")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p->where('page.isDefault', true));
    }

    /** "create" tidak boleh terbaca sebagai id halaman. */
    public function test_the_add_screen_is_not_read_as_a_page_id(): void
    {
        $this->actingAs($this->actor())
            ->get('/seo-social/create')
            ->assertInertia(fn (AssertableInertia $p) => $p->component('Settings/SeoForm'));
    }

    /** Sesudah menyimpan, orangnya kembali ke daftarnya — bukan ke formulir kosong. */
    public function test_saving_returns_to_the_list(): void
    {
        $this->actingAs($this->actor())
            ->post('/seo-social', $this->payload())
            ->assertRedirect('/seo-social');
    }

    public function test_a_viewer_cannot_open_the_add_screen(): void
    {
        $this->actingAs(User::factory()->withRole('viewer')->create())
            ->get('/seo-social/create')
            ->assertForbidden();
    }

    public function test_a_viewer_cannot_change_page_meta(): void
    {
        $this->actingAs(User::factory()->withRole('viewer')->create())
            ->post('/seo-social', $this->payload())
            ->assertForbidden();
    }
}
