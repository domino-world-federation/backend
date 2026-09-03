<?php

namespace Tests\Feature;

use App\Models\NewsArticle;
use App\Models\User;
use App\Support\Dashboard\DashboardData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_renders_for_an_authenticated_user(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create())
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Dashboard/Index')
                ->has('stats', 4)
                ->has('publications.points')
                ->has('publications.series', 2)
                ->has('inbound', 14)
                ->has('sections', 6)
                ->has('activity')
            );
    }

    public function test_it_reports_empty_when_nothing_has_been_written_yet(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create())
            ->get('/dashboard')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('isEmpty', true));
    }

    public function test_the_numbers_come_from_the_database(): void
    {
        // Penjaga kejujuran halaman: tidak boleh ada jalur data karangan.
        // Kalau seseorang mengembalikan generator contoh, tes ini gagal karena
        // angkanya berhenti mengikuti isi tabel.
        $user = User::factory()->superAdmin()->create();
        NewsArticle::factory()->count(3)->create(['published_at' => now()->subDay()]);
        NewsArticle::factory()->draft()->count(2)->create();

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('isEmpty', false)
                ->where('stats.0.value', 3)
                ->where('stats.1.value', 2)
            );
    }

    public function test_a_highlighted_article_lights_up_the_news_section_row(): void
    {
        $user = User::factory()->superAdmin()->create();

        // Kuncinya ikut diperiksa, bukan cuma indeksnya: daftar section pernah
        // berubah panjang (grup "Landing Page" dibuang 2026-09-03), dan tes
        // yang cuma memegang indeks gagal dengan pesan tentang STATUS padahal
        // yang bergeser barisnya.
        $this->actingAs($user)->get('/dashboard')->assertInertia(
            fn (AssertableInertia $page) => $page
                ->where('sections.3.key', 'news-section')
                ->where('sections.3.status', 'incomplete')
        );

        NewsArticle::factory()->create(['is_highlighted' => true, 'published_at' => now()->subDay()]);

        $this->actingAs($user)->get('/dashboard')->assertInertia(
            fn (AssertableInertia $page) => $page->where('sections.3.status', 'ready')
        );
    }

    public function test_range_filter_changes_the_number_of_points(): void
    {
        $user = User::factory()->superAdmin()->create();

        $this->actingAs($user)->get('/dashboard?range=30d')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('range', '30d')
                ->has('publications.points', 30)
            );

        $this->actingAs($user)->get('/dashboard?range=12m')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('range', '12m')
                ->has('publications.points', 12)
            );
    }

    public function test_an_unknown_range_falls_back_instead_of_reaching_the_generator(): void
    {
        // `range` ikut menentukan berapa banyak titik yang dihitung. Nilai
        // sembarang dari query string tidak boleh sampai ke sana.
        $this->actingAs(User::factory()->superAdmin()->create())
            ->get('/dashboard?range='.urlencode('999y; drop table users'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('range', '30d'));
    }

    public function test_published_articles_land_in_the_right_bucket(): void
    {
        NewsArticle::factory()->create(['published_at' => now()->startOfDay()]);

        $points = (new DashboardData('30d'))->publications()['points'];
        $today = end($points);

        $this->assertSame(now()->toDateString(), $today['iso']);
        $this->assertSame(1, $today['values'][0]);
    }

    public function test_root_redirects_to_dashboard(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create())
            ->get('/')
            ->assertRedirect('/dashboard');
    }

    public function test_shared_props_carry_the_signed_in_user(): void
    {
        $user = User::factory()->superAdmin()->create(['name' => 'Robbi Darwis']);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('auth.user.name', 'Robbi Darwis')
                ->where('auth.user.email', $user->email)
                ->where('auth.user.avatarUrl', null)
            );
    }
}
