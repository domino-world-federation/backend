<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ContactSettingController extends Controller
{
    /**
     * Kunci yang dikelola layar ini, beserta aturannya.
     *
     * Satu daftar dipakai untuk tiga hal: nilai awal, validasi, dan
     * penyimpanan. Menyalinnya jadi tiga daftar berarti suatu saat ada field
     * yang bisa diisi tapi tidak pernah tersimpan.
     */
    private const FIELDS = [
        'primary_email' => ['required', 'email', 'max:160'],
        'footer_address_label' => ['required', 'string', 'max:160'],
        'headquarters_address' => ['required', 'string', 'max:400'],
        'form_recipient_email' => ['required', 'email', 'max:160'],
        'social_instagram' => ['nullable', 'string', 'max:120'],
        'social_tiktok' => ['nullable', 'string', 'max:120'],
        'social_x' => ['nullable', 'string', 'max:120'],
        'social_facebook' => ['nullable', 'string', 'max:120'],
        'social_youtube' => ['nullable', 'string', 'max:120'],
    ];

    public function edit(): Response
    {
        $stored = SiteSetting::map(SiteSetting::GROUP_CONTACT);

        return Inertia::render('Settings/ContactSocial', [
            'settings' => collect(self::FIELDS)
                ->map(fn (array $rules, string $key) => $stored[$key] ?? '')
                ->all(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate(self::FIELDS, attributes: [
            'primary_email' => 'Primary Email',
            'footer_address_label' => 'Footer Address Label',
            'headquarters_address' => 'Full Headquarters Address',
            'form_recipient_email' => 'Form Recipient Email',
        ]);

        $before = SiteSetting::map(SiteSetting::GROUP_CONTACT);

        SiteSetting::putMany($data, SiteSetting::GROUP_CONTACT);

        // Satu entri log untuk satu kali Simpan, berisi hanya kunci yang
        // benar-benar berubah. Kalau tidak ada yang berubah, tidak ada entri.
        $changed = collect($data)
            ->filter(fn (?string $value, string $key) => ($before[$key] ?? null) !== $value)
            ->all();

        if ($changed !== []) {
            activity('site-settings')
                ->causedBy($request->user())
                ->event('updated')
                ->withProperties([
                    'attributes' => $changed,
                    'old' => collect($changed)->map(fn ($v, $k) => $before[$k] ?? null)->all(),
                ])
                ->log('updated');
        }

        return back()->with('success', __('backoffice.settings.saved'));
    }
}
