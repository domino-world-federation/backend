<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Security\Recaptcha;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class RecaptchaTest extends TestCase
{
    use RefreshDatabase;

    private function enable(): void
    {
        config([
            'services.recaptcha.site_key' => 'site-key-uji',
            'services.recaptcha.secret_key' => 'secret-key-uji',
        ]);
    }

    private function user(): User
    {
        return User::factory()->superAdmin()->create(['password' => 'rahasia-sekali']);
    }

    public function test_it_is_off_when_the_keys_are_missing(): void
    {
        // Bawaan `.env` lokal: kedua kunci kosong. Login harus tetap jalan, dan
        // TIDAK boleh ada satu pun request ke Google.
        config(['services.recaptcha.site_key' => null, 'services.recaptcha.secret_key' => null]);
        Http::preventStrayRequests();

        $this->assertFalse(Recaptcha::isEnabled());

        $this->post('/login', ['email' => $this->user()->email, 'password' => 'rahasia-sekali'])
            ->assertRedirect('/dashboard');
    }

    public function test_the_login_page_only_ships_the_site_key_when_enabled(): void
    {
        $this->get('/login')->assertInertia(
            fn (AssertableInertia $page) => $page->where('recaptchaSiteKey', null)
        );

        $this->enable();

        $this->get('/login')->assertInertia(
            fn (AssertableInertia $page) => $page->where('recaptchaSiteKey', 'site-key-uji')
        );
    }

    public function test_the_secret_key_never_reaches_the_browser(): void
    {
        $this->enable();

        $this->get('/login')
            ->assertOk()
            ->assertDontSee('secret-key-uji');
    }

    public function test_a_missing_token_is_rejected_before_any_call_to_google(): void
    {
        $this->enable();
        Http::preventStrayRequests();

        $this->post('/login', ['email' => $this->user()->email, 'password' => 'rahasia-sekali'])
            ->assertSessionHasErrors('recaptcha_token');

        $this->assertGuest();
    }

    public function test_a_valid_token_lets_the_login_through(): void
    {
        $this->enable();
        Http::fake([
            'www.google.com/recaptcha/api/siteverify' => Http::response(['success' => true]),
        ]);

        $this->post('/login', [
            'email' => $this->user()->email,
            'password' => 'rahasia-sekali',
            'recaptcha_token' => 'token-dari-browser',
        ])->assertRedirect('/dashboard');

        Http::assertSent(function ($request) {
            // Rahasianya dikirim ke Google, token pengguna ikut, dan IP-nya
            // disertakan — Google memakainya untuk menilai.
            return $request['secret'] === 'secret-key-uji'
                && $request['response'] === 'token-dari-browser'
                && array_key_exists('remoteip', $request->data());
        });
    }

    public function test_a_token_google_rejects_blocks_the_login(): void
    {
        $this->enable();
        Http::fake([
            'www.google.com/recaptcha/api/siteverify' => Http::response([
                'success' => false,
                'error-codes' => ['invalid-input-response'],
            ]),
        ]);

        $this->post('/login', [
            'email' => $this->user()->email,
            'password' => 'rahasia-sekali',
            'recaptcha_token' => 'token-palsu',
        ])->assertSessionHasErrors('recaptcha_token');

        $this->assertGuest();
    }

    public function test_it_fails_open_when_google_cannot_be_reached(): void
    {
        // Keputusan yang disengaja, bukan kelalaian: kalau Google tidak bisa
        // dihubungi, seluruh admin akan terkunci di luar. Yang menahan
        // tebak-sandi adalah RateLimiter, dan itu tidak bergantung pada Google.
        $this->enable();
        Http::fake(fn () => throw new ConnectionException('timeout'));

        $this->post('/login', [
            'email' => $this->user()->email,
            'password' => 'rahasia-sekali',
            'recaptcha_token' => 'token-apa-saja',
        ])->assertRedirect('/dashboard');
    }

    public function test_a_five_hundred_from_google_also_fails_open(): void
    {
        $this->enable();
        Http::fake([
            'www.google.com/recaptcha/api/siteverify' => Http::response('', 500),
        ]);

        $this->post('/login', [
            'email' => $this->user()->email,
            'password' => 'rahasia-sekali',
            'recaptcha_token' => 'token-apa-saja',
        ])->assertRedirect('/dashboard');
    }

    public function test_a_wrong_password_still_fails_even_with_a_valid_captcha(): void
    {
        // Captcha membuktikan "bukan bot", bukan "boleh masuk".
        $this->enable();
        Http::fake([
            'www.google.com/recaptcha/api/siteverify' => Http::response(['success' => true]),
        ]);

        $this->post('/login', [
            'email' => $this->user()->email,
            'password' => 'salah',
            'recaptcha_token' => 'token-sah',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }
}
