<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Models\BoardMember;
use App\Models\StandingCommittee;
use App\Models\SubCommittee;
use App\Support\Media\StoredFile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * People & Governance — tiga daftar yang mengisi `/about` dan `/governance`.
 *
 * Tidak ada layar desainnya di kanvas Backoffice; bentuknya meminjam pola modul
 * lain. Penyimpangannya dicatat di docs/PROGRESS.md.
 *
 * Dua bentuk penyuntingan, dan pemilihannya bukan selera: daftar yang punya
 * GAMBAR memakai formulir kartu (unggahan butuh ruang dan pratinjau), daftar
 * yang seluruhnya teks disunting langsung di barisnya lalu disimpan sekali.
 */
class PeopleController extends Controller
{
    // ------------------------------------------------ Executive Board (gambar)

    public function index(): Response
    {
        return Inertia::render('People/Board', [
            'members' => BoardMember::query()->with('editor:id,name')->ordered()->get()
                ->map(fn (BoardMember $m) => [
                    'id' => $m->id,
                    'name' => $m->name,
                    'role' => $m->role,
                    'portraitUrl' => StoredFile::url($m->portrait_path),
                    'portraitAlt' => $m->portrait_alt,
                    'isActive' => $m->is_active,
                    'updatedAt' => $m->updated_at?->toIso8601String(),
                    'updatedBy' => $m->editor?->name,
                ])
                ->all(),
        ]);
    }

    public function storeMember(Request $request): RedirectResponse
    {
        BoardMember::create($this->memberPayload($request) + ['position' => BoardMember::nextPosition()]);

        return back()->with('success', __('backoffice.people.member_saved'));
    }

    public function updateMember(Request $request, BoardMember $member): RedirectResponse
    {
        $member->update($this->memberPayload($request, $member));

        return back()->with('success', __('backoffice.people.member_updated'));
    }

    public function destroyMember(BoardMember $member): RedirectResponse
    {
        StoredFile::forget($member->portrait_path);
        $member->delete();

        return back()->with('success', __('backoffice.people.member_deleted'));
    }

    /** @return array<string, mixed> */
    private function memberPayload(Request $request, ?BoardMember $member = null): array
    {
        $uploads = config('dwf.uploads');

        $data = $request->validate([
            // Baris baru DIIZINKAN: kartunya merender nama dua baris kalau ada.
            'name' => ['required', 'string', 'max:160'],
            'role' => ['required', 'string', 'max:120'],
            // WAJIB saat membuat, dengan alasan yang sama seperti logo partner:
            // `BoardCard` di situs publik menggambar `<NuxtImg :src>` tanpa
            // penjagaan, jadi anggota tanpa potret jadi gambar rusak — bukan
            // kartu tanpa gambar. Boleh kosong saat MENYUNTING supaya
            // membetulkan salah ketik nama tidak menuntut unggah ulang.
            'portrait' => [
                $member === null ? 'required' : 'nullable', 'image',
                'mimes:'.implode(',', $uploads['image_mimes']),
                'max:'.$uploads['image_max_kb'],
            ],
            'portrait_alt' => ['nullable', 'string', 'max:200'],
            'is_active' => ['required', 'boolean'],
        ], attributes: [
            'name' => __('backoffice.people.member_name'),
            'role' => __('backoffice.people.member_role'),
        ]);

        $payload = [
            'name' => $data['name'],
            'role' => $data['role'],
            'portrait_alt' => $data['portrait_alt'] ?? null,
            'is_active' => $data['is_active'],
        ];

        if ($request->hasFile('portrait')) {
            $payload['portrait_path'] = StoredFile::put(
                $request->file('portrait'),
                'board',
                $member?->portrait_path,
            );
        }

        return $payload;
    }

    // --------------------------------------------- Sub-committees (teks saja)

    public function subCommittees(): Response
    {
        return Inertia::render('People/SubCommittees', [
            'committees' => SubCommittee::query()->ordered()->get()
                ->map(fn (SubCommittee $c) => [
                    'name' => $c->name,
                    'href' => $c->href,
                    'is_active' => $c->is_active,
                ])
                ->all(),
        ]);
    }

    /** Menulis ulang seluruh daftar — urutannya dari susunan di layar. */
    public function updateSubCommittees(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'committees' => ['array', 'max:40'],
            'committees.*.name' => ['required', 'string', 'max:160'],
            // Boleh kosong: halaman tujuannya belum tentu ada.
            'committees.*.href' => ['nullable', 'string', 'max:200'],
            'committees.*.is_active' => ['required', 'boolean'],
        ], attributes: ['committees' => __('backoffice.people.sub_committees')]);

        SubCommittee::query()->delete();

        foreach (array_values($data['committees'] ?? []) as $index => $row) {
            SubCommittee::create($row + ['position' => $index + 1]);
        }

        return back()->with('success', __('backoffice.people.sub_saved'));
    }

    // ------------------------------------------ Standing committees (teks saja)

    public function committees(): Response
    {
        return Inertia::render('People/Committees', [
            'committees' => StandingCommittee::query()->ordered()->get()
                ->map(fn (StandingCommittee $c) => [
                    'name' => $c->name,
                    // Digabung koma untuk SATU kotak isian; dipecah lagi saat
                    // disimpan. Tiga pil di desain terlalu sedikit untuk
                    // dijadikan kelompok berulang tersendiri.
                    'remit' => implode(', ', $c->remit ?? []),
                    'is_active' => $c->is_active,
                ])
                ->all(),
        ]);
    }

    public function updateCommittees(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'committees' => ['array', 'max:40'],
            'committees.*.name' => ['required', 'string', 'max:160'],
            'committees.*.remit' => ['nullable', 'string', 'max:300'],
            'committees.*.is_active' => ['required', 'boolean'],
        ], attributes: ['committees' => __('backoffice.people.committees')]);

        StandingCommittee::query()->delete();

        foreach (array_values($data['committees'] ?? []) as $index => $row) {
            StandingCommittee::create([
                'name' => $row['name'],
                'remit' => $this->splitRemit($row['remit'] ?? ''),
                'is_active' => $row['is_active'],
                'position' => $index + 1,
            ]);
        }

        return back()->with('success', __('backoffice.people.committees_saved'));
    }

    /**
     * "Players, KYC, federation content" -> tiga pil.
     *
     * Bagian kosong dibuang: koma di ujung adalah salah ketik yang lazim, dan
     * tanpa penyaringan ini ia jadi pil kosong di halaman publik.
     *
     * @return array<int, string>
     */
    private function splitRemit(string $value): array
    {
        return collect(explode(',', $value))
            ->map(fn (string $part) => trim($part))
            ->filter()
            ->values()
            ->all();
    }
}
