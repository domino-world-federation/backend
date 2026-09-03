<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Models\HeritageMilestone;
use App\Models\Partner;
use App\Support\Media\StoredFile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Partners & Heritage — dua blok yang mengisi beranda dan `/about`.
 *
 * Keduanya daftar berurut bergambar, jadi keduanya memakai bentuk yang sama:
 * tabel di bawah, formulir kartu di atas saat menambah atau menyunting.
 */
class BlockController extends Controller
{
    // ------------------------------------------------------------ Partners

    public function partners(): Response
    {
        return Inertia::render('Blocks/Partners', [
            'partners' => Partner::query()->with('editor:id,name')->ordered()->get()
                ->map(fn (Partner $p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'logoUrl' => StoredFile::url($p->logo_path),
                    'websiteUrl' => $p->website_url,
                    'isActive' => $p->is_active,
                    'updatedAt' => $p->updated_at?->toIso8601String(),
                    'updatedBy' => $p->editor?->name,
                ])
                ->all(),
        ]);
    }

    public function storePartner(Request $request): RedirectResponse
    {
        Partner::create($this->partnerPayload($request) + ['position' => Partner::nextPosition()]);

        return back()->with('success', __('backoffice.blocks.partner_saved'));
    }

    public function updatePartner(Request $request, Partner $partner): RedirectResponse
    {
        $partner->update($this->partnerPayload($request, $partner));

        return back()->with('success', __('backoffice.blocks.partner_updated'));
    }

    public function destroyPartner(Partner $partner): RedirectResponse
    {
        StoredFile::forget($partner->logo_path);
        $partner->delete();

        return back()->with('success', __('backoffice.blocks.partner_deleted'));
    }

    /** @return array<string, mixed> */
    private function partnerPayload(Request $request, ?Partner $partner = null): array
    {
        $uploads = config('dwf.uploads');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            // Logo WAJIB saat membuat: strip partner tanpa logo adalah slot
            // kosong, dan tidak ada yang bisa digambar sebagai gantinya.
            'logo' => [
                $partner === null ? 'required' : 'nullable', 'image',
                'mimes:'.implode(',', $uploads['image_mimes']),
                'max:'.$uploads['image_max_kb'],
            ],
            'website_url' => ['nullable', 'url', 'max:200'],
            'is_active' => ['required', 'boolean'],
        ], attributes: [
            'name' => __('backoffice.blocks.partner_name'),
            'logo' => __('backoffice.blocks.partner_logo'),
        ]);

        $payload = [
            'name' => $data['name'],
            'website_url' => $data['website_url'] ?? null,
            'is_active' => $data['is_active'],
        ];

        if ($request->hasFile('logo')) {
            $payload['logo_path'] = StoredFile::put($request->file('logo'), 'partners', $partner?->logo_path);
        }

        return $payload;
    }

    // ------------------------------------------------------------ Heritage

    public function heritage(): Response
    {
        return Inertia::render('Blocks/Heritage', [
            'milestones' => HeritageMilestone::query()->with('editor:id,name')->ordered()->get()
                ->map(fn (HeritageMilestone $m) => [
                    'id' => $m->id,
                    'year' => $m->year,
                    'title' => $m->title,
                    'summary' => $m->summary,
                    'imageUrl' => StoredFile::url($m->image_path),
                    'imageAlt' => $m->image_alt,
                    'isActive' => $m->is_active,
                    'updatedAt' => $m->updated_at?->toIso8601String(),
                    'updatedBy' => $m->editor?->name,
                ])
                ->all(),
        ]);
    }

    public function storeMilestone(Request $request): RedirectResponse
    {
        HeritageMilestone::create(
            $this->milestonePayload($request) + ['position' => HeritageMilestone::nextPosition()],
        );

        return back()->with('success', __('backoffice.blocks.milestone_saved'));
    }

    public function updateMilestone(Request $request, HeritageMilestone $milestone): RedirectResponse
    {
        $milestone->update($this->milestonePayload($request, $milestone));

        return back()->with('success', __('backoffice.blocks.milestone_updated'));
    }

    public function destroyMilestone(HeritageMilestone $milestone): RedirectResponse
    {
        StoredFile::forget($milestone->image_path);
        $milestone->delete();

        return back()->with('success', __('backoffice.blocks.milestone_deleted'));
    }

    /** @return array<string, mixed> */
    private function milestonePayload(Request $request, ?HeritageMilestone $milestone = null): array
    {
        $uploads = config('dwf.uploads');

        $data = $request->validate([
            // String, bukan integer: "1990s" sama sahnya dengan "1974".
            'year' => ['required', 'string', 'max:16'],
            'title' => ['required', 'string', 'max:160'],
            'summary' => ['required', 'string', 'max:600'],
            'image' => [
                'nullable', 'image',
                'mimes:'.implode(',', $uploads['image_mimes']),
                'max:'.$uploads['image_max_kb'],
            ],
            'image_alt' => ['nullable', 'string', 'max:200'],
            'is_active' => ['required', 'boolean'],
        ], attributes: [
            'year' => __('backoffice.blocks.milestone_year'),
            'title' => __('backoffice.blocks.milestone_title'),
            'summary' => __('backoffice.blocks.milestone_summary'),
        ]);

        $payload = [
            'year' => $data['year'],
            'title' => $data['title'],
            'summary' => $data['summary'],
            'image_alt' => $data['image_alt'] ?? null,
            'is_active' => $data['is_active'],
        ];

        if ($request->hasFile('image')) {
            $payload['image_path'] = StoredFile::put($request->file('image'), 'heritage', $milestone?->image_path);
        }

        return $payload;
    }
}
