<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Models\LegalPage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Mews\Purifier\Facades\Purifier;

class LegalPageController extends Controller
{
    /**
     * Halaman hukum yang dikelola layar ini.
     *
     * Daftar tetap, bukan tabel: tiap kunci punya rutenya sendiri di situs
     * publik (`/page/{key}`), jadi menambah baris di sini tanpa halamannya ada
     * di sana menghasilkan menu yang berujung 404. Barisnya dibuat sendiri saat
     * pertama dibuka — lihat `pageFor()`.
     */
    private const TITLES = [
        'privacy-policy' => 'Privacy Policy',
        'terms' => 'Terms & Conditions',
        'cookie-policy' => 'Cookie Policy',
    ];

    public function index(): Response
    {
        return Inertia::render('Legal/Index', [
            'pages' => collect(self::TITLES)
                ->map(function (string $title, string $key) {
                    $page = LegalPage::query()->where('key', $key)->withCount('blocks')->first();

                    return [
                        'key' => $key,
                        'title' => $title,
                        'slug' => $page?->slug ?? $key,
                        'blocks' => $page?->blocks_count ?? 0,
                        'lastUpdatedAt' => $page?->last_updated_at?->toDateString(),
                        'href' => "/legal-pages/{$key}",
                    ];
                })
                ->values()
                ->all(),
        ]);
    }

    public function edit(string $key): Response
    {
        abort_unless(array_key_exists($key, self::TITLES), 404);

        $page = $this->pageFor($key);

        return Inertia::render('Legal/Form', [
            'page' => [
                'key' => $page->key,
                'title' => self::TITLES[$key],
                'slug' => $page->slug,
                'lastUpdatedAt' => $page->last_updated_at?->toDateString(),
                // Baris "Last Modified · nama · waktu" di bawah judul
                // (`258:8086`). Berbeda dari `lastUpdatedAt`, yang tanggal
                // pilihan REDAKSI dan tampil di situs publik.
                'lastModifiedBy' => $page->editor?->name,
                'lastModifiedAt' => $page->updated_at?->toIso8601String(),
                'blocks' => $page->blocks->map(fn ($b) => [
                    'id' => $b->id,
                    'title' => $b->title,
                    'description' => $b->description,
                    'isActive' => $b->is_active,
                ])->values()->all(),
            ],
        ]);
    }

    public function update(Request $request, string $key): RedirectResponse
    {
        abort_unless(array_key_exists($key, self::TITLES), 404);

        $page = $this->pageFor($key);

        $data = $request->validate([
            'slug' => ['required', 'string', 'max:120', Rule::unique('legal_pages', 'slug')->ignore($page->id)],
            'last_updated_at' => ['required', 'date'],
            'blocks' => ['array'],
            'blocks.*.title' => ['required', 'string', 'max:200'],
            'blocks.*.description' => ['required', 'string', 'max:20000'],
            'blocks.*.is_active' => ['required', 'boolean'],
        ], attributes: [
            'slug' => 'slug',
            'last_updated_at' => 'tanggal pembaruan',
        ]);

        // Blok ditulis ulang seluruhnya di dalam satu transaksi.
        //
        // Layar ini membiarkan orang menambah, menghapus, dan menyusun ulang
        // blok sekaligus lalu menekan Save satu kali. Menyinkronkannya baris
        // per baris berarti kalau request putus di tengah, halaman hukum
        // situs — yang justru harus tepat — tersimpan setengah jadi.
        DB::transaction(function () use ($page, $data) {
            $page->update([
                'slug' => $data['slug'],
                'last_updated_at' => $data['last_updated_at'],
            ]);

            $page->blocks()->delete();

            foreach (array_values($data['blocks'] ?? []) as $index => $block) {
                $page->blocks()->create([
                    'title' => $block['title'],
                    /*
                     * Deskripsinya HTML sekarang, dibersihkan profil `legal`.
                     *
                     * ── Ini MEMBALIK keputusan sebelumnya. ──
                     *
                     * Dulu ia `strip_tags()` dengan alasan yang benar pada
                     * waktunya: kontrolnya textarea polos, dan
                     * `AutoFormat.AutoParagraph` membungkus hasilnya dengan
                     * `<p>` yang lalu muncul terketik di dalam kotaknya sendiri
                     * pada muat berikutnya.
                     *
                     * Kontrolnya kini editor dasar, dan itu mencabut alasannya:
                     * tiptap sudah mengirim isinya terbungkus `<p>`, jadi
                     * AutoParagraph tidak menambah apa pun. Yang tersisa cuma
                     * kebutuhan membersihkannya — dan itu WAJIB, karena HTML ini
                     * dirender di situs publik.
                     *
                     * Profil `legal` (`config/purifier.php`) jauh lebih sempit
                     * daripada `default`: tanpa judul, gambar, blockquote, kode,
                     * maupun `style`. Bloknya sudah punya judulnya sendiri di
                     * field terpisah.
                     */
                    'description' => Purifier::clean($block['description'], 'legal'),
                    'is_active' => $block['is_active'],
                    'position' => $index + 1,
                ]);
            }

            /*
             * Dipaksa menyentuh timestamp-nya.
             *
             * `update()` di atas TIDAK menulis apa pun kalau slug, tanggal, dan
             * penyuntingnya kebetulan sama seperti sebelumnya — Eloquent
             * melewati baris yang tidak kotor. Padahal yang berubah justru
             * bloknya, dan blok tinggal di tabel lain. Tanpa `touch()`, baris
             * "Last Modified" di bawah judul berhenti bergerak persis pada
             * penyuntingan yang paling sering terjadi: mengubah isi, bukan slug.
             */
            $page->touch();
        });

        return back()->with('success', __('backoffice.legal.saved', ['page' => self::TITLES[$key]]));
    }

    private function pageFor(string $key): LegalPage
    {
        return LegalPage::query()->firstOrCreate(
            ['key' => $key],
            ['title' => self::TITLES[$key], 'slug' => $key, 'last_updated_at' => now()->toDateString()],
        )->load('blocks');
    }
}
