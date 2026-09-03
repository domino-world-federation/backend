<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Models\FederationStat;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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
class FederationStatController extends Controller
{
    public function index(Request $request): Response
    {
        $scope = $request->string('scope')->toString();
        $scope = in_array($scope, FederationStat::SCOPES, true) ? $scope : FederationStat::SCOPE_HOME;

        return Inertia::render('Federations/Stats', [
            'scope' => $scope,
            'stats' => FederationStat::query()
                ->where('scope', $scope)
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

    /**
     * Menulis ulang seluruh baris satu lingkup.
     *
     * Dihapus lalu ditulis ulang, bukan dicocokkan satu per satu: urutannya
     * ditentukan orang lewat susunan di layar, dan mencocokkan baris lama
     * dengan yang baru menuntut id di formulir — id yang tidak berarti apa-apa
     * bagi orang yang menyeret baris.
     */
    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'scope' => ['required', Rule::in(FederationStat::SCOPES)],
            'stats' => ['array', 'max:12'],
            'stats.*.label' => ['required', 'string', 'max:80'],

            // String, BUKAN integer: desainnya mencetak "57", "1974", dan
            // "120+" di slot yang sama.
            'stats.*.value' => ['required', 'string', 'max:32'],
            'stats.*.is_active' => ['required', 'boolean'],
        ], attributes: [
            'stats' => __('backoffice.federations.stats'),
        ]);

        FederationStat::query()->where('scope', $data['scope'])->delete();

        foreach (array_values($data['stats'] ?? []) as $index => $stat) {
            FederationStat::create([
                'scope' => $data['scope'],
                'label' => $stat['label'],
                'value' => $stat['value'],
                'is_active' => $stat['is_active'],
                'position' => $index + 1,
            ]);
        }

        return back()->with('success', __('backoffice.federations.stats_saved'));
    }
}
