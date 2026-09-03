<?php

namespace Tests\Feature\Cms;

use App\Models\User;
use App\Support\Access;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->superAdmin()->create();
    }

    public function test_the_matrix_covers_every_module_and_action(): void
    {
        $this->actingAs($this->admin())
            ->get('/roles/create')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->component('Roles/Form')
                ->has('matrix', count(Access::MODULES)));
    }

    public function test_creating_a_role_grants_exactly_the_ticked_permissions(): void
    {
        $this->actingAs($this->admin())->post('/roles', [
            'name' => 'content-lead',
            'permissions' => ['news.view', 'news.create', 'faq.view'],
            'scope' => 'global',
        ])->assertRedirect('/roles');

        $role = Role::findByName('content-lead', 'web');

        $this->assertSame(
            ['faq.view', 'news.create', 'news.view'],
            $role->permissions->pluck('name')->sort()->values()->all(),
        );
    }

    public function test_a_role_change_takes_effect_immediately(): void
    {
        // Yang paling mudah salah: izin spatie di-cache. Kalau cache-nya tidak
        // dibuang, menyunting peran terlihat berhasil tapi tidak mengubah apa
        // pun sampai cache kedaluwarsa.
        $admin = $this->admin();
        $editor = User::factory()->withRole('editor')->create();

        $this->actingAs($editor)->get('/documents')->assertOk();

        $role = Role::findByName('editor', 'web');
        $this->actingAs($admin)->put("/roles/{$role->id}", [
            'name' => 'editor',
            'permissions' => ['news.view'],
            'scope' => 'global',
        ])->assertRedirect('/roles');

        $this->actingAs($editor)->get('/documents')->assertForbidden();
        $this->actingAs($editor)->get('/news')->assertOk();
    }

    public function test_a_role_name_must_be_a_slug(): void
    {
        $this->actingAs($this->admin())
            ->post('/roles', ['name' => 'Content Lead!', 'permissions' => [], 'scope' => 'global'])
            ->assertSessionHasErrors('name');
    }

    public function test_super_admin_cannot_be_edited_or_deleted(): void
    {
        // Ia melewati seluruh pemeriksaan lewat `Gate::before`, jadi baris
        // izinnya tidak pernah dibaca — layar sunting yang tidak mengubah apa
        // pun akan lebih membingungkan daripada menolak.
        // Admin dibuat LEBIH DULU: peran baru diseed saat pengguna pertama
        // dibuat, jadi `findByName` sebelum itu tidak menemukan apa pun.
        $admin = $this->admin();
        $role = Role::findByName(Access::SUPER_ADMIN, 'web');

        $this->actingAs($admin)
            ->put("/roles/{$role->id}", ['name' => 'super-admin', 'permissions' => ['news.view'], 'scope' => 'global'])
            ->assertSessionHasErrors('role');

        $this->actingAs($admin)
            ->delete("/roles/{$role->id}")
            ->assertSessionHasErrors('role');

        $this->assertDatabaseHas('roles', ['name' => Access::SUPER_ADMIN]);
    }

    public function test_a_role_that_is_still_assigned_cannot_be_deleted(): void
    {
        User::factory()->withRole('editor')->create();
        $role = Role::findByName('editor', 'web');

        $this->actingAs($this->admin())
            ->delete("/roles/{$role->id}")
            ->assertSessionHasErrors('role');

        $this->assertDatabaseHas('roles', ['name' => 'editor']);
    }

    public function test_an_unused_role_can_be_deleted(): void
    {
        $this->actingAs($this->admin())->post('/roles', ['name' => 'sementara', 'permissions' => [], 'scope' => 'global']);
        $role = Role::findByName('sementara', 'web');

        $this->actingAs($this->admin())->delete("/roles/{$role->id}")->assertRedirect();

        $this->assertDatabaseMissing('roles', ['name' => 'sementara']);
    }

    public function test_only_user_managers_reach_the_screen(): void
    {
        $editor = User::factory()->withRole('editor')->create();

        $this->actingAs($editor)->get('/roles')->assertForbidden();
        $this->actingAs($editor)->post('/roles', ['name' => 'x', 'permissions' => [], 'scope' => 'global'])->assertForbidden();
    }

    public function test_an_unknown_permission_is_rejected(): void
    {
        $this->actingAs($this->admin())
            ->post('/roles', ['name' => 'aneh', 'permissions' => ['tidak-ada.view'], 'scope' => 'global'])
            ->assertSessionHasErrors('permissions.0');
    }
}
