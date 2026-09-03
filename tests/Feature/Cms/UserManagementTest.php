<?php

namespace Tests\Feature\Cms;

use App\Models\User;
use App\Support\Access;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->superAdmin()->create();
    }

    /**
     * Akun lahir TANPA sandi — "No password is created in this form"
     * (`529:9716`). Yang menggantikannya undangan; alurnya diuji terpisah di
     * `AdminInvitationTest`.
     */
    public function test_creating_a_user_assigns_the_chosen_roles(): void
    {
        $this->actingAs($this->admin())->post('/users', [
            'name' => 'Editor Baru',
            'email' => 'editor@dwf-domino.org',
            'roles' => ['editor'],
            'two_factor_enabled' => true,
            'is_active' => true,
            'is_active' => true,
        ])->assertRedirect('/users');

        $user = User::where('email', 'editor@dwf-domino.org')->first();

        $this->assertNotNull($user);
        $this->assertSame(['editor'], $user->getRoleNames()->all());
        $this->assertNull($user->password);
        $this->assertTrue($user->isPendingInvitation());
    }

    /** Formulir tambah menolak sandi, bukan mengabaikannya diam-diam. */
    public function test_the_create_form_refuses_a_password(): void
    {
        $this->actingAs($this->admin())
            ->post('/users', [
                'name' => 'Editor Baru',
                'email' => 'editor@dwf-domino.org',
                'password' => 'sandi-yang-panjang',
                'roles' => ['editor'],
                'two_factor_enabled' => true,
                'is_active' => true,
                'is_active' => true,
            ])
            ->assertSessionHasErrors('password');
    }

    public function test_a_user_must_have_at_least_one_role(): void
    {
        $this->actingAs($this->admin())
            ->post('/users', [
                'name' => 'Tanpa Peran',
                'email' => 'x@dwf-domino.org',
                'roles' => [],
                'two_factor_enabled' => true,
                'is_active' => true,
            ])
            ->assertSessionHasErrors('roles');
    }

    public function test_editing_without_a_password_keeps_the_old_one(): void
    {
        // Field sandi yang dikirim KOSONG adalah string kosong, bukan null.
        // Menyimpannya apa adanya akan mengganti sandi orang dengan hash
        // string kosong — dan ia baru menyadarinya saat gagal masuk.
        $target = User::factory()->withRole('editor')->create(['password' => 'sandi-lama-sekali']);
        $before = $target->password;

        $this->actingAs($this->admin())->put("/users/{$target->id}", [
            'name' => 'Nama Diubah',
            'email' => $target->email,
            'password' => '',
            'roles' => ['editor'],
            'two_factor_enabled' => true,
            'is_active' => true,
        ])->assertRedirect('/users');

        $target->refresh();

        $this->assertSame('Nama Diubah', $target->name);
        $this->assertSame($before, $target->password);
        $this->assertTrue(Hash::check('sandi-lama-sekali', $target->password));
    }

    public function test_two_factor_can_be_switched_off_per_user(): void
    {
        $target = User::factory()->withRole('editor')->create(['two_factor_enabled' => true]);

        $this->actingAs($this->admin())->put("/users/{$target->id}", [
            'name' => $target->name,
            'email' => $target->email,
            'roles' => ['editor'],
            'two_factor_enabled' => false,
            'is_active' => true,
        ]);

        $this->assertFalse($target->fresh()->two_factor_enabled);
    }

    // --- Penjagaan agar backoffice tidak mengunci dirinya sendiri ----------

    public function test_you_cannot_remove_your_own_super_admin_role(): void
    {
        $me = $this->admin();
        User::factory()->superAdmin()->create(); // masih ada super admin lain

        $this->actingAs($me)
            ->put("/users/{$me->id}", [
                'name' => $me->name,
                'email' => $me->email,
                'roles' => ['editor'],
                'two_factor_enabled' => true,
                'is_active' => true,
            ])
            ->assertSessionHasErrors('roles');

        $this->assertTrue($me->fresh()->isSuperAdmin());
    }

    public function test_the_last_super_admin_cannot_be_demoted(): void
    {
        $only = $this->admin();

        $this->actingAs($only)
            ->put("/users/{$only->id}", [
                'name' => $only->name,
                'email' => $only->email,
                'roles' => ['admin'],
                'two_factor_enabled' => true,
                'is_active' => true,
            ])
            ->assertSessionHasErrors('roles');

        $this->assertTrue($only->fresh()->isSuperAdmin());
    }

    public function test_you_cannot_delete_yourself(): void
    {
        $me = $this->admin();

        $this->actingAs($me)->delete("/users/{$me->id}")->assertSessionHasErrors('user');
        $this->assertDatabaseHas('users', ['id' => $me->id]);
    }

    public function test_the_last_super_admin_cannot_be_deleted(): void
    {
        $only = $this->admin();
        $other = User::factory()->withRole('editor')->create();

        // `other` yang menghapus, jadi penjagaan "jangan hapus diri sendiri"
        // tidak ikut campur — yang diuji penjagaan super admin terakhir.
        $other->syncRoles([Access::SUPER_ADMIN]);
        $only->syncRoles(['admin']);

        $this->actingAs($other)->delete("/users/{$only->id}")->assertRedirect();
        $this->assertDatabaseMissing('users', ['id' => $only->id]);
    }

    public function test_a_non_admin_cannot_reach_user_management_at_all(): void
    {
        $editor = User::factory()->withRole('editor')->create();
        $target = User::factory()->withRole('editor')->create();

        $this->actingAs($editor)->get('/users')->assertForbidden();
        $this->actingAs($editor)->get('/users/create')->assertForbidden();
        $this->actingAs($editor)->delete("/users/{$target->id}")->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $target->id]);
    }
}
