<?php

namespace App\Http\Middleware;

use App\Support\Locales;
use App\Support\Navigation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Lang;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),

            'auth' => [
                'user' => $user === null ? null : [
                    'name' => $user->name,
                    'email' => $user->email,
                    'avatarUrl' => $user->avatar_url,
                ],
            ],

            // Sidebar hanya digambar untuk sesi yang sudah masuk; mengirim
            // strukturnya ke halaman login berarti membocorkan peta backoffice
            // ke siapa pun yang membuka /login.
            'navigation' => Navigation::forUser($user),

            // Kamus dikirim UTUH, bukan per halaman.
            //
            // Berkasnya ~270 kunci (~12 KB sebelum gzip) dan ia ikut di setiap
            // respons Inertia — itu harganya. Yang dibeli: satu `t()` yang
            // selalu punya jawabannya, tanpa tiap controller harus ingat
            // kunci mana yang dipakai halamannya. Kalau nanti berkasnya tumbuh
            // sampai puluhan kali lipat, pecah per modul; bukan sekarang.
            'locale' => App::getLocale(),
            // Daftar pilihan dikirim kosong saat pengalihnya mati — halaman
            // tidak perlu tahu bahasa apa saja yang ada kalau tidak boleh
            // memilihnya.
            'localeSwitchable' => Locales::isSwitchable(),
            'locales' => Locales::isSwitchable() ? Locales::options() : [],
            'translations' => Lang::get('backoffice'),

            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }
}
