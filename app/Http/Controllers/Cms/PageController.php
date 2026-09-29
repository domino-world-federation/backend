<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use App\Support\PageContent;
use App\Support\PreviewToken;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Editor halaman — naskah halaman publik, dengan pratinjau situs asli di
 * samping formulirnya.
 *
 * Layar ini tidak menggambar pratinjaunya sendiri: iframe-nya ADALAH situs
 * publik, dibuka dengan token pratinjau sehingga membaca draf. Yang dikirim
 * ke sini hanya skema, nilai, dan alamat iframe; klik di pratinjau dan ketikan
 * di panel saling dikirim lewat `postMessage` di sisi browser.
 *
 * Skema: `config('dwf.pages')`. Penyimpanan: `App\Support\PageContent`.
 */
class PageController extends Controller
{
    public function index(): Response
    {
        $pages = collect(PageContent::pages())->map(function (array $page, string $key) {
            $latest = SiteSetting::query()
                ->where('group', PageContent::group($key))
                ->with('editor:id,name')
                ->latest('updated_at')
                ->first();

            return [
                'key' => $key,
                'label' => $page['label'],
                'path' => $page['path'],
                'fields' => count(PageContent::fields($key)),
                'drafts' => count(PageContent::drafts($key)),
                'updatedAt' => $latest?->updated_at?->toIso8601String(),
                'updatedBy' => $latest?->editor?->name,
            ];
        })->values();

        return Inertia::render('Pages/Index', ['pages' => $pages]);
    }

    public function edit(string $page): Response
    {
        abort_unless(PageContent::exists($page), 404);

        $meta = PageContent::pages()[$page];
        $site = config('dwf.site_url');

        return Inertia::render('Pages/Edit', [
            'page' => ['key' => $page] + $meta,
            'sections' => PageContent::sections($page),
            'published' => PageContent::published($page),
            'drafts' => PageContent::drafts($page),
            'previewUrl' => $site.$meta['path'].'?cms-preview='.urlencode(PreviewToken::make($page)),
            // Origin iframe, untuk memeriksa pesan yang datang darinya dan
            // mengalamatkan pesan yang dikirim ke sana.
            'siteOrigin' => self::origin($site),
        ]);
    }

    public function saveDraft(Request $request, string $page): RedirectResponse
    {
        abort_unless(PageContent::exists($page), 404);

        PageContent::saveDraft($page, $this->values($request, $page));

        return back()->with('success', __('backoffice.pages.draft_saved'));
    }

    /**
     * Terbitkan = simpan apa yang sedang di layar sebagai draf, lalu
     * terbitkan semua draf halaman ini. Satu tombol, karena "Terbitkan" yang
     * mengabaikan ketikan yang belum disimpan akan menerbitkan versi yang
     * berbeda dari yang sedang dilihat orangnya.
     */
    public function publish(Request $request, string $page): RedirectResponse
    {
        abort_unless(PageContent::exists($page), 404);

        PageContent::saveDraft($page, $this->values($request, $page));
        $changes = PageContent::publish($page);

        // Satu entri untuk satu kali terbit — alasan yang sama dengan
        // `HomePageController`: puluhan field ditulis sekaligus.
        if ($changes !== []) {
            activity('page-editor')
                ->causedBy($request->user())
                ->event('published')
                ->withProperties([
                    'page' => $page,
                    'attributes' => collect($changes)->map(fn (array $c) => $c['new'])->all(),
                    'old' => collect($changes)->map(fn (array $c) => $c['old'])->all(),
                ])
                ->log('published');
        }

        return back()->with('success', $changes === []
            ? __('backoffice.pages.nothing_to_publish')
            : __('backoffice.pages.published'));
    }

    public function discard(string $page): RedirectResponse
    {
        abort_unless(PageContent::exists($page), 404);

        PageContent::discard($page);

        return back()->with('success', __('backoffice.pages.discarded'));
    }

    /** @return array<string, ?string> */
    private function values(Request $request, string $page): array
    {
        $request->validate(PageContent::rules($page));

        // Dibaca dari input mentah, bukan dari `validated()`: kunci field
        // mengandung titik, dan yang dikembalikan validator menguraikannya
        // jadi array bersarang. Setiap kunci yang dipakai sudah divalidasi di
        // atas; yang tidak dikenal skema diabaikan `saveDraft`.
        return collect((array) $request->input('values', []))
            ->map(fn ($value) => is_string($value) || $value === null ? $value : null)
            ->all();
    }

    private static function origin(string $url): string
    {
        $parts = parse_url($url);

        return ($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? '')
            .(isset($parts['port']) ? ':'.$parts['port'] : '');
    }
}
