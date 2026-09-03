<?php

namespace App\Support\Security;

use App\Models\User;
use Illuminate\Http\Request;

/**
 * Sesi "sudah benar sandinya, belum lolos 2FA".
 *
 * Pengguna di keadaan ini **belum** login: `Auth::login()` baru dipanggil
 * setelah kode terbukti benar. Itu bedanya dengan menaruh sesi login lebih
 * dulu lalu mengalihkannya lewat middleware — cara itu memberi penyerang yang
 * sudah punya sandi sebuah sesi yang sah, dan yang menahannya cuma redirect.
 */
final class TwoFactorSession
{
    private const ID = 'two_factor.user_id';

    private const REMEMBER = 'two_factor.remember';

    public static function start(Request $request, User $user, bool $remember): void
    {
        $request->session()->put(self::ID, $user->id);
        $request->session()->put(self::REMEMBER, $remember);
    }

    public static function user(Request $request): ?User
    {
        $id = $request->session()->get(self::ID);

        return $id === null ? null : User::find($id);
    }

    public static function remember(Request $request): bool
    {
        return (bool) $request->session()->get(self::REMEMBER, false);
    }

    public static function forget(Request $request): void
    {
        $request->session()->forget([self::ID, self::REMEMBER]);
    }
}
