<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Security\TwoFactor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class TwoFactorTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'sandi-yang-benar';

    protected function setUp(): void
    {
        parent::setUp();
        config(['dwf.two_factor' => true]);
    }

    private function user(array $attributes = []): User
    {
        return User::factory()->superAdmin()->create(['password' => self::PASSWORD, ...$attributes]);
    }

    private function enrolled(): array
    {
        $secret = TwoFactor::generateSecret();

        $user = $this->user([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => ['aaaa-bbbb', 'cccc-dddd'],
            'two_factor_confirmed_at' => now(),
        ]);

        return [$user, $secret];
    }

    private function currentCode(string $secret): string
    {
        return (new Google2FA)->getCurrentOtp($secret);
    }

    private function login(User $user): TestResponse
    {
        return $this->post('/login', ['email' => $user->email, 'password' => self::PASSWORD]);
    }

    // --- Yang paling penting: sandi benar TIDAK berarti masuk ---------------

    public function test_a_correct_password_alone_does_not_authenticate(): void
    {
        [$user] = $this->enrolled();

        $this->login($user)->assertRedirect(route('two-factor.challenge'));

        // Inilah bedanya dengan "login dulu lalu dialihkan middleware": di sana
        // penyerang yang punya sandi sudah memegang sesi yang sah.
        $this->assertGuest();
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_an_unenrolled_user_is_sent_to_the_qr_screen(): void
    {
        $user = $this->user();

        $this->login($user)->assertRedirect(route('two-factor.setup'));
        $this->assertGuest();

        $this->get('/two-factor/setup')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->component('Auth/TwoFactorSetup')
                ->has('qrSvg')
                ->has('secret'));
    }

    public function test_the_qr_is_rendered_locally_and_leaks_nothing(): void
    {
        $this->login($this->user());

        $svg = $this->get('/two-factor/setup')->viewData('page')['props']['qrSvg'];

        $this->assertStringContainsString('<svg', $svg);
        $this->assertStringNotContainsString('<image', $svg);
        // Satu-satunya `http` yang boleh ada adalah namespace XML.
        $this->assertSame(
            ['http://www.w3.org/2000/svg'],
            array_values(array_unique(preg_match_all('~https?://[^"\'\s>]+~', $svg, $m) ? $m[0] : [])),
        );
    }

    public function test_the_secret_survives_a_page_refresh(): void
    {
        // Kalau rahasianya dibuat ulang tiap kali halaman dimuat, orang yang
        // sudah memindai QR lalu menyegarkan halaman tidak akan pernah bisa
        // memasukkan kode yang benar.
        $this->login($this->user());

        $first = $this->get('/two-factor/setup')->viewData('page')['props']['secret'];
        $second = $this->get('/two-factor/setup')->viewData('page')['props']['secret'];

        $this->assertSame($first, $second);
    }

    public function test_confirming_with_a_valid_code_enrols_and_logs_in(): void
    {
        $user = $this->user();
        $this->login($user);

        $secret = str_replace(' ', '', $this->get('/two-factor/setup')->viewData('page')['props']['secret']);

        $this->post('/two-factor/setup', ['code' => $this->currentCode($secret)])
            ->assertRedirect(route('two-factor.recovery'));

        $user->refresh();

        $this->assertTrue($user->hasConfirmedTwoFactor());
        $this->assertSame($secret, $user->two_factor_secret);
        $this->assertCount(TwoFactor::RECOVERY_CODE_COUNT, $user->two_factor_recovery_codes);
        $this->assertAuthenticatedAs($user);
    }

    public function test_confirming_with_a_wrong_code_enrols_nothing(): void
    {
        $user = $this->user();
        $this->login($user);
        $this->get('/two-factor/setup');

        $this->post('/two-factor/setup', ['code' => '000000'])->assertSessionHasErrors('code');

        $this->assertFalse($user->fresh()->hasConfirmedTwoFactor());
        $this->assertGuest();
    }

    // --- Tantangan ---------------------------------------------------------

    public function test_a_valid_code_completes_the_login(): void
    {
        [$user, $secret] = $this->enrolled();
        $this->login($user);

        $this->post('/two-factor/challenge', ['code' => $this->currentCode($secret)])
            ->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);
    }

    public function test_a_code_with_spaces_is_accepted(): void
    {
        // Aplikasi authenticator menampilkan "123 456" dan orang menyalinnya
        // apa adanya.
        [$user, $secret] = $this->enrolled();
        $this->login($user);

        $code = $this->currentCode($secret);

        $this->post('/two-factor/challenge', ['code' => substr($code, 0, 3).' '.substr($code, 3)])
            ->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);
    }

    public function test_a_wrong_code_keeps_the_user_out(): void
    {
        [$user] = $this->enrolled();
        $this->login($user);

        $this->post('/two-factor/challenge', ['code' => '000000'])->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_the_challenge_is_rate_limited(): void
    {
        [$user] = $this->enrolled();
        $this->login($user);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/two-factor/challenge', ['code' => '000000']);
        }

        // Enam digit itu sejuta kemungkinan; tanpa pembatas ini hanya soal waktu.
        $this->post('/two-factor/challenge', ['code' => '000000'])->assertSessionHasErrors('code');
        $this->assertMatchesRegularExpression(
            '/\d+/',
            session('errors')->getBag('default')->first('code'),
        );

        RateLimiter::clear("2fa:challenge:{$user->id}|127.0.0.1");
    }

    // --- Kode pemulihan ----------------------------------------------------

    public function test_a_recovery_code_works_and_is_burned_after_use(): void
    {
        [$user] = $this->enrolled();
        $this->login($user);

        $this->post('/two-factor/challenge', ['code' => 'aaaa-bbbb'])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);

        $this->assertSame(['cccc-dddd'], $user->fresh()->two_factor_recovery_codes);

        // Sekali pakai: kode yang sama tidak boleh bekerja dua kali.
        $this->post('/logout');
        $this->login($user);
        $this->post('/two-factor/challenge', ['code' => 'aaaa-bbbb'])->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_recovery_codes_are_shown_once_and_never_again(): void
    {
        $user = $this->user();
        $this->login($user);
        $secret = str_replace(' ', '', $this->get('/two-factor/setup')->viewData('page')['props']['secret']);
        $this->post('/two-factor/setup', ['code' => $this->currentCode($secret)]);

        $this->get('/two-factor/recovery-codes')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p->has('codes', TwoFactor::RECOVERY_CODE_COUNT));

        $this->get('/two-factor/recovery-codes')->assertRedirect('/dashboard');
    }

    // --- Sakelar -----------------------------------------------------------

    public function test_a_user_with_two_factor_disabled_logs_straight_in(): void
    {
        $user = $this->user(['two_factor_enabled' => false]);

        $this->login($user)->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
    }

    public function test_the_global_switch_turns_it_off_for_everyone(): void
    {
        config(['dwf.two_factor' => false]);
        [$user] = $this->enrolled();

        $this->login($user)->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
    }

    // --- Kebocoran ---------------------------------------------------------

    public function test_the_secret_never_reaches_a_serialised_user(): void
    {
        [$user] = $this->enrolled();

        $this->assertArrayNotHasKey('two_factor_secret', $user->toArray());
        $this->assertArrayNotHasKey('two_factor_recovery_codes', $user->toArray());
    }

    public function test_the_secret_is_encrypted_at_rest(): void
    {
        [$user, $secret] = $this->enrolled();

        $raw = \DB::table('users')->where('id', $user->id)->value('two_factor_secret');

        $this->assertNotSame($secret, $raw);
        $this->assertStringNotContainsString($secret, $raw);
    }

    public function test_reaching_the_challenge_without_a_password_step_is_refused(): void
    {
        $this->get('/two-factor/challenge')->assertRedirect(route('login'));
        $this->get('/two-factor/setup')->assertRedirect(route('login'));
    }

    public function test_logging_out_clears_the_pending_state(): void
    {
        [$user] = $this->enrolled();
        $this->login($user);

        $this->post('/logout');

        $this->get('/two-factor/challenge')->assertRedirect(route('login'));
    }
}
