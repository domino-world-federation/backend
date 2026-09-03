<?php

namespace Tests\Feature;

use App\Models\NewsCategory;
use App\Models\User;
use App\Support\Access;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_permission_list_is_generated_not_typed(): void
    {
        // Daftar yang diketik tangan akan berbeda dari yang diperiksa `can()`
        // begitu ada modul baru — dan yang terjadi bukan galat, melainkan
        // tombol yang diam-diam hilang untuk semua orang.
        $permissions = Access::permissions();

        foreach (array_keys(Access::MODULES) as $module) {
            foreach (Access::actionsFor($module) as $action) {
                $this->assertContains("{$module}.{$action}", $permissions);
            }
        }
    }

    public function test_a_super_admin_passes_every_gate_without_owning_any_permission_row(): void
    {
        $user = User::factory()->superAdmin()->create();

        $this->assertTrue($user->can('news.delete'));
        $this->assertTrue($user->can('users.delete'));
        // Modul yang belum pernah diseed pun lolos, karena `Gate::before`
        // yang memutuskan — bukan baris izin di database.
        $this->assertTrue($user->can('modul-yang-belum-ada.apa-pun'));
    }

    public function test_an_editor_reaches_content_but_not_users(): void
    {
        $editor = User::factory()->withRole('editor')->create();

        $this->actingAs($editor)->get('/news')->assertOk();
        $this->actingAs($editor)->get('/faq')->assertOk();
        $this->actingAs($editor)->get('/users')->assertForbidden();
        $this->actingAs($editor)->get('/activity-log')->assertForbidden();
    }

    public function test_a_viewer_can_read_but_not_write(): void
    {
        $viewer = User::factory()->withRole('viewer')->create();
        $category = NewsCategory::factory()->create();

        $this->actingAs($viewer)->get('/news/categories')->assertOk();

        // `viewer` hanya punya izin `.view`; route tulisnya harus menolak.
        $this->actingAs($viewer)->get('/users/create')->assertForbidden();
    }

    public function test_a_user_without_any_role_reaches_nothing(): void
    {
        $nobody = User::factory()->create();

        $this->actingAs($nobody)->get('/news')->assertForbidden();
        $this->actingAs($nobody)->get('/users')->assertForbidden();

        // Dashboard tetap terbuka — ia tidak memuat data modul mana pun, dan
        // menolaknya berarti pengguna baru mendarat di halaman galat.
        $this->actingAs($nobody)->get('/dashboard')->assertOk();
    }

    public function test_the_sidebar_hides_what_the_user_cannot_open(): void
    {
        // Menu yang terlihat tapi berujung 403 memberi tahu orang tentang
        // modul yang bukan urusannya, dan membuat mereka mengira ada yang rusak.
        $editor = User::factory()->withRole('editor')->create();

        $this->actingAs($editor)
            ->get('/dashboard')
            ->assertInertia(function (AssertableInertia $page) {
                $labels = collect($page->toArray()['props']['navigation'])->pluck('label');

                $this->assertContains('News Articles', $labels);
                $this->assertNotContains('User Management', $labels);
                $this->assertNotContains('Activity Log', $labels);
                // Judul grup yang jadi kosong ikut dibuang.
                $this->assertNotContains('Administration', $labels);
            });
    }

    public function test_a_super_admin_sees_the_administration_group(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create())
            ->get('/dashboard')
            ->assertInertia(function (AssertableInertia $page) {
                $labels = collect($page->toArray()['props']['navigation'])->pluck('label');

                $this->assertContains('Administration', $labels);
                $this->assertContains('User Management', $labels);
            });
    }
}
