<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cms\SubCommitteeRequest;
use App\Models\SubCommittee;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Sub-komite — modul CRUD-nya sendiri.
 *
 * **Sebelumnya satu formulir massal**, sebangun dengan komite tetap dan
 * statistik federasi: seluruh daftar dikirim sekaligus, tabelnya dihapus, dan
 * isinya ditulis ulang dari yang datang. Bentuk itu berumur selama daftarnya
 * pendek dan dikelola satu orang. Yang dibayar untuk itu ada tiga, dan
 * ketiganya baru terasa setelah dipakai:
 *
 * - Menambah satu nama berarti menyimpan seluruh daftar, jadi dua orang yang
 *   membuka layar itu bersamaan saling menimpa tanpa tanda apa pun.
 * - Setiap Save membuat baris BARU dengan id baru. Log aktivitas jadi tidak
 *   bisa dibaca — tidak ada satu pun baris yang punya riwayat — dan apa pun
 *   yang kelak menunjuk ke sub-komite (foreign key, tautan) akan patah.
 * - Tidak ada yang bisa menghapus satu baris; yang ada cuma "kirim daftar
 *   tanpa baris itu".
 *
 * Sekarang tiap baris punya id yang tetap, disunting di tempat seperti
 * kategori berita (`433:6116`), dan urutannya disimpan terpisah dari isinya.
 * Bentuk layarnya mengikuti News Categories, bukan News Articles: entitas ini
 * dua field, dan halaman formulir tersendiri untuk dua field berarti dua kali
 * berpindah halaman demi satu kata.
 */
class SubCommitteeController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('People/SubCommittees', [
            'committees' => SubCommittee::query()
                ->with('editor:id,name')
                ->ordered()
                ->get()
                ->map(fn (SubCommittee $c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'href' => $c->href,
                    'isActive' => $c->is_active,
                    'updatedAt' => $c->updated_at?->toIso8601String(),
                    'updatedBy' => $c->editor?->name,
                ])
                ->all(),
        ]);
    }

    public function store(SubCommitteeRequest $request): RedirectResponse
    {
        SubCommittee::create([
            ...$request->validated(),
            'position' => SubCommittee::nextPosition(),
        ]);

        return back()->with('success', __('backoffice.people.sub_saved'));
    }

    public function update(SubCommitteeRequest $request, SubCommittee $committee): RedirectResponse
    {
        $committee->update($request->validated());

        return back()->with('success', __('backoffice.people.sub_updated'));
    }

    public function destroy(SubCommittee $committee): RedirectResponse
    {
        $committee->delete();

        return back()->with('success', __('backoffice.people.sub_deleted'));
    }

    /**
     * Urutan saja, terpisah dari isi.
     *
     * Menaikkan satu baris tidak boleh menuntut namanya valid — dan sebaliknya,
     * menyunting nama tidak boleh diam-diam menulis ulang posisi semua baris
     * lain. `exists` per id supaya id asing tidak menggeser urutan diam-diam.
     */
    public function reorder(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'max:40'],
            'ids.*' => ['required', 'integer', 'exists:sub_committees,id'],
        ], attributes: ['ids' => __('backoffice.people.sub_committees')]);

        SubCommittee::applyOrder($data['ids']);

        return back()->with('success', __('backoffice.people.sub_reordered'));
    }
}
