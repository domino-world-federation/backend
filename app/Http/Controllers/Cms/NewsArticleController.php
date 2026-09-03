<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cms\NewsArticleRequest;
use App\Models\NewsArticle;
use App\Models\NewsCategory;
use App\Support\Csv;
use App\Support\Media\StoredFile;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Mews\Purifier\Facades\Purifier;
use Symfony\Component\HttpFoundation\StreamedResponse;

class NewsArticleController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('q')->toString();
        $status = $request->string('status')->toString();
        $category = $request->string('category')->toString();

        $articles = $this->filtered($search, $status, $category)
            ->paginate(config('dwf.per_page'))
            ->withQueryString()
            ->through(fn (NewsArticle $a) => $this->row($a));

        return Inertia::render('News/Articles/Index', [
            'articles' => $articles,
            'categories' => $this->categoryOptions(),
            'filters' => ['q' => $search, 'status' => $status, 'category' => $category],
        ]);
    }

    /**
     * Satu baris daftar — bentuknya mengikuti layar Figma `252:1743`.
     *
     * Tiga kolom waktunya membawa NAMA, bukan cuma tanggal: "diubah 3 menit lalu"
     * tidak berguna kalau yang ditanyakan "oleh siapa", dan itulah pertanyaan
     * yang membawa orang ke daftar ini setelah sesuatu berubah tanpa ada yang
     * mengaku.
     */
    private function row(NewsArticle $a): array
    {
        $visibility = $a->visibility;

        return [
            'id' => $a->id,
            'title' => $a->title,
            'category' => $a->category?->name,
            'status' => $a->status,
            'visibility' => $visibility,
            // Jadwalnya ikut, dan hanya untuk yang memang terjadwal: di desain,
            // waktu itu dicetak di bawah kata "Scheduled", bukan di kolom
            // Published — kolom itu untuk yang SUDAH tayang.
            'scheduledFor' => $visibility === 'scheduled' ? $a->published_at?->toIso8601String() : null,
            // Bisa dikembalikan ke Scheduled hanya kalau jadwalnya masih di
            // depan. Tanpa keterangan ini, dropdown-nya menawarkan pilihan yang
            // pasti ditolak server.
            'canSchedule' => $a->published_at?->isFuture() ?? false,
            'isHighlighted' => $a->is_highlighted,
            'publishedAt' => $visibility === 'posted' ? $a->published_at?->toIso8601String() : null,
            'publishedBy' => $visibility === 'posted' ? $a->author?->name : null,
            'createdAt' => $a->created_at?->toIso8601String(),
            'createdBy' => $a->author?->name,
            'updatedAt' => $a->updated_at?->toIso8601String(),
            'updatedBy' => $a->editor?->name ?? $a->author?->name,
        ];
    }

    /** Query daftar — dipakai layar daftar DAN ekspor, supaya keduanya sepakat. */
    private function filtered(string $search, string $status, string $category): Builder
    {
        return NewsArticle::query()
            ->with(['category:id,name', 'author:id,name', 'editor:id,name'])
            ->when($search !== '', fn ($q) => $q->where('title', 'ilike', "%{$search}%"))
            ->when(in_array($status, NewsArticle::STATUSES, true), fn ($q) => $q->where('status', $status))
            ->when($category !== '', fn ($q) => $q->where('news_category_id', $category))
            ->latest('id');
    }

    /**
     * Ekspor CSV, mengikuti filter yang sedang aktif.
     *
     * Yang diekspor adalah HASIL SARINGAN, bukan seluruh tabel. Tombolnya duduk
     * di sebelah filter; mengekspor sesuatu yang berbeda dari yang sedang
     * dilihat orang adalah cara paling cepat membuat angka di rapat tidak cocok
     * dengan angka di layar.
     */
    /**
     * Ekspor CSV, mengikuti filter yang sedang aktif.
     *
     * Yang diekspor adalah HASIL SARINGAN, bukan seluruh tabel. Tombolnya duduk
     * di sebelah filter; mengekspor sesuatu yang berbeda dari yang sedang
     * dilihat orang adalah cara paling cepat membuat angka di rapat tidak cocok
     * dengan angka di layar.
     */
    public function export(Request $request): StreamedResponse
    {
        // `lazy()`, bukan `cursor()`: yang ini tetap eager-load relasinya per
        // potongan. `cursor()` memuat ulang kategori dan penulis satu per satu —
        // seribu baris jadi tiga ribu query.
        $rows = $this->filtered(
            $request->string('q')->toString(),
            $request->string('status')->toString(),
            $request->string('category')->toString(),
        )->lazy();

        return Csv::stream('news', [
            'ID', 'Title', 'Slug', 'Category', 'Visibility',
            'Published At', 'Author', 'Created At', 'Last Modified At', 'Last Modified By',
            'Highlighted',
        ], $rows->map(fn (NewsArticle $a) => [
            $a->id,
            $a->title,
            $a->slug,
            $a->category?->name,
            $a->visibility,
            $a->published_at?->toDateTimeString(),
            $a->author?->name,
            $a->created_at?->toDateTimeString(),
            $a->updated_at?->toDateTimeString(),
            $a->editor?->name ?? $a->author?->name,
            $a->is_highlighted ? 'yes' : 'no',
        ]));
    }

    /**
     * Ubah status langsung dari kolom Visibility.
     *
     * `scheduled` hanya diterima kalau `published_at` masih di depan — dropdown
     * tidak punya tempat untuk memasukkan tanggal, dan menyetel "terjadwal"
     * tanpa jadwal menghasilkan artikel yang tidak pernah tayang dan tidak
     * pernah kelihatan salah.
     */
    public function visibility(Request $request, NewsArticle $article): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(NewsArticle::QUICK_STATUSES)],
        ]);

        if ($data['status'] === NewsArticle::STATUS_SCHEDULED && ! $article->published_at?->isFuture()) {
            throw ValidationException::withMessages([
                'status' => __('backoffice.news.needs_schedule'),
            ]);
        }

        // Menayangkan sesuatu yang belum pernah punya tanggal: tanggalnya
        // sekarang. Tanpa ini kolom Published tetap kosong di baris yang jelas-
        // jelas sudah tayang.
        if ($data['status'] === NewsArticle::STATUS_PUBLISHED && $article->published_at === null) {
            $article->published_at = now();
        }

        $article->status = $data['status'];
        $article->save();

        return back()->with('success', __('backoffice.news.updated'));
    }

    /** Toggle Highlight langsung dari daftar. */
    public function highlight(Request $request, NewsArticle $article): RedirectResponse
    {
        $data = $request->validate(['is_highlighted' => ['required', 'boolean']]);

        $article->is_highlighted = $data['is_highlighted'];
        $article->save();

        return back()->with('success', __('backoffice.news.updated'));
    }

    /**
     * Layar baca — dibuka dengan mengklik judul di daftar.
     *
     * Terpisah dari `edit()` dengan sengaja: yang paling sering dilakukan orang
     * pada satu artikel adalah MEMBACANYA untuk memastikan isinya benar, dan
     * membuka formulir penuh untuk itu berarti setiap pemeriksaan dimulai dengan
     * risiko mengubah sesuatu tanpa sadar.
     */
    public function show(NewsArticle $article): Response
    {
        $article->load(['category:id,name', 'author:id,name']);

        return Inertia::render('News/Articles/Show', [
            'article' => [
                'id' => $article->id,
                'title' => $article->title,
                'slug' => $article->slug,
                'excerpt' => $article->excerpt,
                'body' => $article->body,
                'category' => $article->category?->name,
                'visibility' => $article->visibility,
                'isHighlighted' => $article->is_highlighted,
                'authorName' => $article->author?->name,
                'publishedAt' => $article->published_at?->toIso8601String(),
                'createdAt' => $article->created_at?->toIso8601String(),
                'updatedAt' => $article->updated_at?->toIso8601String(),
                'heroUrl' => StoredFile::url($article->hero_image_path),
                'landscapeUrl' => StoredFile::url($article->landscape_image_path),
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('News/Articles/Form', [
            'article' => null,
            'categories' => $this->categoryOptions(),
        ]);
    }

    public function store(NewsArticleRequest $request): RedirectResponse
    {
        $article = new NewsArticle;
        $this->fill($article, $request);
        $article->author_id = $request->user()->id;
        $article->slug = NewsArticle::uniqueSlug($request->slugSource());
        $article->save();

        return to_route('news.articles.index')->with('success', __('backoffice.news.saved'));
    }

    public function edit(NewsArticle $article): Response
    {
        return Inertia::render('News/Articles/Form', [
            'article' => [
                'id' => $article->id,
                'title' => $article->title,
                'slug' => $article->slug,
                'excerpt' => $article->excerpt,
                'body' => $article->body,
                'categoryId' => $article->news_category_id,
                'isHighlighted' => $article->is_highlighted,
                'status' => $article->status,
                'publishedAt' => $article->published_at?->format('Y-m-d\TH:i'),
                'authorName' => $article->author?->name,
                'createdAt' => $article->created_at?->toIso8601String(),
                'heroUrl' => StoredFile::url($article->hero_image_path),
                'landscapeUrl' => StoredFile::url($article->landscape_image_path),
            ],
            'categories' => $this->categoryOptions(),
        ]);
    }

    public function update(NewsArticleRequest $request, NewsArticle $article): RedirectResponse
    {
        $this->fill($article, $request);
        $article->slug = NewsArticle::uniqueSlug($request->slugSource(), $article->id);
        $article->save();

        return to_route('news.articles.index')->with('success', __('backoffice.news.updated'));
    }

    public function destroy(NewsArticle $article): RedirectResponse
    {
        foreach (['hero_image_path', 'landscape_image_path'] as $column) {
            StoredFile::forget($article->{$column});
        }

        $article->delete();

        return back()->with('success', __('backoffice.news.deleted'));
    }

    private function fill(NewsArticle $article, NewsArticleRequest $request): void
    {

        $article->fill([
            'news_category_id' => $request->integer('news_category_id'),
            'title' => $request->string('title')->toString(),
            'excerpt' => $request->string('excerpt')->toString() ?: null,
            // Dibersihkan di server, apa pun yang dikirim editor. Editor adalah
            // kenyamanan mengetik, bukan batas keamanan — HTML ini nanti tampil
            // di situs publik.
            'body' => Purifier::clean($request->string('body')->toString()),
            'is_highlighted' => $request->boolean('is_highlighted'),
            'status' => $request->resolvedStatus(),
            'published_at' => $request->resolvedPublishedAt(),
        ]);

        foreach ([
            'hero' => 'hero_image_path',
            'landscape' => 'landscape_image_path',
        ] as $field => $column) {
            if ($request->hasFile($field)) {
                $article->{$column} = StoredFile::put($request->file($field), 'news', $article->{$column});
            }
        }
    }

    /** @return array<int, array{value: int, label: string}> */
    private function categoryOptions(): array
    {
        return NewsCategory::query()
            ->where('is_active', true)
            ->ordered()
            ->get(['id', 'name'])
            ->map(fn (NewsCategory $c) => ['value' => $c->id, 'label' => $c->name])
            ->all();
    }
}
