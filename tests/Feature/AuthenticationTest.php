<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_renders(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Auth/Login'));
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_login_screen_does_not_leak_the_navigation_tree(): void
    {
        // Struktur sidebar adalah peta backoffice. Halaman login dilihat siapa
        // saja, jadi `share()` menahannya untuk sesi yang belum masuk.
        $this->get('/login')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('navigation', []));
    }

    public function test_user_can_log_in(): void
    {
        $user = User::factory()->superAdmin()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);
    }

    public function test_login_regenerates_the_session_id(): void
    {
        $user = User::factory()->superAdmin()->create();

        $this->get('/login');
        $before = session()->getId();

        $this->post('/login', ['email' => $user->email, 'password' => 'password']);

        $this->assertNotSame($before, session()->getId());
    }

    public function test_wrong_password_is_rejected(): void
    {
        $user = User::factory()->superAdmin()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'salah-sekali',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_login_is_rate_limited_after_five_attempts(): void
    {
        $user = User::factory()->superAdmin()->create();

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'salah']);
        }

        $response = $this->post('/login', ['email' => $user->email, 'password' => 'salah']);

        $response->assertSessionHasErrors('email');

        // Pesannya mengikuti bahasa yang aktif — bawaannya Indonesia — jadi
        // yang diperiksa ANGKA detiknya. Itu bagian yang sama di semua
        // terjemahan, dan itu juga yang benar-benar membuktikan throttle-nya
        // menahan permintaan berikutnya.
        $this->assertMatchesRegularExpression(
            '/\d+/',
            session('errors')->first('email'),
        );

        RateLimiter::clear(strtolower($user->email).'|127.0.0.1');
    }

    public function test_user_can_log_out(): void
    {
        $user = User::factory()->superAdmin()->create();

        $this->actingAs($user)->post('/logout')->assertRedirect('/login');

        $this->assertGuest();
    }
}
