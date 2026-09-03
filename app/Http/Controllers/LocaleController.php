<?php

namespace App\Http\Controllers;

use App\Support\Locales;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LocaleController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        // Route-nya tetap terdaftar tapi menolak saat pengalihnya mati —
        // diperiksa di sini, bukan saat mendaftarkan route, supaya perubahan
        // `.env` langsung berlaku tanpa `route:clear`.
        abort_unless(Locales::isSwitchable(), 404);

        $validated = $request->validate([
            'locale' => ['required', Rule::in(array_keys(Locales::SUPPORTED))],
        ]);

        // Ditulis ke DUANYA: kolom user supaya pilihannya ikut ke perangkat
        // lain, dan sesi supaya halaman login berikutnya — yang belum tahu
        // siapa yang datang — sudah memakai bahasa yang benar.
        $request->session()->put('locale', $validated['locale']);
        $request->user()?->forceFill(['locale' => $validated['locale']])->save();

        return back();
    }
}
