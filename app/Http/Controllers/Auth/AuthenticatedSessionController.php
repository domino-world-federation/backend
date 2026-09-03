<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Support\Security\Recaptcha;
use App\Support\Security\TwoFactorSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            // `null` berarti captcha mati — halaman login tidak menggambar
            // apa pun dan tidak memuat skrip Google sama sekali.
            'recaptchaSiteKey' => Recaptcha::siteKey(),
        ]);
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $user = $request->verifiedUser();

        if ($user->requiresTwoFactor()) {
            // Sandinya benar, tapi ia BELUM login. Sesi digenerasi ulang di
            // sini juga: penanda "menunggu 2FA" adalah nilai sesi, dan nilai
            // itu tidak boleh mendarat di id sesi yang sudah dipegang orang
            // lain sebelum halaman login dibuka.
            $request->session()->regenerate();

            TwoFactorSession::start($request, $user, $request->boolean('remember'));

            return redirect()->route(
                $user->hasConfirmedTwoFactor() ? 'two-factor.challenge' : 'two-factor.setup',
            );
        }

        Auth::login($user, $request->boolean('remember'));

        // Wajib: tanpa ini id sesi sebelum dan sesudah login sama, dan sesi
        // yang ditanam penyerang ikut naik pangkat jadi sesi admin.
        $request->session()->regenerate();

        return redirect()->intended('/dashboard');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        TwoFactorSession::forget($request);
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }
}
