<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Security\TwoFactor;
use App\Support\Security\TwoFactorSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pendaftaran dan verifikasi 2FA.
 *
 * Tiga layar, satu alur:
 *   1. `setup`     — QR untuk dipindai (hanya kalau belum pernah didaftarkan)
 *   2. `challenge` — enam kotak kode
 *   3. `recovery`  — kode pemulihan, ditampilkan SEKALI setelah pendaftaran
 */
class TwoFactorController extends Controller
{
    public function setup(Request $request): Response|RedirectResponse
    {
        $user = $this->pendingUser($request);

        if ($user === null) {
            return redirect()->route('login');
        }

        if ($user->hasConfirmedTwoFactor()) {
            return redirect()->route('two-factor.challenge');
        }

        // Rahasia dibuat sekali lalu DITAHAN di sesi sampai dikonfirmasi.
        //
        // Kalau ia dibuat ulang di tiap kali halaman dimuat, orang yang sudah
        // memindai QR lalu menyegarkan halaman akan memegang rahasia lama
        // sementara server sudah pindah ke yang baru — dan tiap kode yang ia
        // masukkan akan ditolak tanpa alasan yang kelihatan.
        $secret = $request->session()->get('two_factor.pending_secret');

        if (blank($secret)) {
            $secret = TwoFactor::generateSecret();
            $request->session()->put('two_factor.pending_secret', $secret);
        }

        return Inertia::render('Auth/TwoFactorSetup', [
            'qrSvg' => TwoFactor::qrSvg($secret, $user->email),
            // Ditampilkan sebagai teks juga: sebagian orang memasukkannya
            // manual karena kameranya rusak, atau memakai pengelola sandi di
            // perangkat yang sama sehingga tidak ada kamera untuk memindai.
            'secret' => trim(chunk_split($secret, 4, ' ')),
        ]);
    }

    public function confirm(Request $request): RedirectResponse
    {
        $user = $this->pendingUser($request);

        if ($user === null) {
            return redirect()->route('login');
        }

        $secret = $request->session()->get('two_factor.pending_secret');
        $code = $request->validate(['code' => ['required', 'string']])['code'];

        $this->throttle($request, 'confirm');

        if (blank($secret) || ! TwoFactor::verify($secret, $code)) {
            throw ValidationException::withMessages([
                'code' => __('backoffice.two_factor.invalid_code'),
            ]);
        }

        $codes = TwoFactor::generateRecoveryCodes();

        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => $codes,
            'two_factor_confirmed_at' => now(),
        ])->save();

        $request->session()->forget('two_factor.pending_secret');
        $this->clearThrottle($request, 'confirm');

        // Kode pemulihan ditaruh di sesi, bukan di query string: URL tercatat
        // di riwayat browser dan log server.
        $request->session()->flash('two_factor.recovery_codes', $codes);

        $this->completeLogin($request, $user);

        return redirect()->route('two-factor.recovery');
    }

    public function challenge(Request $request): Response|RedirectResponse
    {
        $user = $this->pendingUser($request);

        if ($user === null) {
            return redirect()->route('login');
        }

        if (! $user->hasConfirmedTwoFactor()) {
            return redirect()->route('two-factor.setup');
        }

        return Inertia::render('Auth/TwoFactorChallenge');
    }

    public function verify(Request $request): RedirectResponse
    {
        $user = $this->pendingUser($request);

        if ($user === null) {
            return redirect()->route('login');
        }

        $code = $request->validate(['code' => ['required', 'string']])['code'];

        // Enam digit itu sejuta kemungkinan — tanpa pembatas, menebaknya cuma
        // soal waktu. Dibatasi terpisah dari throttle kata sandi karena ini
        // tahap yang berbeda dengan biaya yang berbeda.
        $this->throttle($request, 'challenge');

        $passed = TwoFactor::verify($user->two_factor_secret, $code)
            || $user->consumeRecoveryCode($code);

        if (! $passed) {
            throw ValidationException::withMessages([
                'code' => __('backoffice.two_factor.invalid_code'),
            ]);
        }

        $this->clearThrottle($request, 'challenge');
        $this->completeLogin($request, $user);

        return redirect()->intended('/dashboard');
    }

    public function recovery(Request $request): Response|RedirectResponse
    {
        $codes = $request->session()->get('two_factor.recovery_codes');

        // Hanya bisa dilihat sekali, tepat setelah pendaftaran. Memuat ulang
        // halaman ini tidak menampilkannya lagi — itu memang maksudnya.
        if (blank($codes)) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('Auth/TwoFactorRecovery', ['codes' => $codes]);
    }

    // -----------------------------------------------------------------------

    /**
     * Pengguna yang sedang menunggu 2FA, atau yang sudah login tapi 2FA-nya
     * baru saja dinyalakan oleh admin lewat User Management.
     */
    private function pendingUser(Request $request): ?User
    {
        return TwoFactorSession::user($request) ?? $request->user();
    }

    private function completeLogin(Request $request, User $user): void
    {
        if (! Auth::check()) {
            Auth::login($user, TwoFactorSession::remember($request));
        }

        TwoFactorSession::forget($request);
        $request->session()->regenerate();
    }

    private function throttleKey(Request $request, string $stage): string
    {
        $id = TwoFactorSession::user($request)?->id ?? $request->user()?->id ?? 'tamu';

        return Str::transliterate("2fa:{$stage}:{$id}|".$request->ip());
    }

    /** @throws ValidationException */
    private function throttle(Request $request, string $stage): void
    {
        $key = $this->throttleKey($request, $stage);

        if (! RateLimiter::tooManyAttempts($key, 5)) {
            RateLimiter::hit($key, 300);

            return;
        }

        throw ValidationException::withMessages([
            'code' => __('auth.throttle', [
                'seconds' => $seconds = RateLimiter::availableIn($key),
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    private function clearThrottle(Request $request, string $stage): void
    {
        RateLimiter::clear($this->throttleKey($request, $stage));
    }
}
