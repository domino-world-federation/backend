<?php

namespace Tests\Feature\Cms;

use App\Mail\AdminInvitationMail;
use App\Models\AdminInvitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Alur undangan admin — panel "Invitation Flow" (`529:9714`).
 *
 * "No password is created in this form. The admin receives a secure invitation
 * link. Invitation expires after 72 hours and can be resent or revoked from
 * Admin Users." Empat janji; berkas ini menguji keempatnya.
 */
class AdminInvitationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->superAdmin()->create();
    }

    /** @param array<string, mixed> $overrides @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Kenji Mori',
            'email' => 'kenji@dwf-domino.org',
            'roles' => ['editor'],
            'two_factor_enabled' => true,
            'is_active' => true,
        ], $overrides);
    }

    // ------------------------------------------------------------ penerbitan

    public function test_creating_an_admin_sends_an_invitation_instead_of_a_password(): void
    {
        Mail::fake();

        $this->actingAs($this->admin())->post('/users', $this->payload())->assertRedirect('/users');

        $user = User::where('email', 'kenji@dwf-domino.org')->sole();

        $this->assertNull($user->password);
        $this->assertTrue($user->isPendingInvitation());
        $this->assertDatabaseCount('admin_invitations', 1);

        Mail::assertSent(AdminInvitationMail::class, fn ($mail) => $mail->hasTo('kenji@dwf-domino.org'));
    }

    /** "Invitation expires after 72 hours." */
    public function test_the_invitation_expires_after_seventy_two_hours(): void
    {
        Mail::fake();
        $this->actingAs($this->admin())->post('/users', $this->payload());

        $invitation = AdminInvitation::sole();

        $this->assertEqualsWithDelta(
            72,
            $invitation->created_at->diffInHours($invitation->expires_at),
            0.01,
        );
    }

    /**
     * Yang tersimpan HASH-nya, bukan tokennya.
     *
     * Kalau ini gagal, siapa pun yang bisa membaca tabel — dump, backup, layar
     * admin yang keliru — bisa menerima undangan orang lain.
     */
    public function test_the_raw_token_is_never_stored(): void
    {
        [$invitation, $token] = AdminInvitation::issue(User::factory()->create());

        $this->assertNotSame($token, $invitation->token_hash);
        $this->assertSame(hash('sha256', $token), $invitation->token_hash);
        $this->assertDatabaseMissing('admin_invitations', ['token_hash' => $token]);
    }

    // ------------------------------------------------------------ penerimaan

    public function test_accepting_the_invitation_sets_the_password_and_signs_in(): void
    {
        $user = User::factory()->withRole('editor')->create(['password' => null]);
        [, $token] = AdminInvitation::issue($user);

        $this->get("/invitation/{$token}")->assertOk();

        $this->post("/invitation/{$token}", [
            'password' => 'sandi-yang-panjang',
            'password_confirmation' => 'sandi-yang-panjang',
        ])->assertRedirect('/dashboard');

        $user->refresh();

        $this->assertTrue(Hash::check('sandi-yang-panjang', $user->password));
        $this->assertFalse($user->isPendingInvitation());
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull(AdminInvitation::sole()->accepted_at);
    }

    /** Sekali pakai. Tautan yang bisa dipakai dua kali bukan undangan, melainkan sandi. */
    public function test_an_invitation_cannot_be_accepted_twice(): void
    {
        $user = User::factory()->withRole('editor')->create(['password' => null]);
        [, $token] = AdminInvitation::issue($user);

        $this->post("/invitation/{$token}", [
            'password' => 'sandi-yang-panjang',
            'password_confirmation' => 'sandi-yang-panjang',
        ]);

        $this->post('/logout');

        $this->post("/invitation/{$token}", [
            'password' => 'sandi-lain-yang-panjang',
            'password_confirmation' => 'sandi-lain-yang-panjang',
        ])->assertRedirect('/login');

        $this->assertTrue(Hash::check('sandi-yang-panjang', $user->fresh()->password));
    }

    public function test_an_expired_invitation_is_refused(): void
    {
        $user = User::factory()->withRole('editor')->create(['password' => null]);
        [$invitation, $token] = AdminInvitation::issue($user);

        $invitation->update(['expires_at' => now()->subMinute()]);

        $this->get("/invitation/{$token}")->assertRedirect('/login');
        $this->assertNull($user->fresh()->password);
    }

    public function test_an_invitation_for_a_deactivated_account_is_refused(): void
    {
        $user = User::factory()->withRole('editor')->create(['password' => null, 'is_active' => false]);
        [, $token] = AdminInvitation::issue($user);

        $this->get("/invitation/{$token}")->assertRedirect('/login');
    }

    public function test_an_unknown_token_is_refused(): void
    {
        $this->get('/invitation/'.str_repeat('x', 64))->assertRedirect('/login');
    }

    // ------------------------------------------------- kirim ulang & cabut

    /** Mengirim ulang menerbitkan token BARU dan mematikan yang lama. */
    public function test_resending_replaces_the_previous_link(): void
    {
        Mail::fake();

        $user = User::factory()->withRole('editor')->create(['password' => null]);
        [, $old] = AdminInvitation::issue($user);

        $this->actingAs($this->admin())
            ->post("/users/{$user->id}/invitation/resend")
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('admin_invitations', 2);

        // Tautan lama mati seketika — kalau tidak, "kirim ulang" menggandakan
        // pintu masuk alih-alih memindahkannya.
        $this->get("/invitation/{$old}")->assertRedirect('/login');
    }

    public function test_resending_is_refused_once_the_invitation_is_accepted(): void
    {
        $user = User::factory()->withRole('editor')->create(['password' => 'sandi-yang-panjang']);

        $this->actingAs($this->admin())
            ->post("/users/{$user->id}/invitation/resend")
            ->assertSessionHasErrors('invitation');
    }

    public function test_revoking_kills_the_link_but_keeps_the_account(): void
    {
        $user = User::factory()->withRole('editor')->create(['password' => null]);
        [, $token] = AdminInvitation::issue($user);

        $this->actingAs($this->admin())
            ->delete("/users/{$user->id}/invitation")
            ->assertSessionHasNoErrors();

        $this->get("/invitation/{$token}")->assertRedirect('/login');
        $this->assertModelExists($user);
        $this->assertNotNull(AdminInvitation::sole()->revoked_at);
    }

    // ---------------------------------------------------------------- login

    /**
     * Akun yang belum menerima undangannya tidak bisa login.
     *
     * Sandinya `null`, dan `Hash::check` terhadap `null` selalu gagal — tapi
     * mengandalkan itu saja berarti perubahan kecil di jalur login bisa
     * membukanya tanpa ada yang tahu.
     */
    public function test_an_account_awaiting_its_invitation_cannot_sign_in(): void
    {
        $user = User::factory()->withRole('editor')->create(['password' => null]);

        // Sandi apa pun, termasuk yang bentuknya sah — yang diuji adalah bahwa
        // akun tanpa sandi tidak bisa dimasuki, bukan bahwa field kosong
        // ditolak (itu aturan `required` dan sudah diuji di tempat lain).
        $this->post('/login', ['email' => $user->email, 'password' => 'tebakan-yang-panjang'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_a_deactivated_account_cannot_sign_in(): void
    {
        $user = User::factory()->withRole('editor')->create([
            'password' => 'sandi-yang-panjang',
            'is_active' => false,
        ]);

        $this->post('/login', ['email' => $user->email, 'password' => 'sandi-yang-panjang'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    /** Kolom "Last Login" (`528:8821`) diisi listener, bukan controller. */
    public function test_signing_in_stamps_the_last_login(): void
    {
        $user = User::factory()->withRole('editor')->create(['password' => 'sandi-yang-panjang']);

        $this->assertNull($user->last_login_at);

        $this->post('/login', ['email' => $user->email, 'password' => 'sandi-yang-panjang']);

        $this->assertNotNull($user->fresh()->last_login_at);
    }
}
