<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cms\FaqRequest;
use App\Models\Faq;
use App\Models\FaqCategory;
use App\Models\FaqPlacement;
use App\Support\Csv;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Mews\Purifier\Facades\Purifier;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FaqController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('q')->toString();
        $status = $request->string('status')->toString();
        $category = $request->string('category')->toString();

        return Inertia::render('Faq/Index', [
            'faqs' => $this->filtered($search, $status, $category)
                ->paginate(config('dwf.per_page'))
                ->withQueryString()
                ->through(fn (Faq $f) => $this->row($f)),
            'categories' => $this->categoryOptions(),
            'filters' => ['q' => $search, 'status' => $status, 'category' => $category],
        ]);
    }

    /**
     * Satu baris daftar — bentuknya sama dengan News (`252:1743`).
     *
     * Dua kolom "siapa + kapan" ikut, sama seperti di sana: "diubah tiga menit
     * lalu" tidak berguna kalau yang ditanyakan "oleh siapa".
     *
     * @return array<string, mixed>
     */
    private function row(Faq $f): array
    {
        return [
            'id' => $f->id,
            'question' => $f->question,
            'category' => $f->category?->name,
            'pages' => $this->pageLabels($f->pages),
            'isActive' => $f->is_active,
            'createdAt' => $f->created_at?->toIso8601String(),
            'updatedAt' => $f->updated_at?->toIso8601String(),
            'updatedBy' => $f->editor?->name,
        ];
    }

    /** Query daftar — dipakai layar daftar DAN ekspor, supaya keduanya sepakat. */
    private function filtered(string $search, string $status, string $category): Builder
    {
        return Faq::query()
            ->with(['category:id,name', 'editor:id,name', 'placements'])
            ->when($search !== '', fn ($q) => $q->where('question', 'ilike', "%{$search}%"))
            ->when($status === 'active', fn ($q) => $q->where('is_active', true))
            ->when($status === 'inactive', fn ($q) => $q->where('is_active', false))
            ->when($category !== '', fn ($q) => $q->where('faq_category_id', $category))
            ->ordered();
    }

    public function export(Request $request): StreamedResponse
    {
        $rows = $this->filtered(
            $request->string('q')->toString(),
            $request->string('status')->toString(),
            $request->string('category')->toString(),
        )->lazy();

        return Csv::stream('faq', [
            'ID', 'Question', 'Category', 'Applied To', 'Active',
            'Created At', 'Last Modified At', 'Last Modified By',
        ], $rows->map(fn (Faq $f) => [
            $f->id,
            $f->question,
            $f->category?->name,
            implode(', ', $this->pageLabels($f->pages)),
            $f->is_active ? 'yes' : 'no',
            $f->created_at?->toDateTimeString(),
            $f->updated_at?->toDateTimeString(),
            $f->editor?->name,
        ]));
    }

    /**
     * Layar baca — dibuka dengan mengklik pertanyaannya di daftar.
     *
     * Terpisah dari `edit()` dengan sengaja, alasan yang sama seperti di News:
     * memeriksa isi tidak seharusnya dimulai dengan membuka sesuatu yang bisa
     * diubah tanpa sadar.
     */
    public function show(Faq $faq): Response
    {
        $faq->load(['category:id,name', 'editor:id,name', 'placements']);

        return Inertia::render('Faq/Show', [
            'faq' => [
                'id' => $faq->id,
                'question' => $faq->question,
                'answer' => $faq->answer,
                'category' => $faq->category?->name,
                'pages' => $this->pageLabels($faq->pages),
                'isActive' => $faq->is_active,
                'createdAt' => $faq->created_at?->toIso8601String(),
                'updatedAt' => $faq->updated_at?->toIso8601String(),
                'updatedBy' => $faq->editor?->name,
            ],
        ]);
    }

    /** Sakelar status langsung dari daftar — sama seperti Highlight di News. */
    public function status(Request $request, Faq $faq): RedirectResponse
    {
        $data = $request->validate(['is_active' => ['required', 'boolean']]);

        $faq->update(['is_active' => $data['is_active']]);

        return back()->with('success', __('backoffice.faq.updated'));
    }

    public function create(): Response
    {
        return Inertia::render('Faq/Form', [
            'faq' => null,
            'categories' => $this->categoryOptions(),
        ]);
    }

    public function store(FaqRequest $request): RedirectResponse
    {
        $faq = Faq::create([
            ...$this->payload($request),
            'position' => Faq::nextPosition(),
        ]);

        return to_route('faq.index')->with('success', __('backoffice.faq.saved'));
    }

    public function edit(Faq $faq): Response
    {
        $faq->load('placements');

        return Inertia::render('Faq/Form', [
            'faq' => [
                'id' => $faq->id,
                'question' => $faq->question,
                'answer' => $faq->answer,
                'categoryId' => $faq->faq_category_id,
                // Label jadi, bukan kunci: layar itu cuma mencetaknya.
                'pages' => $this->pageLabels($faq->pages),
                'isActive' => $faq->is_active,
            ],
            'categories' => $this->categoryOptions(),
        ]);
    }

    public function update(FaqRequest $request, Faq $faq): RedirectResponse
    {
        $faq->update($this->payload($request));

        return to_route('faq.index')->with('success', __('backoffice.faq.updated'));
    }

    public function destroy(Faq $faq): RedirectResponse
    {
        $faq->delete();

        return back()->with('success', __('backoffice.faq.deleted'));
    }

    /**
     * Urutan di halaman FAQ LENGKAP (`/page/faq`).
     *
     * Bukan urutan di Home/Domino/Tournament — yang itu punya layarnya sendiri
     * (`pages()`), karena tiap halaman menyimpan peringkatnya masing-masing.
     * Kategori ikut dicetak di tiap baris supaya menyeret di sini tidak
     * dilakukan buta: halaman lengkap mengelompokkan pertanyaan per kategori,
     * jadi yang dipengaruhi satu geseran adalah urutan DI DALAM kategorinya.
     */
    public function manage(): Response
    {
        return Inertia::render('Faq/Manage', [
            'faqs' => Faq::query()->with('category:id,name')->ordered()->get(['id', 'question', 'faq_category_id'])
                ->map(fn (Faq $f) => [
                    'id' => $f->id,
                    'label' => $f->question,
                    'note' => $f->category?->name,
                ])
                ->all(),
        ]);
    }

    public function reorder(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer', 'exists:faqs,id'],
        ]);

        Faq::applyOrder($validated['ids']);

        return back()->with('success', __('backoffice.order.saved'));
    }

    /**
     * "FAQ per Halaman" — memilih DAN mengurutkan isi tiap halaman publik.
     *
     * Ketiga halaman digambar sekaligus, bukan satu per satu di balik pemilih.
     * Alasannya persis keluhan yang melahirkan layar ini: dulu satu urutan
     * dipakai bersama, dan yang membuatnya tidak ketahuan adalah tidak adanya
     * satu pun tempat yang memperlihatkan ketiganya berdampingan.
     */
    public function pages(): Response
    {
        $placements = FaqPlacement::query()
            ->with('faq.category:id,name')
            ->orderBy('position')
            ->get()
            ->groupBy('page');

        return Inertia::render('Faq/Pages', [
            'pages' => collect(config('dwf.faq_pages'))
                ->map(fn (string $label, string $key) => [
                    'key' => $key,
                    'label' => $label,
                    'max' => Faq::MAX_PER_PAGE,
                    'faqs' => $placements->get($key, collect())
                        ->map(fn (FaqPlacement $p) => [
                            'id' => $p->faq_id,
                            'label' => $p->faq->question,
                            'note' => $p->faq->category?->name,
                            // FAQ nonaktif TETAP ditampilkan, ditandai. Membuangnya
                            // diam-diam dari layar ini berarti orang melihat dua
                            // pertanyaan di Home, menambah yang ketiga, lalu
                            // ditolak karena halamannya "penuh" oleh sesuatu yang
                            // tidak kelihatan.
                            'isActive' => $p->faq->is_active,
                        ])
                        ->values()
                        ->all(),
                ])
                ->values()
                ->all(),

            // Pilihan untuk kotak "tambah", dikelompokkan per kategori — itu
            // cara orang mencari pertanyaan yang mau dipasang di suatu halaman.
            'library' => FaqCategory::query()
                ->with(['faqs' => fn ($q) => $q->where('is_active', true)->ordered()])
                ->where('is_active', true)
                ->ordered()
                ->get()
                ->map(fn (FaqCategory $c) => [
                    'label' => $c->name,
                    'options' => $c->faqs
                        ->map(fn (Faq $f) => ['value' => $f->id, 'label' => $f->question])
                        ->all(),
                ])
                ->filter(fn (array $group) => $group['options'] !== [])
                ->values()
                ->all(),
        ]);
    }

    /**
     * Menyimpan isi dan urutan SATU halaman.
     *
     * Satu halaman per request, bukan ketiganya sekaligus: kalau Domino ditolak
     * karena kelebihan satu pertanyaan, Home dan Tournament yang di layar yang
     * sama tidak ikut batal.
     */
    public function placements(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'page' => ['required', Rule::in(Faq::PAGES)],
            'ids' => ['present', 'array', 'max:'.Faq::MAX_PER_PAGE],
            'ids.*' => ['integer', 'distinct', 'exists:faqs,id'],
        ]);

        $page = $validated['page'];
        $ids = array_values($validated['ids']);

        $before = FaqPlacement::query()->where('page', $page)->orderBy('position')->pluck('faq_id')->all();

        DB::transaction(function () use ($page, $ids) {
            FaqPlacement::query()->where('page', $page)->whereNotIn('faq_id', $ids)->delete();

            foreach ($ids as $index => $id) {
                FaqPlacement::query()->updateOrCreate(
                    ['faq_id' => $id, 'page' => $page],
                    ['position' => $index + 1],
                );
            }
        });

        // Dicatat sebagai SATU entri, bukan satu per penempatan — alasan yang
        // sama dengan `SiteSetting`: satu kali Simpan bukan sembilan tindakan.
        // `log_name` 'faq' sengaja sama dengan yang dipakai `RecordsActivity`
        // di model, supaya penyaring modul tidak memperlihatkan dua FAQ.
        if ($before !== array_map('intval', $ids)) {
            activity('faq')
                ->causedBy($request->user())
                ->event('reordered')
                ->withProperties(['page' => $page, 'attributes' => $ids, 'old' => $before])
                ->log('reordered');
        }

        return back()->with('success', __('backoffice.order.saved'));
    }

    /** @return array<string, mixed> */
    private function payload(FaqRequest $request): array
    {
        return [
            'faq_category_id' => $request->integer('faq_category_id'),
            'question' => $request->string('question')->toString(),
            'answer' => Purifier::clean($request->string('answer')->toString()),
            'is_active' => $request->boolean('is_active'),
        ];
    }

    /** @return array<int, array{value: int, label: string}> */
    private function categoryOptions(): array
    {
        return FaqCategory::query()->where('is_active', true)->ordered()->get(['id', 'name'])
            ->map(fn (FaqCategory $c) => ['value' => $c->id, 'label' => $c->name])
            ->all();
    }

    /** @param array<int, string> $keys @return array<int, string> */
    private function pageLabels(array $keys): array
    {
        $labels = config('dwf.faq_pages');

        return array_values(array_map(fn (string $key) => $labels[$key] ?? $key, $keys));
    }
}
