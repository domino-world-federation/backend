<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use App\Support\Csv;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ContactMessageController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('q')->toString();
        $topic = $request->string('topic')->toString();
        $status = $request->string('status')->toString();

        return Inertia::render('ContactMessages/Index', [
            'messages' => $this->filtered($search, $topic, $status)
                ->paginate(config('dwf.per_page'))
                ->withQueryString()
                ->through(fn (ContactMessage $m) => [
                    'id' => $m->id,
                    'name' => $m->name,
                    'email' => $m->email,
                    'topic' => $m->topic,
                    // Formulir situs publik tidak menanyakan subjek; yang
                    // ditanyakannya topik. Jatuh ke sana supaya barisnya tetap
                    // punya identitas yang bisa diklik.
                    'subject' => $m->subject ?? $m->topic,
                    'isRead' => $m->read_at !== null,
                    'receivedAt' => $m->created_at?->toIso8601String(),
                ]),
            'topics' => ContactMessage::TOPICS,
            'unreadCount' => ContactMessage::query()->unread()->count(),
            'filters' => ['q' => $search, 'topic' => $topic, 'status' => $status],
        ]);
    }

    /** Query daftar — dipakai layar daftar DAN ekspor, supaya keduanya sepakat. */
    private function filtered(string $search, string $topic, string $status): Builder
    {
        return ContactMessage::query()
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'ilike', "%{$search}%")
                ->orWhere('email', 'ilike', "%{$search}%")
                ->orWhere('subject', 'ilike', "%{$search}%")))
            ->when($topic !== '', fn ($q) => $q->where('topic', $topic))
            ->when($status === 'unread', fn ($q) => $q->unread())
            ->when($status === 'read', fn ($q) => $q->whereNotNull('read_at'))
            ->latest('created_at');
    }

    /**
     * Ekspor CSV, mengikuti filter yang sedang aktif.
     *
     * ISI PESANNYA IKUT. Itu memang gunanya: yang biasanya diminta orang dari
     * layar ini bukan daftar nama, melainkan bahan untuk dibalas dan diarsipkan
     * di luar aplikasi. Isinya dicetak apa adanya — ia tidak pernah dirender
     * sebagai HTML, di sini maupun di CSV.
     */
    public function export(Request $request): StreamedResponse
    {
        $rows = $this->filtered(
            $request->string('q')->toString(),
            $request->string('topic')->toString(),
            $request->string('status')->toString(),
        )->lazy();

        return Csv::stream('contact-messages', [
            'ID', 'Received At', 'Name', 'Email', 'Country', 'Topic', 'Subject', 'Message', 'Read',
        ], $rows->map(fn (ContactMessage $m) => [
            $m->id,
            $m->created_at?->toDateTimeString(),
            $m->name,
            $m->email,
            $m->country,
            $m->topic,
            $m->subject ?? $m->topic,
            $m->message,
            $m->read_at !== null ? 'yes' : 'no',
        ]));
    }

    public function show(ContactMessage $contactMessage): Response
    {
        // Membuka pesan menandainya terbaca — itu yang diharapkan orang, dan
        // menuntut klik tambahan hanya membuat penghitung "belum dibaca"
        // berhenti bisa dipercaya.
        $contactMessage->markRead();

        return Inertia::render('ContactMessages/Show', [
            'message' => [
                'id' => $contactMessage->id,
                'name' => $contactMessage->name,
                'email' => $contactMessage->email,
                'country' => $contactMessage->country,
                'topic' => $contactMessage->topic,
                'subject' => $contactMessage->subject ?? $contactMessage->topic,
                'body' => $contactMessage->message,
                'receivedAt' => $contactMessage->created_at?->toIso8601String(),
            ],
        ]);
    }

    public function destroy(ContactMessage $contactMessage): RedirectResponse
    {
        $contactMessage->delete();

        return to_route('contact-messages.index')->with('success', __('backoffice.messages.deleted'));
    }
}
