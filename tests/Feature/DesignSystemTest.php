<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Navigation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class DesignSystemTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_renders_for_an_authenticated_user(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create())
            ->get('/design-system')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('DesignSystem/Index'));
    }

    public function test_it_is_behind_auth(): void
    {
        // Halaman ini memuat contoh hidup seluruh komponen backoffice. Bukan
        // rahasia, tapi juga tidak ada alasan menyajikannya ke publik.
        $this->get('/design-system')->assertRedirect('/login');
    }

    public function test_it_is_marked_built_so_it_gets_no_placeholder_route(): void
    {
        // Kalau `built` kembali jadi false, `routes/web.php` akan mendaftarkan
        // route placeholder di path yang sama dan menimpa halaman sungguhannya.
        $this->assertTrue(Navigation::destinations()['design-system']['built']);
        $this->assertArrayNotHasKey('design-system', Navigation::pending());
    }
}
