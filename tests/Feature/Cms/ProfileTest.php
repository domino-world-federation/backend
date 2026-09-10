<?php

namespace Tests\Feature\Cms;

use App\Models\MemberFederation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Akun sendiri — `/profile`.
 *
 * Yang paling penting di berkas ini bukan "nama tersimpan", melainkan
 * `test_the_screen_cannot_raise_your_own_privileges`. Layar ini satu-satunya
 * tempat di aplikasi tempat seseorang menyunting barisnya SENDIRI di tabel
 * `users`, dan baris itu memuat peran, status aktif, dan sakelar 2FA. Semuanya
 * ada di `#[Fillable]` karena User Management memang membutuhkannya — jadi satu
 * `fill($request->validated())` yang tampak wajar sudah cukup untuk membuat
 * seorang `viewer` mengangkat dirinya jadi super admin, tanpa satu pun galat.
 */
class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_is_sent_to_the_login_screen(): void
    {
        $this->get('/profile')->assertRedirect('/login');
    }

    /**
     * Tanpa `can:` apa pun — termasuk untuk `viewer`.
     *
     * Menjaganya dengan `users.update` akan berarti orang yang hanya boleh
     * membaca tidak bisa mengganti sandinya sendiri, dan izin itu justru
     * tentang menyunting akun ORANG LAIN.
     */
    public function test_every_signed_in_user_can_open_it_including_a_viewer(): void
    {
        $this->actingAs(User::factory()->withRole('viewer')->create())
            ->get('/profile')
            ->assertOk();
    }

    public function test_the_name_and_email_can_be_changed(): void
    {
        $user = User::factory()->withRole('editor')->create();

        $this->actingAs($user)
            ->post('/profile', ['name' => 'Nama Baru', 'email' => 'baru@dwf.test'])
            ->assertSessionHasNoErrors();

        $user->refresh();

        $this->assertSame('Nama Baru', $user->name);
        $this->assertSame('baru@dwf.test', $user->email);
    }

    /** Surel adalah identitas login, jadi dua akun tidak boleh memegang yang sama. */
    public function test_an_email_already_taken_is_refused(): void
    {
        User::factory()->create(['email' => 'sudah@dwf.test']);
        $user = User::factory()->withRole('editor')->create();

        $this->actingAs($user)
            ->post('/profile', ['name' => $user->name, 'email' => 'sudah@dwf.test'])
            ->assertSessionHasErrors('email');
    }

    /** Menyimpan tanpa mengubah surel tidak boleh ditolak oleh aturan keunikannya sendiri. */
    public function test_keeping_your_own_email_is_not_a_duplicate(): void
    {
        $user = User::factory()->withRole('editor')->create(['email' => 'saya@dwf.test']);

        $this->actingAs($user)
            ->post('/profile', ['name' => 'Nama Lain', 'email' => 'saya@dwf.test'])
            ->assertSessionHasNoErrors();
    }

    /**
     * **Wewenang tidak bisa dinaikkan dari sini.**
     *
     * Keempat field dikirim langsung ke endpoint-nya, melewati layar mana pun —
     * persis yang bisa dilakukan siapa saja dengan alat baris perintah.
     */
    public function test_the_screen_cannot_raise_your_own_privileges(): void
    {
        $federation = MemberFederation::factory()->create();
        $user = User::factory()->withRole('viewer')->create([
            'two_factor_enabled' => true,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->post('/profile', [
                'name' => 'Nama Baru',
                'email' => $user->email,
                'roles' => ['super-admin'],
                'is_active' => false,
                'two_factor_enabled' => false,
                'member_federation_id' => $federation->id,
            ])
            ->assertSessionHasNoErrors();

        $user->refresh();

        $this->assertSame('Nama Baru', $user->name, 'Yang boleh berubah tetap berubah.');
        $this->assertFalse($user->isSuperAdmin());
        $this->assertSame(['viewer'], $user->getRoleNames()->all());
        $this->assertTrue($user->is_active);
        $this->assertTrue($user->two_factor_enabled);
        $this->assertNull($user->member_federation_id);
    }

    // ------------------------------------------------------------------ sandi

    public function test_the_password_changes_when_the_current_one_is_right(): void
    {
        $user = User::factory()->withRole('editor')->create(['password' => 'sandi-lama-panjang']);

        $this->actingAs($user)
            ->put('/profile/password', [
                'current_password' => 'sandi-lama-panjang',
                'password' => 'sandi-baru-panjang',
                'password_confirmation' => 'sandi-baru-panjang',
            ])
            ->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('sandi-baru-panjang', $user->refresh()->password));
    }

    /**
     * Sandi lama yang salah menolak — dan ini bukan formalitas.
     *
     * Sesi backoffice bertahan berjam-jam, jadi layar yang terbuka di laptop
     * yang ditinggal adalah jalan mengambil alih akun secara permanen tanpa
     * pemeriksaan ini.
     */
    public function test_a_wrong_current_password_refuses_the_change(): void
    {
        $user = User::factory()->withRole('editor')->create(['password' => 'sandi-lama-panjang']);

        $this->actingAs($user)
            ->put('/profile/password', [
                'current_password' => 'tebakan-yang-salah',
                'password' => 'sandi-baru-panjang',
                'password_confirmation' => 'sandi-baru-panjang',
            ])
            ->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('sandi-lama-panjang', $user->refresh()->password));
    }

    // ----------------------------------------------------------------- avatar

    public function test_an_avatar_is_stored_and_can_be_removed(): void
    {
        Storage::fake('public');

        $user = User::factory()->withRole('editor')->create();

        $this->actingAs($user)
            ->post('/profile', [
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => UploadedFile::fake()->image('avatar.webp', 512, 512),
            ])
            ->assertSessionHasNoErrors();

        $path = $user->refresh()->avatar_path;

        $this->assertNotNull($path);
        Storage::disk('public')->assertExists($path);

        $this->actingAs($user)
            ->post('/profile', [
                'name' => $user->name,
                'email' => $user->email,
                'remove_avatar' => true,
            ])
            ->assertSessionHasNoErrors();

        $this->assertNull($user->refresh()->avatar_path);
        Storage::disk('public')->assertMissing($path);
    }

    /** Persegi ditegakkan: `object-cover` di sidebar memotong foto lanskap tepat di kepalanya. */
    public function test_an_avatar_that_is_not_square_is_refused(): void
    {
        Storage::fake('public');

        $user = User::factory()->withRole('editor')->create();

        $this->actingAs($user)
            ->post('/profile', [
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => UploadedFile::fake()->image('avatar.webp', 800, 400),
            ])
            ->assertSessionHasErrors('avatar');

        $this->assertNull($user->refresh()->avatar_path);
    }
}
