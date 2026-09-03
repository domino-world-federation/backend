<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Models\NewsletterSubscriber;
use App\Support\Csv;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Daftar langganan buletin.
 *
 * Tidak ada layar tambah dan tidak ada layar sunting: barisnya lahir dari
 * formulir di kaki situs publik, dan alamat yang diketik admin di sini adalah
 * alamat yang pemiliknya tidak pernah meminta dikirimi apa pun.
 */
class NewsletterController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('q')->toString();
        $status = $request->string('status')->toString();

        return Inertia::render('Newsletter/Index', [
            'subscribers' => $this->filtered($search, $status)
                ->paginate(config('dwf.per_page'))
                ->withQueryString()
                ->through(fn (NewsletterSubscriber $s) => [
                    'id' => $s->id,
                    'email' => $s->email,
                    'isSubscribed' => $s->isSubscribed(),
                    'joinedAt' => $s->created_at?->toIso8601String(),
                    'leftAt' => $s->unsubscribed_at?->toIso8601String(),
                ]),
            'subscribedCount' => NewsletterSubscriber::query()->subscribed()->count(),
            'filters' => ['q' => $search, 'status' => $status],
        ]);
    }

    /** Query daftar — dipakai layar daftar DAN ekspor, supaya keduanya sepakat. */
    private function filtered(string $search, string $status): Builder
    {
        return NewsletterSubscriber::query()
            ->when($search !== '', fn ($q) => $q->where('email', 'ilike', "%{$search}%"))
            ->when($status === 'subscribed', fn ($q) => $q->subscribed())
            ->when($status === 'unsubscribed', fn ($q) => $q->whereNotNull('unsubscribed_at'))
            ->latest('created_at');
    }

    public function export(Request $request): StreamedResponse
    {
        $rows = $this->filtered(
            $request->string('q')->toString(),
            $request->string('status')->toString(),
        )->lazy();

        return Csv::stream('newsletter', ['ID', 'Email', 'Subscribed', 'Joined At', 'Left At'],
            $rows->map(fn (NewsletterSubscriber $s) => [
                $s->id,
                $s->email,
                $s->isSubscribed() ? 'yes' : 'no',
                $s->created_at?->toDateTimeString(),
                $s->unsubscribed_at?->toDateTimeString(),
            ]));
    }

    /**
     * Sakelar langganan — MENANDAI, bukan menghapus.
     *
     * Baris yang hilang berarti alamat yang sama bisa didaftarkan ulang oleh
     * siapa pun, termasuk oleh orang yang baru saja keluar. Menandainya juga
     * yang membuat "berhenti lalu daftar lagi" dari situs publik bekerja tanpa
     * membuat baris kedua.
     */
    public function status(Request $request, NewsletterSubscriber $subscriber): RedirectResponse
    {
        $data = $request->validate(['is_subscribed' => ['required', 'boolean']]);

        $subscriber->update([
            'unsubscribed_at' => $data['is_subscribed'] ? null : now(),
        ]);

        return back()->with('success', __('backoffice.newsletter.updated'));
    }

    /**
     * Menghapus benar-benar menghapus.
     *
     * Dipisah dari sakelar di atas dengan sengaja: yang satu "orang ini berhenti
     * menerima kiriman", yang ini "alamat ini tidak pernah ada di sini" — dan
     * yang kedua itu yang diminta orang saat memakai haknya untuk dihapus.
     */
    public function destroy(NewsletterSubscriber $subscriber): RedirectResponse
    {
        $subscriber->delete();

        return back()->with('success', __('backoffice.newsletter.deleted'));
    }
}
