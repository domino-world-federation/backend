<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cms\NewsCategoryRequest;
use App\Models\NewsCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class NewsCategoryController extends Controller
{
    /**
     * Satu layar untuk seluruh pengelolaan kategori — Figma `433:6116`.
     *
     * Daftar terpisah (`246:516`) dan formulir penuh (`252:1432`) DIBUANG:
     * ketiganya melakukan hal yang sama, dan yang paling sederhana sudah
     * mencakup dua lainnya. Menambah satu kata tidak lagi berarti berpindah
     * halaman dua kali.
     */
    public function index(): Response
    {
        return Inertia::render('News/Categories/Index', [
            'categories' => NewsCategory::query()
                ->withCount('articles')
                ->ordered()
                ->get()
                ->map(fn (NewsCategory $c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'usage' => $c->articles_count,
                    'isActive' => $c->is_active,
                ])
                ->all(),
        ]);
    }

    public function store(NewsCategoryRequest $request): RedirectResponse
    {
        NewsCategory::create([
            ...$request->validated(),
            'slug' => NewsCategory::uniqueSlug($request->string('name')->toString()),
            'position' => NewsCategory::nextPosition(),
        ]);

        return back()->with('success', __('backoffice.news.saved'));
    }

    public function update(NewsCategoryRequest $request, NewsCategory $category): RedirectResponse
    {
        $category->update([
            ...$request->validated(),
            'slug' => NewsCategory::uniqueSlug($request->string('name')->toString(), $category->id),
        ]);

        return back()->with('success', __('backoffice.news.updated'));
    }

    public function destroy(NewsCategory $category): RedirectResponse
    {
        // Kategori yang masih dipakai tidak dihapus diam-diam. Foreign key-nya
        // `restrictOnDelete`, jadi tanpa penjagaan ini yang muncul adalah galat
        // database 500, bukan kalimat yang bisa dibaca editor.
        if ($category->articles()->exists()) {
            throw ValidationException::withMessages([
                'category' => __('backoffice.category.in_use', ['count' => $category->articles()->count()]),
            ]);
        }

        $category->delete();

        return back()->with('success', __('backoffice.news.deleted'));
    }
}
