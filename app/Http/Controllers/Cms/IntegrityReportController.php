<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Models\IntegrityReport;
use App\Support\Csv;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Laporan integritas yang masuk dari `/integrity`.
 *
 * Bentuknya meminjam Contact Messages, tapi dua hal berbeda dan keduanya
 * berasal dari sifat salurannya:
 *
 *   1. TIDAK ADA tombol balas. Laporannya anonim — tidak ada nama, email,
 *      maupun alamat IP yang disimpan — jadi tombol yang membuka `mailto:`
 *      akan menjanjikan sesuatu yang tidak ada di baris mana pun.
 *   2. Isinya dicetak dengan `{{ }}`, tidak pernah sebagai HTML. Ia diketik
 *      orang asing di internet.
 */
class IntegrityReportController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('q')->toString();
        $type = $request->string('type')->toString();
        $status = $request->string('status')->toString();

        return Inertia::render('Integrity/Index', [
            'reports' => $this->filtered($search, $type, $status)
                ->paginate(config('dwf.per_page'))
                ->withQueryString()
                ->through(fn (IntegrityReport $r) => [
                    'id' => $r->id,
                    'type' => $r->type,
                    // Cuplikan, bukan isi penuh: satu laporan bisa ribuan
                    // karakter, dan daftar yang barisnya setinggi paragraf
                    // berhenti bisa dipindai.
                    'excerpt' => str($r->description)->limit(120)->toString(),
                    'isRead' => $r->read_at !== null,
                    'receivedAt' => $r->created_at?->toIso8601String(),
                ]),
            'types' => IntegrityReport::TYPES,
            'unreadCount' => IntegrityReport::query()->unread()->count(),
            'filters' => ['q' => $search, 'type' => $type, 'status' => $status],
        ]);
    }

    /** Query daftar — dipakai layar daftar DAN ekspor, supaya keduanya sepakat. */
    private function filtered(string $search, string $type, string $status): Builder
    {
        return IntegrityReport::query()
            ->when($search !== '', fn ($q) => $q->where('description', 'ilike', "%{$search}%"))
            ->when($type !== '', fn ($q) => $q->where('type', $type))
            ->when($status === 'unread', fn ($q) => $q->unread())
            ->when($status === 'read', fn ($q) => $q->whereNotNull('read_at'))
            ->latest('created_at');
    }

    public function export(Request $request): StreamedResponse
    {
        $rows = $this->filtered(
            $request->string('q')->toString(),
            $request->string('type')->toString(),
            $request->string('status')->toString(),
        )->lazy();

        return Csv::stream('integrity-reports', ['ID', 'Type', 'Description', 'Read', 'Received At'],
            $rows->map(fn (IntegrityReport $r) => [
                $r->id,
                $r->type,
                $r->description,
                $r->read_at !== null ? 'yes' : 'no',
                $r->created_at?->toDateTimeString(),
            ]));
    }

    /** Membuka laporan MENANDAINYA terbaca — tidak ada tombol terpisah untuk itu. */
    public function show(IntegrityReport $integrityReport): Response
    {
        $integrityReport->markRead();

        return Inertia::render('Integrity/Show', [
            'report' => [
                'id' => $integrityReport->id,
                'type' => $integrityReport->type,
                'description' => $integrityReport->description,
                'receivedAt' => $integrityReport->created_at?->toIso8601String(),
            ],
        ]);
    }

    public function destroy(IntegrityReport $integrityReport): RedirectResponse
    {
        $integrityReport->delete();

        return to_route('integrity-reports.index')->with('success', __('backoffice.integrity.deleted'));
    }
}
