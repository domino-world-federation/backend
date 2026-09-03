<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Models\FaqCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class FaqCategoryController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('q')->toString();
        $status = $request->string('status')->toString();

        return Inertia::render('Faq/Categories/Index', [
            'categories' => FaqCategory::query()
                ->withCount('faqs')
                ->when($search !== '', fn ($q) => $q->where('name', 'ilike', "%{$search}%"))
                ->when($status === 'active', fn ($q) => $q->where('is_active', true))
                ->when($status === 'inactive', fn ($q) => $q->where('is_active', false))
                ->ordered()
                ->paginate(config('dwf.per_page'))
                ->withQueryString()
                ->through(fn (FaqCategory $c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'usage' => $c->faqs_count,
                    'isActive' => $c->is_active,
                ]),
            'filters' => ['q' => $search, 'status' => $status],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Faq/Categories/Form', ['category' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        FaqCategory::create([
            ...$data,
            'slug' => FaqCategory::uniqueSlug($data['name']),
            'position' => FaqCategory::nextPosition(),
        ]);

        return to_route('faq.categories.index')->with('success', __('backoffice.faq.saved'));
    }

    public function edit(FaqCategory $category): Response
    {
        return Inertia::render('Faq/Categories/Form', [
            'category' => ['id' => $category->id, 'name' => $category->name, 'isActive' => $category->is_active],
        ]);
    }

    public function update(Request $request, FaqCategory $category): RedirectResponse
    {
        $data = $this->validated($request, $category->id);

        $category->update([...$data, 'slug' => FaqCategory::uniqueSlug($data['name'], $category->id)]);

        return to_route('faq.categories.index')->with('success', __('backoffice.faq.updated'));
    }

    public function destroy(FaqCategory $category): RedirectResponse
    {
        if ($category->faqs()->exists()) {
            throw ValidationException::withMessages([
                'category' => __('backoffice.category.in_use', ['count' => $category->faqs()->count()]),
            ]);
        }

        $category->delete();

        return back()->with('success', __('backoffice.faq.deleted'));
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?int $ignore = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('faq_categories', 'name')->ignore($ignore)],
            'is_active' => ['required', 'boolean'],
        ], attributes: ['name' => 'nama kategori', 'is_active' => 'status']);
    }
}
