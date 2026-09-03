<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Naskah halaman depan — dan HANYA naskah yang tidak dimiliki modul lain.
 *
 * Menggantikan grup "Landing Page" berisi delapan submenu di komponen sidebar
 * `252:3403`. Lima di antaranya akan jadi pintu kedua ke modul yang datanya
 * memang tinggal di sana (statistik → Federations, enam kartu → Tournaments,
 * rel berita → News, deret logo → Partners), satu menduplikasi widget
 * kelengkapan di Dashboard, dan satu ("About / Mission") menunjuk section yang
 * tidak ada di halaman itu. Yang benar-benar yatim cuma dua: naskah hero dan
 * band ajakan penutup, keduanya sebelumnya tertulis keras di repo situs publik
 * (`content/home/hero.ts`, `content/home/join.ts`).
 *
 * Disimpan di `site_settings` dengan `group = home`, bukan tabel sendiri: dua
 * penyimpanan kunci-nilai yang bentuknya sama persis berarti dua tempat untuk
 * mencari saat sebuah nilai tidak muncul di situs.
 */
class HomePageController extends Controller
{
    /**
     * Kunci yang dikelola layar ini, per kartu.
     *
     * Daftar tetap, bukan tabel: tiap kunci punya slotnya sendiri di halaman
     * depan, dan menambah baris di sini tanpa slotnya ada di sana menghasilkan
     * field yang tidak pernah tampil di mana pun.
     */
    private const FIELDS = [
        'hero' => [
            'hero_tagline', 'hero_headline', 'hero_mission', 'hero_accountability',
            'hero_primary_cta', 'hero_primary_cta_url',
            'hero_secondary_cta', 'hero_secondary_cta_url',
        ],
        'closing' => [
            'closing_headline', 'closing_body', 'closing_cta', 'closing_cta_url',
        ],
    ];

    /** Kunci yang isinya tautan — divalidasi berbeda dari teks biasa. */
    private const URL_FIELDS = [
        'hero_primary_cta_url', 'hero_secondary_cta_url', 'closing_cta_url',
    ];

    public function edit(): Response
    {
        $stored = SiteSetting::map(SiteSetting::GROUP_HOME);

        return Inertia::render('HomePage/Form', [
            'values' => collect(self::FIELDS)
                ->flatten()
                ->mapWithKeys(fn (string $key) => [$key => $stored[$key] ?? ''])
                ->all(),

            // Bagian halaman depan yang TIDAK disunting di sini, beserta
            // tujuannya. Dicetak, bukan disembunyikan: pertanyaan pertama orang
            // yang membuka layar ini adalah "kenapa statistiknya tidak ada",
            // dan jawabannya lebih berguna daripada ketiadaannya.
            'elsewhere' => [
                ['key' => 'stats', 'href' => '/federations/stats'],
                ['key' => 'featured_event', 'href' => '/tournaments'],
                ['key' => 'news', 'href' => '/news'],
                ['key' => 'partners', 'href' => '/blocks'],
                ['key' => 'faq', 'href' => '/faq/pages'],
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate($this->rules());

        $before = SiteSetting::map(SiteSetting::GROUP_HOME);
        SiteSetting::putMany($data, SiteSetting::GROUP_HOME);

        $changed = collect($data)
            ->filter(fn (?string $value, string $key) => ($before[$key] ?? null) !== $value)
            ->all();

        // Satu entri untuk satu kali Simpan — alasan yang sama dengan
        // `ContactSettingController`: dua belas baris ditulis sekaligus, dan
        // dua belas entri log untuk satu tindakan menenggelamkan jejaknya.
        if ($changed !== []) {
            activity('home-page')
                ->causedBy($request->user())
                ->event('updated')
                ->withProperties([
                    'attributes' => $changed,
                    'old' => collect($changed)->map(fn ($v, $k) => $before[$k] ?? null)->all(),
                ])
                ->log('updated');
        }

        return back()->with('success', __('backoffice.home_page.saved'));
    }

    /** @return array<string, array<int, mixed>> */
    private function rules(): array
    {
        $rules = [];

        foreach (collect(self::FIELDS)->flatten() as $key) {
            $rules[$key] = in_array($key, self::URL_FIELDS, true)
                // Path internal (`/federation-members`), jangkar (`#`), atau URL
                // penuh. Diperiksa DI SINI karena tautan yang salah ketik gagal
                // diam-diam: tombolnya tetap tergambar dan tetap bisa ditekan.
                ? ['required', 'string', 'max:300', 'regex:#^(/[^\s]*|\#[^\s]*|https?://[^\s]+)$#']
                : ['required', 'string', 'max:600'];
        }

        return $rules;
    }
}
