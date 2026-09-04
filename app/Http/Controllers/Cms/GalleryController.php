<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Models\GalleryEvent;
use App\Models\GalleryItem;
use App\Models\Tournament;
use App\Support\Csv;
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

class GalleryController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $this->filters($request);

        return Inertia::render('Gallery/Index', [
            'items' => $this->filtered($filters)
                ->paginate(config('dwf.per_page'))
                ->withQueryString()
                ->through(fn (GalleryItem $i) => [
                    'id' => $i->id,
                    'kind' => $i->kind,
                    'url' => StoredFile::url($i->path),
                    'alt' => $i->alt,
                    'event' => $i->event?->name,
                    'eventType' => $i->event?->type,
                    'visibility' => $i->visibility,
                    'scheduledFor' => $i->visibility === 'scheduled' ? $i->published_at?->toIso8601String() : null,
                    'canSchedule' => $i->published_at?->isFuture() ?? false,

                    // Tiga kolom berbentuk sama di `478:5884`: nama di atas,
                    // waktu di bawahnya.
                    'publishedAt' => $i->published_at?->toIso8601String(),
                    'publishedBy' => $i->publisher?->name,
                    'createdAt' => $i->created_at?->toIso8601String(),
                    'createdBy' => $i->creator?->name,
                    'updatedAt' => $i->updated_at?->toIso8601String(),
                    'updatedBy' => $i->editor?->name,
                ]),
            'filters' => $filters,
        ]);
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
     * Pencarian menjangkau alt text DAN nama event. Desain hanya menggambar dua
     * dropdown (Visibility, Category), jadi penyaring "event tertentu" yang dulu
     * ada tidak punya tempat lagi — dan tanpa `orWhereHas` di bawah,
     * kemampuannya hilang begitu saja alih-alih pindah ke kotak pencarian.
     *
     * @param  array{q: string, status: string, category: string}  $filters
     */
    private function filtered(array $filters): Builder
    {
        return GalleryItem::query()
            ->with([
                'event:id,name,type',
                'editor:id,name',
                'creator:id,name',
                'publisher:id,name',
            ])
            ->when($filters['q'] !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('alt', 'ilike', "%{$filters['q']}%")
                ->orWhereHas('event', fn ($e) => $e->where('name', 'ilike', "%{$filters['q']}%"))))
            ->when(
                in_array($filters['status'], GalleryItem::STATUSES, true),
                fn ($q) => $q->where('status', $filters['status']),
            )
            // "Category" di desain adalah jenis event-nya — Event atau
            // Tournament — yang dicetak sebagai subteks di kolom Image Info.
            ->when($filters['category'] !== '', fn ($q) => $q->whereHas(
                'event',
                fn ($e) => $e->where('type', $filters['category']),
            ))
            ->ordered();
    }

    public function export(Request $request): StreamedResponse
    {
        $rows = $this->filtered($this->filters($request))->lazy();

        return Csv::stream('gallery', [
            'ID', 'Kind', 'Alt Text', 'Slug', 'Event', 'Category', 'Visibility',
            'Published At', 'Published By', 'Created At', 'Created By',
            'Last Modified At', 'Last Modified By',
        ], $rows->map(fn (GalleryItem $i) => [
            $i->id,
            $i->kind,
            $i->alt,
            $i->slug,
            $i->event?->name,
            $i->event?->type,
            $i->visibility,
            $i->published_at?->toDateTimeString(),
            $i->publisher?->name,
            $i->created_at?->toDateTimeString(),
            $i->creator?->name,
            $i->updated_at?->toDateTimeString(),
            $i->editor?->name,
        ]));
    }

    /**
     * Ubah visibilitas langsung dari barisnya.
     *
     * Desain menaruh pemilihnya DI DALAM sel (`478:5921` — ikon globe, teks,
     * chevron), bukan sebagai menu terpisah: menayangkan sesuatu tidak
     * seharusnya menuntut membuka formulirnya. Kontrol, kosakata, dan aturannya
     * sama persis dengan kolom Visibility di News — termasuk penolakan
     * `scheduled` tanpa jadwal.
     */
    public function visibility(Request $request, GalleryItem $gallery): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(GalleryItem::QUICK_STATUSES)],
        ]);

        if ($data['status'] === GalleryItem::STATUS_SCHEDULED && ! $gallery->published_at?->isFuture()) {
            throw ValidationException::withMessages([
                'status' => __('backoffice.news.needs_schedule'),
            ]);
        }

        if ($data['status'] === GalleryItem::STATUS_PUBLISHED && $gallery->published_at === null) {
            $gallery->published_at = now();
        }

        $gallery->status = $data['status'];
        $gallery->save();

        return back()->with('success', __('backoffice.gallery.updated'));
    }

    public function create(): Response
    {
        return Inertia::render('Gallery/Form', [
            'item' => null,
            'events' => $this->eventOptions(),
            'tournaments' => $this->tournamentOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request, required: true);
        $event = $this->resolveEvent($data);

        // Kunci nullable yang tidak dikirim TIDAK muncul di data tervalidasi —
        // `$data['alt']` akan melempar "Undefined array key" alih-alih bernilai
        // null. Semua pembacaan opsional lewat `??` karena itu.
        $alt = $data['alt'] ?? '';

        // Slug boleh diketik sendiri (`478:6930`); kalau dikosongkan ia lahir
        // dari alt, dan kalau alt juga kosong dari nama event + jenis aset.
        $slugSource = $data['slug'] ?? '';
        if ($slugSource === '') {
            $slugSource = $alt !== '' ? $alt : ($event->name.'-'.$data['kind']);
        }

        GalleryItem::create([
            'gallery_event_id' => $event->id,
            'kind' => $data['kind'],
            'alt' => $alt !== '' ? $alt : null,
            'slug' => GalleryItem::uniqueSlug($slugSource),
            'path' => StoredFile::put($request->file('asset'), 'gallery'),
            'status' => $this->resolvedStatus($data),
            'published_at' => $this->resolvedPublishedAt($data),
            'position' => GalleryItem::nextPosition(),
        ]);

        return to_route('gallery.index')->with('success', __('backoffice.gallery.saved'));
    }

    public function edit(GalleryItem $gallery): Response
    {
        return Inertia::render('Gallery/Form', [
            'item' => [
                'id' => $gallery->id,
                'kind' => $gallery->kind,
                'alt' => $gallery->alt,
                'eventId' => $gallery->gallery_event_id,
                'eventType' => $gallery->event?->type,
                'tournamentId' => $gallery->event?->tournament_id,
                'slug' => $gallery->slug,
                'status' => $gallery->status,
                'publishedAt' => $gallery->published_at?->format('Y-m-d\TH:i'),
                'url' => StoredFile::url($gallery->path),
            ],
            'events' => $this->eventOptions(),
            'tournaments' => $this->tournamentOptions(),
        ]);
    }

    public function update(Request $request, GalleryItem $gallery): RedirectResponse
    {
        $data = $this->validated($request, required: false);
        $event = $this->resolveEvent($data);

        $payload = [
            'gallery_event_id' => $event->id,
            'kind' => $data['kind'],
            'alt' => ($data['alt'] ?? '') !== '' ? $data['alt'] : null,
            'status' => $this->resolvedStatus($data),
            'published_at' => $this->resolvedPublishedAt($data),
        ];

        if (($data['slug'] ?? '') !== '') {
            $payload['slug'] = GalleryItem::uniqueSlug($data['slug'], $gallery->id);
        }

        if ($request->hasFile('asset')) {
            $payload['path'] = StoredFile::put($request->file('asset'), 'gallery', $gallery->path);
        }

        $gallery->update($payload);

        return to_route('gallery.index')->with('success', __('backoffice.gallery.updated'));
    }

    public function destroy(GalleryItem $gallery): RedirectResponse
    {
        StoredFile::forget($gallery->path);
        $gallery->delete();

        return back()->with('success', __('backoffice.gallery.deleted'));
    }

    /**
     * Tombolnya tiga (Save Draft / Publish / Schedule) tapi kolomnya satu.
     *
     * Pemetaannya dikunci di sini, bukan ditebak di dua tempat — persis seperti
     * `NewsArticleRequest::resolvedStatus()`.
     *
     * @param  array<string, mixed>  $data
     */
    private function resolvedStatus(array $data): string
    {
        return match ($data['posting']) {
            'draft' => GalleryItem::STATUS_DRAFT,
            'schedule' => GalleryItem::STATUS_SCHEDULED,
            default => GalleryItem::STATUS_PUBLISHED,
        };
    }

    /** @param array<string, mixed> $data */
    private function resolvedPublishedAt(array $data): ?CarbonImmutable
    {
        return match ($data['posting']) {
            // Draft belum punya tanggal tayang, dan menuliskannya sekarang
            // berarti tanggal itu ikut terbit begitu statusnya diubah nanti —
            // tanggal yang sudah lewat sebelum barangnya pernah terlihat.
            'draft' => null,
            'schedule' => CarbonImmutable::parse($data['published_at']),
            default => CarbonImmutable::now(),
        };
    }

    /**
     * Ke mana aset ini ditempelkan.
     *
     * DUA jalan yang berbeda, bukan satu dengan syarat:
     *
     * — **Tournament** selalu menunjuk turnamen yang sudah ada. Tidak ada
     *   "New" di sana, dan itu permintaan pemilik repo 2026-09-04 yang
     *   memperbaiki hal nyata: mengetik nama turnamen di layar galeri
     *   menghasilkan turnamen kedua yang cuma ada di galeri — tidak punya
     *   tanggal, tidak punya venue, tidak pernah ikut berubah saat yang asli
     *   diganti nama. Turnamen dibuat di modul Tournaments, satu tempat.
     *
     * — **Event** tetap dua jalan. Acara galeri tidak punya modul sendiri;
     *   membuang "New" di sana berarti tidak ada satu pun layar yang bisa
     *   melahirkannya.
     */
    private function resolveEvent(array $data): GalleryEvent
    {
        if ($data['type'] === 'tournament') {
            return GalleryEvent::forTournament(Tournament::findOrFail($data['tournament_id'] ?? null));
        }

        if (($data['event_mode'] ?? 'existing') === 'existing') {
            return GalleryEvent::findOrFail($data['gallery_event_id'] ?? null);
        }

        return GalleryEvent::create([
            'name' => $data['event_name'],
            'slug' => GalleryEvent::uniqueSlug($data['event_name']),
            'type' => $data['type'],
        ]);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, bool $required): array
    {
        $uploads = config('dwf.uploads');
        $kind = $request->string('kind')->toString();

        /*
         * Kedua field acara diikat ke `type`, bukan cuma ke `event_mode`.
         *
         * Layar Add Gallery memulai dengan `event_mode: 'new'` — bawaan untuk
         * Event — dan tidak membuangnya saat orang memilih Tournament; field-nya
         * cuma berhenti digambar. Dengan `required_if:event_mode,new` yang
         * polos, formulir turnamen menuntut `event_name` yang tidak punya kotak
         * di layar: yang dilihat orangnya tombol Publish yang tidak melakukan
         * apa-apa, dan pesan galatnya menempel pada field tak terlihat.
         *
         * Diperbaiki di server, bukan dengan membersihkan keadaan formulir di
         * Vue: aturan yang benar tetap benar untuk permintaan yang tidak datang
         * dari layar itu. Ada tesnya, yang mengirim persis muatan formulirnya.
         */
        $type = $request->string('type')->toString();
        $mode = $request->string('event_mode')->toString();
        $isEvent = $type === 'event';

        $mimes = $kind === 'video' ? $uploads['video_mimes'] : $uploads['image_mimes'];
        $maxKb = $kind === 'video' ? $uploads['video_max_kb'] : $uploads['image_max_kb'];

        return $request->validate([
            'type' => ['required', Rule::in(GalleryEvent::TYPES)],
            'kind' => ['required', Rule::in(GalleryItem::KINDS)],

            // Turnamen: satu field, dan wajib menunjuk baris yang ada.
            // SEMUA status ikut — draft sekalipun. Galeri disiapkan sebelum
            // turnamennya tayang, dan daftar yang cuma memuat yang sudah
            // tayang berarti fotonya baru bisa diunggah setelah halamannya
            // dibuka umum.
            'tournament_id' => ['required_if:type,tournament', 'nullable', 'integer', 'exists:tournaments,id'],

            // Acara: dua jalan, seperti sebelumnya. `required_if:type,event`
            // yang membuat ketiganya diam saat yang dipilih turnamen.
            'event_mode' => ['required_if:type,event', 'nullable', Rule::in(['new', 'existing'])],
            'gallery_event_id' => [
                Rule::requiredIf($isEvent && $mode === 'existing'),
                'nullable', 'integer', 'exists:gallery_events,id',
            ],
            'event_name' => [
                Rule::requiredIf($isEvent && $mode === 'new'),
                'nullable', 'string', 'max:160',
            ],
            'alt' => ['nullable', 'string', 'max:200'],
            'slug' => ['nullable', 'string', 'max:200'],
            'posting' => ['required', Rule::in(['now', 'schedule', 'draft'])],
            'published_at' => ['nullable', 'required_if:posting,schedule', 'date'],
            'asset' => [
                $required ? 'required' : 'nullable',
                'file',
                'mimes:'.implode(',', $mimes),
                'max:'.$maxKb,
            ],
        ], attributes: [
            'tournament_id' => 'turnamen',
            'gallery_event_id' => 'event',
            'event_name' => 'nama event',
            'asset' => 'berkas aset',
            'alt' => 'keterangan',
        ]);
    }

    /**
     * Pilihan untuk tipe "Event" saja.
     *
     * Album turnamen tidak ikut: yang dipilih orang di sana adalah TURNAMENnya
     * (lihat `tournamentOptions()`), dan albumnya lahir dari pilihan itu. Kalau
     * keduanya dikirim, layar akan punya dua daftar yang mengaku menjawab
     * pertanyaan yang sama.
     *
     * @return array<int, array{value: int, label: string, type: string}>
     */
    private function eventOptions(): array
    {
        return GalleryEvent::query()
            ->where('type', 'event')
            ->orderBy('name')
            ->get(['id', 'name', 'type'])
            ->map(fn (GalleryEvent $e) => ['value' => $e->id, 'label' => $e->name, 'type' => $e->type])
            ->all();
    }

    /**
     * Turnamen yang bisa dipilih — SEMUA status, tanpa kecuali.
     *
     * Tidak ada `->live()` di sini dan itu disengaja: galeri justru disiapkan
     * sebelum turnamennya tayang. Menyaringnya berarti foto baru bisa diunggah
     * setelah halaman turnamennya dibuka umum, dan urutan kerja yang benar
     * justru kebalikannya.
     *
     * Terbaru di atas: yang sedang dikerjakan orang hampir selalu yang paling
     * dekat tanggalnya, bukan yang paling awal abjadnya.
     *
     * @return array<int, array{value: int, label: string}>
     */
    private function tournamentOptions(): array
    {
        return Tournament::query()
            ->orderByDesc('starts_on')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Tournament $t) => ['value' => $t->id, 'label' => $t->name])
            ->all();
    }
}
