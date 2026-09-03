<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use App\Rules\ValidRecaptcha;
use App\Support\Security\Recaptcha;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['boolean'],

            // Hanya diminta kalau kuncinya terpasang. Tanpa penjagaan ini,
            // `.env` lokal yang kosong membuat login mustahil.
            'recaptcha_token' => Recaptcha::isEnabled()
                ? ['required', 'string', new ValidRecaptcha]
                : ['nullable'],
        ];
    }

    /**
     * Pengguna yang kredensialnya terbukti benar — TANPA membuat sesi login.
     *
     * `Auth::attempt()` langsung menaruh pengguna ke dalam sesi, dan itu salah
     * di sini: kalau 2FA menyala, sandi yang benar belum boleh berarti masuk.
     * Jadi kata sandinya diperiksa sendiri, dan controller yang memutuskan
     * kapan `Auth::login()` dipanggil.
     *
     * @throws ValidationException
     */
    public function verifiedUser(): User
    {
        $this->ensureIsNotRateLimited();

        $user = User::where('email', $this->string('email')->toString())->first();

        // `Hash::check` tetap dijalankan walau penggunanya tidak ada, memakai
        // hash palsu, supaya lama respons tidak membocorkan email mana yang
        // terdaftar.
        $hash = $user?->password ?? '$2y$12$'.str_repeat('x', 53);

        if ($user === null || ! Hash::check($this->string('password')->toString(), $hash)) {
            RateLimiter::hit($this->throttleKey());

            // Ditembakkan sendiri karena `Auth::attempt()` tidak dipakai di
            // sini — dan `attempt()`-lah yang biasanya menembakkannya. Tanpa
            // baris ini percobaan gagal tidak pernah masuk log otentikasi, dan
            // yang paling ingin diketahui saat menyelidiki justru itu.
            event(new Failed('web', $user, $this->only('email', 'password')));

            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        /*
         * Akun NONAKTIF ditolak sesudah sandinya terbukti benar, bukan sebelum.
         *
         * Urutannya disengaja: memeriksa `is_active` lebih dulu akan membalas
         * cepat untuk email yang ada dan lambat untuk yang tidak, dan selisih
         * itu memberi tahu penebak alamat mana yang terdaftar — persis yang
         * dicegah hash palsu di atas.
         *
         * Pesannya juga sama dengan sandi salah. "Akun Anda dinonaktifkan"
         * mengonfirmasi bahwa email DAN sandinya benar, yang berguna bagi orang
         * yang baru saja dicabut aksesnya.
         */
        if (! $user->is_active) {
            RateLimiter::hit($this->throttleKey());

            event(new Failed('web', $user, $this->only('email', 'password')));

            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());

        return $user;
    }

    /**
     * @throws ValidationException
     */
    protected function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => __('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /** Per email + IP: satu penyerang tidak bisa mengunci akun orang lain. */
    protected function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }
}
