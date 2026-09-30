<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Models\FederationStat;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Angka statistik federasi — mengisi roda di beranda (`getFederationStats`)
 * dan blok keanggotaan di `/federation-members` (`getMembershipStats`).
 *
 * Satu layar untuk dua lingkup, dipisah tab. Dua layar terpisah berarti dua
 * tempat yang mengelola bentuk baris yang identik.
 *
 * Bentuknya sengaja BUKAN daftar berhalaman: angka-angka ini sedikit (empat
 * sampai enam per lingkup), urutannya penting, dan yang dilakukan orang di sini
 * adalah menyunting semuanya sekaligus lalu menyimpan sekali — sama seperti
 * blok halaman hukum.
 */
/**
 * Statistik federasi — SATU daftar, dibaca roda beranda dan hero
 * `/federation-members` (2026-09-30). Tidak ada lagi pilihan lingkup.
 */
class FederationStatController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Federations/Stats', [
            'stats' => FederationStat::query()
                ->where('scope', FederationStat::SCOPE_HOME)
                ->ordered()
                ->get()
                ->map(fn (FederationStat $s) => [
                    'id' => $s->id,
                    'label' => $s->label,
                    'value' => $s->value,
                    'isActive' => $s->is_active,
                ])
                ->all(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'stats' => ['array', 'max:12'],
            'stats.*.label' => ['required', 'string', 'max:80'],
            'stats.*.value' => ['required', 'string', 'max:32'],
            'stats.*.is_active' => ['required', 'boolean'],
        ], attributes: [
            'stats' => __('backoffice.federations.stats'),
        ]);

        FederationStat::query()->where('scope', FederationStat::SCOPE_HOME)->delete();

        foreach (array_values($data['stats'] ?? []) as $index => $stat) {
            FederationStat::create([
                'scope' => FederationStat::SCOPE_HOME,
                'label' => $stat['label'],
                'value' => $stat['value'],
                'is_active' => $stat['is_active'],
                'position' => $index + 1,
            ]);
        }

        return back()->with('success', __('backoffice.federations.stats_saved'));
    }
}
