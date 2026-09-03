<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Models\PageMeta;
use App\Support\Media\StoredFile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * SEO & Social — menu yang sejak awal ada di sidebar tapi belum punya layarnya.
 *
 * Memakai izin `settings.*` yang sudah ada, bukan modul baru: ia bagian dari
 * grup Site Settings, dan siapa yang boleh mengubah kontak federasi adalah
 * orang yang sama dengan yang boleh mengubah judul halamannya.
 */
class SeoController extends Controller
{
    public function index(): Response
    {
        $rows = PageMeta::query()->with('editor:id,name')->ordered()->get();

        return Inertia::render('Settings/Seo', [
            // Baris bawaan dipisah dari daftar: ia bukan halaman, ia cadangan
            // untuk seluruh halaman, dan menaruhnya di tengah tabel membuatnya
            // terbaca seperti rute bernama "*".
            'fallback' => $this->present($rows->firstWhere('route', PageMeta::DEFAULT_ROUTE)),
            'pages' => $rows
                ->reject(fn (PageMeta $m) => $m->isDefault())
                ->map(fn (PageMeta $m) => $this->present($m))
                ->values()
                ->all(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Settings/SeoForm', ['page' => null]);
    }

    /**
     * Layar sunting berdiri SENDIRI, bukan formulir yang terbuka di atas
     * daftarnya.
     *
     * Baris bawaan (`*`) memakai layar yang sama; yang membedakannya cuma field
     * Route yang tidak digambar untuknya — mengubah `*` jadi path biasa akan
     * menghilangkan cadangan seluruh situs sekaligus.
     */
    public function edit(PageMeta $page): Response
    {
        return Inertia::render('Settings/SeoForm', ['page' => $this->present($page)]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        PageMeta::create($this->payload($request, $data, null) + ['position' => PageMeta::nextPosition()]);

        return to_route('seo-social')->with('success', __('backoffice.seo.saved'));
    }

    public function update(Request $request, PageMeta $page): RedirectResponse
    {
        $data = $this->validated($request, $page);

        $payload = $this->payload($request, $data, $page);

        // Rute baris bawaan tidak bisa diubah: mengubahnya jadi path biasa akan
        // menghilangkan cadangan seluruh situs sekaligus, dan tidak ada layar
        // yang memberi tahu.
        if ($page->isDefault()) {
            unset($payload['route']);
        }

        $page->update($payload);

        return to_route('seo-social')->with('success', __('backoffice.seo.updated'));
    }

    public function destroy(PageMeta $page): RedirectResponse
    {
        if ($page->isDefault()) {
            throw ValidationException::withMessages([
                'page' => __('backoffice.seo.cannot_delete_fallback'),
            ]);
        }

        StoredFile::forget($page->og_image_path);
        $page->delete();

        return back()->with('success', __('backoffice.seo.deleted'));
    }

    /** @return array<string, mixed> */
    private function present(?PageMeta $meta): ?array
    {
        return $meta === null ? null : [
            'id' => $meta->id,
            'route' => $meta->route,
            'label' => $meta->label,
            'title' => $meta->title,
            'description' => $meta->description,
            'ogImageUrl' => StoredFile::url($meta->og_image_path),
            'isDefault' => $meta->isDefault(),
            'updatedAt' => $meta->updated_at?->toIso8601String(),
            'updatedBy' => $meta->editor?->name,
        ];
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?PageMeta $page = null): array
    {
        $uploads = config('dwf.uploads');

        return $request->validate([
            /*
             * Path Nuxt, bukan URL. Regex-nya menolak spasi dan protokol: rute
             * yang salah ketik di sini tidak menghasilkan galat apa pun — ia
             * cuma baris yang tidak pernah cocok dengan halaman mana pun, dan
             * halamannya diam-diam memakai bawaan selamanya.
             */
            'route' => [
                'required', 'string', 'max:160', 'regex:#^/[a-z0-9\-/\[\]]*$#',
                Rule::unique('page_meta', 'route')->ignore($page?->id),
            ],
            'label' => ['required', 'string', 'max:120'],

            // Panjangnya dibatasi karena mesin pencari memotongnya: judul di
            // atas ~60 karakter dan deskripsi di atas ~160 dipangkas dengan
            // elipsis, dan yang hilang selalu bagian akhir kalimatnya.
            'title' => ['nullable', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:320'],

            'og_image' => [
                'nullable', 'image',
                'mimes:'.implode(',', $uploads['image_mimes']),
                'max:'.$uploads['image_max_kb'],
            ],
        ], attributes: [
            'route' => __('backoffice.seo.route'),
            'label' => __('backoffice.seo.label'),
            'title' => __('backoffice.seo.meta_title'),
            'description' => __('backoffice.seo.meta_description'),
            'og_image' => __('backoffice.seo.og_image'),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function payload(Request $request, array $data, ?PageMeta $page): array
    {
        $payload = [
            'route' => $data['route'],
            'label' => $data['label'],
            'title' => $data['title'] ?? null,
            'description' => $data['description'] ?? null,
        ];

        if ($request->hasFile('og_image')) {
            // Path lama ikut supaya berkas yang diganti benar-benar dibuang,
            // bukan menumpuk di disk tanpa ada yang menunjuknya.
            $payload['og_image_path'] = StoredFile::put($request->file('og_image'), 'seo', $page?->og_image_path);
        }

        return $payload;
    }
}
