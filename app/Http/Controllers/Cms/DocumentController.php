<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Support\Csv;
use App\Support\DocumentCategories;
use App\Support\Media\StoredFile;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
            'file_path' => StoredFile::put($request->file('file'), 'documents', disk: 'local'),
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
            $payload['file_path'] = StoredFile::put($request->file('file'), 'documents', $document->file_path, 'local');
            $payload['file_size'] = $request->file('file')->getSize();
        }

        $document->update($payload);

        return to_route('documents.index')->with('success', __('backoffice.documents.updated'));
    }

    public function destroy(Document $document): RedirectResponse
    {
        StoredFile::forget($document->file_path, 'local');
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
