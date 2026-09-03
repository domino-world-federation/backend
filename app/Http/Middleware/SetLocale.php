<?php

namespace App\Http\Middleware;

use App\Support\Locales;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Memasang bahasa untuk request ini.
 *
 * Urutannya: preferensi user yang login -> pilihan tamu di sesi -> bawaan.
 * Tamu perlu ikut dilayani karena halaman login juga diterjemahkan, dan di
 * sana belum ada user yang bisa ditanya.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        // Preferensi tersimpan HANYA dibaca kalau pengalihnya menyala.
        //
        // Tanpa penjagaan ini, mematikan pengalih akan mengunci siapa pun yang
        // pernah memilih bahasa lain: `users.locale` tetap terbaca, sementara
        // tombol untuk mengubahnya sudah hilang dari layar.
        $locale = Locales::isSwitchable()
            ? ($request->user()?->locale ?? $request->session()->get('locale'))
            : null;

        App::setLocale(Locales::sanitize($locale));

        return $next($request);
    }
}
