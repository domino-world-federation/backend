<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\DocumentPlacement;
use App\Support\Csv;
use App\Support\DocumentCategories;
use App\Support\DocumentSections;
use App\Support\Media\StoredFile;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $this->filters($request);

        return Inertia::render('Documents/Index', [
            'documents' => $this->filtered($filters)
                ->paginate(config('dwf.per_page'))
                ->withQueryString()
                ->through(fn (Document $d) => $this->row($d)),
            'categories' => DocumentCategories::options(),
            'filters' => $filters,
        ]);
    }

    /**
     * Satu baris daftar — bentuknya sama dengan News dan FAQ.
     *
     * @return array<string, mixed>
     */
    private function row(Document $d): array
    {
        return [
            'id' => $d->id,
            'title' => $d->title,
            'category' => $d->category,
            'fileSize' => $d->file_size_label,
            'fileUrl' => route('media.document', $d),
            'visibility' => $d->visibility,
            'scheduledFor' => $d->visibility === 'scheduled' ? $d->published_at?->toIso8601String() : null,
            'canSchedule' => $d->published_at?->isFuture() ?? false,

            // Tiga kolom berbentuk sama di `369:5236`: nama di atas, waktu di
            // bawahnya.
            'publishedAt' => $d->published_at?->toIso8601String(),
            'publishedBy' => $d->publisher?->name,
            'createdAt' => $d->created_at?->toIso8601String(),
            'createdBy' => $d->creator?->name,
            'updatedAt' => $d->updated_at?->toIso8601String(),
            'updatedBy' => $d->editor?->name,
        ];
    }

    /** @return array{q: string, status: string, category: string} */
    private function filters(Request $request): array
    {
        return [
            'q' => $request->string('q')->toString(),
            'status' => $request->string('status')->toString(),
            'category' => $request->string('category')->toString(),
        ];
    }

    /**
     * Query daftar — dipakai layar daftar DAN ekspor, supaya keduanya sepakat.
     *
     * @param  array{q: string, status: string, category: string}  $filters
     */
    private function filtered(array $filters): Builder
    {
        return Document::query()
            ->with(['editor:id,name', 'creator:id,name', 'publisher:id,name'])
            ->when($filters['q'] !== '', fn ($q) => $q->where('title', 'ilike', "%{$filters['q']}%"))
            ->when(
                in_array($filters['status'], Document::STATUSES, true),
                fn ($q) => $q->where('status', $filters['status']),
            )
            ->when($filters['category'] !== '', fn ($q) => $q->where('category', $filters['category']))
            ->latest('id');
    }

    public function export(Request $request): StreamedResponse
    {
        $rows = $this->filtered($this->filters($request))->lazy();

        return Csv::stream('documents', [
            'ID', 'Title', 'Slug', 'Category', 'File Size', 'Visibility',
            'Published At', 'Published By', 'Created At', 'Created By',
            'Last Modified At', 'Last Modified By',
        ], $rows->map(fn (Document $d) => [
            $d->id,
            $d->title,
            $d->slug,
            $d->category,
            $d->file_size_label,
            $d->visibility,
            $d->published_at?->toDateTimeString(),
            $d->publisher?->name,
            $d->created_at?->toDateTimeString(),
            $d->creator?->name,
            $d->updated_at?->toDateTimeString(),
            $d->editor?->name,
        ]));
    }

    /** Layar baca — dibuka dengan mengklik judulnya di daftar. */
    public function show(Document $document): Response
    {
        $document->load(['editor:id,name', 'creator:id,name', 'publisher:id,name']);

        return Inertia::render('Documents/Show', [
            'document' => [
                'id' => $document->id,
                'title' => $document->title,
                'slug' => $document->slug,
                'category' => $document->category,
                'visibility' => $document->visibility,
                'fileName' => $document->downloadName(),
                'fileSize' => $document->file_size_label,
                'fileUrl' => route('media.document', $document),
                'publishedAt' => $document->published_at?->toIso8601String(),
                'publishedBy' => $document->publisher?->name,
                'createdAt' => $document->created_at?->toIso8601String(),
                'createdBy' => $document->creator?->name,
                'updatedAt' => $document->updated_at?->toIso8601String(),
                'updatedBy' => $document->editor?->name,
            ],
        ]);
    }

    /**
     * Ubah visibilitas langsung dari barisnya.
     *
     * Desain menaruh pemilihnya DI DALAM sel (`478:5342` — ikon globe, teks,
     * chevron), sama persis dengan News dan Gallery. Aturannya juga sama,
     * termasuk penolakan `scheduled` tanpa jadwal.
     */
    public function visibility(Request $request, Document $document): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(Document::QUICK_STATUSES)],
        ]);

        if ($data['status'] === Document::STATUS_SCHEDULED && ! $document->published_at?->isFuture()) {
            throw ValidationException::withMessages([
                'status' => __('backoffice.news.needs_schedule'),
            ]);
        }

        if ($data['status'] === Document::STATUS_PUBLISHED && $document->published_at === null) {
            $document->published_at = now();
        }

        $document->status = $data['status'];
        $document->save();

        return back()->with('success', __('backoffice.documents.updated'));
    }

    /**
     * "Documents per Halaman" — memilih DAN mengurutkan isi tiap rak publik.
     *
     * Seluruh rak berdiri berdampingan di satu layar, sama seperti `/faq/pages`,
     * dan alasannya sama: dua rak yang menarik kategori yang sama menampilkan
     * isi yang sama persis, dan yang membuat itu tidak pernah ketahuan adalah
     * tidak adanya satu pun tempat yang memperlihatkan keduanya sekaligus.
     * Statutes & Constitution dan Governance Repository dua-duanya
     * `Governance Documents`.
     */
    public function sections(): Response
    {
        $placements = DocumentPlacement::query()
            ->with('document:id,title,category,status,published_at')
            ->orderBy('position')
            ->get()
            ->groupBy('section');

        return Inertia::render('Documents/Sections', [
            'sections' => collect(DocumentSections::all())
                ->map(fn (array $section) => [
                    ...$section,
                    // Rak yang belum punya satu pun baris menarik "N terbaru
                    // dari kategorinya" sendiri. Layar HARUS mengatakannya:
                    // tanpa itu, rak yang di situs publik penuh tampil kosong
                    // di sini, dan yang membacanya menyimpulkan fiturnya rusak.
                    'isAuto' => ! $placements->has($section['key']),
                    'documents' => $placements->get($section['key'], collect())
                        ->map(fn (DocumentPlacement $p) => [
                            'id' => $p->document_id,
                            'label' => $p->document->title,
                            'note' => $p->document->category,
                            // Dokumen yang belum tayang TETAP digambar,
                            // ditandai — alasan yang sama dengan FAQ nonaktif di
                            // `/faq/pages`: membuangnya diam-diam berarti orang
                            // melihat empat baris, menambah yang kelima, lalu
                            // ditolak karena raknya penuh oleh sesuatu yang
                            // tidak ada di layar.
                            'isLive' => $this->isLive($p->document),
                        ])
                        ->values()
                        ->all(),
                    'library' => DocumentSections::library($section['key'])
                        ->map(fn (Document $d) => [
                            'value' => $d->id,
                            'label' => $d->title,
                            'isLive' => $this->isLive($d),
                        ])
                        ->values()
                        ->all(),
                ])
                ->values()
                ->all(),
        ]);
    }

    /**
     * Menyimpan isi dan urutan SATU rak.
     *
     * Satu rak per request, bukan sembilan sekaligus: kalau Governance ditolak
     * karena kelebihan satu dokumen, delapan rak lain di layar yang sama tidak
     * ikut batal.
     */
    public function placements(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'section' => ['required', Rule::in(DocumentSections::keys())],
            'ids' => ['present', 'array'],
            'ids.*' => ['integer', 'distinct', 'exists:documents,id'],
        ]);

        $section = DocumentSections::find($validated['section']);
        $ids = array_values($validated['ids']);

        /*
         * Batas dan kategori diperiksa DI SINI, bukan cuma di layar.
         *
         * Aturan `max:` statis tidak bisa dipakai — batasnya berbeda per rak
         * (1 untuk Official Rulebook, 6 untuk grid) dan baru diketahui setelah
         * `section` terbaca. Begitu juga kategorinya: tanpa pemeriksaan ini,
         * permintaan yang dirakit tangan bisa menaruh rulebook di rak
         * publikasi, dan tidak ada satu pun layar yang akan memberi tahu.
         */
        if (count($ids) > $section['max']) {
            throw ValidationException::withMessages([
                'ids' => __('backoffice.documents.sections_full', ['max' => $section['max']]),
            ]);
        }

        if ($section['category'] !== null && $ids !== []) {
            $foreign = Document::query()
                ->whereIn('id', $ids)
                ->where('category', '!=', $section['category'])
                ->exists();

            if ($foreign) {
                throw ValidationException::withMessages([
                    'ids' => __('backoffice.documents.sections_wrong_category', [
                        'category' => $section['category'],
                    ]),
                ]);
            }
        }

        $key = $section['key'];
        $before = DocumentPlacement::query()->where('section', $key)->orderBy('position')->pluck('document_id')->all();

        DB::transaction(function () use ($key, $ids) {
            DocumentPlacement::query()->where('section', $key)->whereNotIn('document_id', $ids)->delete();

            foreach ($ids as $index => $id) {
                DocumentPlacement::query()->updateOrCreate(
                    ['document_id' => $id, 'section' => $key],
                    ['position' => $index + 1],
                );
            }
        });

        // Satu entri, bukan satu per penempatan — alasan yang sama dengan
        // `FaqController::placements()`: satu kali Simpan bukan enam tindakan.
        // `log_name` 'document' sengaja sama dengan yang dipakai
        // `RecordsActivity` di model, supaya penyaring modul tidak
        // memperlihatkan dua Documents.
        if ($before !== array_map('intval', $ids)) {
            activity('document')
                ->causedBy($request->user())
                ->event('reordered')
                ->withProperties(['section' => $key, 'attributes' => $ids, 'old' => $before])
                ->log('reordered');
        }

        return back()->with('success', __('backoffice.order.saved'));
    }

    /**
     * Apakah dokumen ini benar-benar tampil di situs publik hari ini.
     *
     * Diturunkan dengan aturan yang SAMA PERSIS dengan `Document::scopeLive()`
     * — kalau keduanya berbeda, layar ini akan menandai dokumen sebagai tayang
     * sementara raknya tidak memuatnya.
     */
    private function isLive(Document $document): bool
    {
        if ($document->status === Document::STATUS_PUBLISHED) {
            return true;
        }

        return $document->status === Document::STATUS_SCHEDULED
            && $document->published_at !== null
            && ! $document->published_at->isFuture();
    }

    public function create(): Response
    {
        return Inertia::render('Documents/Form', [
            'document' => null,
            'categories' => DocumentCategories::options(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request, required: true);

        Document::create([
            'title' => $data['title'],
            'slug' => Document::uniqueSlug($data['title']),
            'category' => $data['category'] ?? null,
            'status' => $this->resolvedStatus($data),
            'published_at' => $this->resolvedPublishedAt($data),
            'file_path' => StoredFile::put($request->file('file'), 'documents'),
            'file_size' => $request->file('file')->getSize(),
        ]);

        return to_route('documents.index')->with('success', __('backoffice.documents.saved'));
    }

    public function edit(Document $document): Response
    {
        return Inertia::render('Documents/Form', [
            'document' => [
                'id' => $document->id,
                'title' => $document->title,
                'category' => $document->category,
                'status' => $document->status,
                'publishedAt' => $document->published_at?->format('Y-m-d\TH:i'),
                'fileName' => $document->downloadName(),
                'fileSize' => $document->file_size_label,
            ],
            'categories' => DocumentCategories::options(),
        ]);
    }

    public function update(Request $request, Document $document): RedirectResponse
    {
        $data = $this->validated($request, required: false);

        $payload = [
            'title' => $data['title'],
            'slug' => Document::uniqueSlug($data['title'], $document->id),
            'category' => $data['category'] ?? null,
            'status' => $this->resolvedStatus($data),
            'published_at' => $this->resolvedPublishedAt($data),
        ];

        // Berkas hanya diganti kalau memang ada yang diunggah. Tanpa penjagaan
        // ini, menyunting judul saja akan mengosongkan `file_path`.
        if ($request->hasFile('file')) {
            $payload['file_path'] = StoredFile::put($request->file('file'), 'documents', $document->file_path);
            $payload['file_size'] = $request->file('file')->getSize();
        }

        $document->update($payload);

        return to_route('documents.index')->with('success', __('backoffice.documents.updated'));
    }

    public function destroy(Document $document): RedirectResponse
    {
        StoredFile::forget($document->file_path);
        $document->delete();

        return back()->with('success', __('backoffice.documents.deleted'));
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, bool $required): array
    {
        $uploads = config('dwf.uploads');

        return $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'category' => ['nullable', Rule::in(DocumentCategories::names())],

            // "Publish Time: Now / Schedule" (`262:3449`). Tidak ada tombol
            // Save Draft di layar ini — berbeda dari Gallery — jadi draft hanya
            // dicapai lewat pemilih Visibility di daftar.
            'posting' => ['required', Rule::in(['now', 'schedule'])],
            'published_at' => ['required_if:posting,schedule', 'nullable', 'date'],
            'file' => [
                $required ? 'required' : 'nullable',
                'file',
                'mimes:'.implode(',', $uploads['document_mimes']),
                'max:'.$uploads['document_max_kb'],
            ],
        ], attributes: [
            'title' => 'judul dokumen',
            'file' => 'berkas dokumen',
            'category' => 'kategori',
        ]);
    }

    /**
     * Dua pilihan Publish Time, satu kolom status.
     *
     * Pemetaannya dikunci di sini, bukan ditebak di dua tempat — persis seperti
     * `GalleryController` dan `NewsArticleRequest`.
     *
     * @param  array<string, mixed>  $data
     */
    private function resolvedStatus(array $data): string
    {
        return $data['posting'] === 'schedule'
            ? Document::STATUS_SCHEDULED
            : Document::STATUS_PUBLISHED;
    }

    /** @param array<string, mixed> $data */
    private function resolvedPublishedAt(array $data): CarbonImmutable
    {
        return $data['posting'] === 'schedule'
            ? CarbonImmutable::parse($data['published_at'])
            : CarbonImmutable::now();
    }
}
