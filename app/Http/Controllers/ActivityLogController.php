<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Csv;
use App\Support\UserAgent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Jejak audit: siapa mengubah apa, kapan, dari nilai apa ke nilai apa.
 *
 * Hanya bisa dibaca. Sengaja tidak ada tombol hapus atau sunting — jejak audit
 * yang bisa dirapikan lewat antarmukanya sendiri berhenti jadi jejak audit.
 */
class ActivityLogController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $this->filters($request);

        return Inertia::render('ActivityLog/Index', [
            'entries' => $this->filtered($filters)
                ->paginate(config('dwf.per_page'))
                ->withQueryString()
                ->through(fn (Activity $a) => [
                    'id' => $a->id,
                    'module' => $a->log_name,
                    'moduleLabel' => $this->moduleLabel($a->log_name),
                    'event' => $a->event,
                    'eventLabel' => $this->eventLabel((string) $a->event),
                    'subject' => $this->subjectLabel($a),
                    'subjectId' => $a->subject_id,
                    'causer' => $a->causer?->name ?? __('backoffice.activity.system'),
                    'causerEmail' => $a->causer?->email,
                    'at' => $a->created_at?->toIso8601String(),
                    'ip' => $a->properties['ip'] ?? null,
                    // Ringkasan untuk dibaca sekilas, string asli untuk
                    // tooltip — kalau parser-nya salah, informasinya tidak
                    // ikut hilang.
                    'device' => UserAgent::summarise($a->properties['user_agent'] ?? null)['label'],
                    'userAgent' => $a->properties['user_agent'] ?? null,
                    'result' => self::resultOf((string) $a->event),
                    'changes' => $this->changes($a),
                ]),
            'modules' => Activity::query()->distinct()->orderBy('log_name')->pluck('log_name')
                ->map(fn (string $name) => ['value' => $name, 'label' => $this->moduleLabel($name)])
                ->all(),
            // Diambil dari isinya, bukan dipaku: begitu ada kejadian baru
            // (login, lockout, …) penyaringnya ikut mengenalinya tanpa ada
            // yang harus ingat menambahkannya di sini.
            'events' => Activity::query()->distinct()->orderBy('event')->pluck('event')
                ->filter()
                ->map(fn (string $e) => ['value' => $e, 'label' => $this->eventLabel($e)])
                ->values()
                ->all(),
            'causers' => User::query()->orderBy('name')->get(['id', 'name'])
                ->map(fn (User $u) => ['value' => (string) $u->id, 'label' => $u->name])
                ->all(),
            'filters' => $filters,
        ]);
    }

    /**
     * Kolom "Result" (`528:11529`) — "Success" atau "Blocked".
     *
     * Diturunkan dari nama kejadiannya, BUKAN kolom tersendiri di database.
     * Sebuah entri audit lahir karena sesuatu terjadi; apakah itu keberhasilan
     * atau penolakan sudah tersimpan di nama kejadiannya, dan kolom kedua yang
     * mengulanginya hanya menambah tempat untuk tidak sinkron.
     *
     * Daftarnya sengaja pendek dan eksplisit: kejadian yang TIDAK dikenali
     * dianggap berhasil, karena entri audit yang biasa memang mencatat sesuatu
     * yang sudah terjadi. Kejadian penolakan yang baru harus ditambahkan ke
     * sini — dan tesnya mengunci ketiganya supaya penambahan itu tidak
     * terlewat diam-diam.
     */
    public const BLOCKED_EVENTS = ['failed', 'lockout', 'access_denied'];

    public static function resultOf(string $event): string
    {
        return in_array($event, self::BLOCKED_EVENTS, true) ? 'blocked' : 'success';
    }

    /** @return array{module: string, event: string, causer: string, result: string, q: string, from: string, to: string} */
    private function filters(Request $request): array
    {
        return [
            'module' => $request->string('module')->toString(),
            'event' => $request->string('event')->toString(),
            'causer' => $request->string('causer')->toString(),
            'result' => $request->string('result')->toString(),
            'q' => $request->string('q')->toString(),
            'from' => $request->string('from')->toString(),
            'to' => $request->string('to')->toString(),
        ];
    }

    /**
     * Query daftar — dipakai layar daftar DAN ekspor, supaya keduanya sepakat.
     *
     * @param  array{module: string, event: string, causer: string, result: string, q: string, from: string, to: string}  $filters
     */
    private function filtered(array $filters): Builder
    {
        return Activity::query()
            ->with(['causer:id,name,email', 'subject'])
            ->when($filters['module'] !== '', fn ($q) => $q->where('log_name', $filters['module']))
            ->when($filters['event'] !== '', fn ($q) => $q->where('event', $filters['event']))
            ->when($filters['causer'] !== '', fn ($q) => $q->where('causer_id', $filters['causer']))

            // "Result" bukan kolom, jadi ia disaring lewat daftar kejadiannya —
            // sumber yang sama dengan `resultOf()`, supaya penyaring dan kolom
            // tidak bisa berbeda pendapat.
            ->when($filters['result'] === 'blocked', fn ($q) => $q->whereIn('event', self::BLOCKED_EVENTS))
            ->when($filters['result'] === 'success', fn ($q) => $q->where(fn ($w) => $w
                ->whereNotIn('event', self::BLOCKED_EVENTS)
                ->orWhereNull('event')))

            /*
             * Pencarian "event, target, or IP" (`528:11529`).
             *
             * `properties::text` menangkap IP, email, dan nilai yang berubah
             * sekaligus. Yang TIDAK tertangkap: nama subjek, karena ia dirakit
             * di PHP (`subjectLabel()`) dari relasi dan tidak pernah jadi teks
             * di baris ini. Mencari "DWF Governance Update" karena itu tidak
             * menemukan artikelnya — batas yang nyata, dan lebih jujur
             * dinyatakan di sini daripada ditutupi dengan join ke enam tabel
             * yang tiap modul baru harus ingat menambahnya.
             */
            ->when($filters['q'] !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('event', 'ilike', "%{$filters['q']}%")
                ->orWhere('log_name', 'ilike', "%{$filters['q']}%")
                ->orWhereRaw('properties::text ilike ?', ["%{$filters['q']}%"])
                ->orWhereHas('causer', fn ($c) => $c->where('name', 'ilike', "%{$filters['q']}%"))))

            // Rentang tanggal inklusif di kedua ujung: orang yang mengetik
            // tanggal yang sama di dua kotak bermaksud "hari itu", bukan
            // "tengah malam persis".
            ->when($filters['from'] !== '', fn ($q) => $q->whereDate('created_at', '>=', $filters['from']))
            ->when($filters['to'] !== '', fn ($q) => $q->whereDate('created_at', '<=', $filters['to']))
            ->latest('id');
    }

    /**
     * Ekspor jejak audit, mengikuti filter yang sedang aktif.
     *
     * Ini SATU-SATUNYA cara membawa log keluar dari aplikasi, dan itu memang
     * disengaja: layar ini tidak punya tombol hapus maupun sunting — jejak audit
     * yang bisa dirapikan lewat antarmukanya sendiri berhenti jadi jejak audit.
     * Menyalinnya keluar tidak mengubah apa pun di dalam.
     *
     * Perubahannya diratakan jadi satu sel `field: dari → ke`; satu baris per
     * atribut akan membuat satu penyuntingan tersebar di belasan baris CSV dan
     * kehilangan bentuk aslinya.
     */
    public function export(Request $request): StreamedResponse
    {
        $rows = $this->filtered($this->filters($request))->lazy();

        return Csv::stream('audit-log', [
            'ID', 'When', 'Actor', 'Actor Email', 'IP', 'Device',
            'Module', 'Event', 'Target', 'Target ID', 'Result', 'Changes',
        ], $rows->map(fn (Activity $a) => [
            $a->id,
            $a->created_at?->toDateTimeString(),
            $a->causer?->name ?? __('backoffice.activity.system'),
            $a->causer?->email,
            $a->properties['ip'] ?? null,
            UserAgent::summarise($a->properties['user_agent'] ?? null)['label'],
            $this->moduleLabel($a->log_name),
            $this->eventLabel((string) $a->event),
            $this->subjectLabel($a),
            $a->subject_id,
            self::resultOf((string) $a->event),
            collect($this->changes($a))
                ->map(fn (array $c) => "{$c['field']}: ".($c['from'] ?? '—').' -> '.($c['to'] ?? '—'))
                ->implode(' | '),
        ]));
    }

    /**
     * `news-category` -> `News Category`.
     *
     * Diturunkan, bukan dipetakan tangan: `log_name` berasal dari nama kelas
     * model, jadi peta manual akan basi diam-diam tiap kali ada model baru —
     * dan yang muncul di layar adalah slug mentah, bukan galat.
     */
    /** `login` -> `Login`. Sama seperti modul: diturunkan, bukan dipetakan. */
    private function eventLabel(string $event): string
    {
        return str($event)->replace('-', ' ')->headline()->toString();
    }

    private function moduleLabel(string $logName): string
    {
        $label = str($logName)->replace('-', ' ')->headline()->toString();

        // `headline()` menghasilkan "Faq" — benar sebagai kapitalisasi, salah
        // sebagai kata. Akronim ditegakkan setelahnya, bukan dengan menulis
        // peta untuk seluruh modul.
        return preg_replace('/\bFaq\b/', 'FAQ', $label) ?? $label;
    }

    /**
     * Nama baris yang diubah, bukan `NewsCategory #5`.
     *
     * Tiga tingkat, sengaja berurutan:
     *   1. barisnya masih ada  -> baca kolom judulnya sekarang;
     *   2. barisnya SUDAH DIHAPUS -> baca dari properti yang tercatat di log
     *      itu sendiri. Ini yang penting: entri `deleted` justru yang paling
     *      sering dicari orang, dan justru di situ relasinya selalu null;
     *   3. tidak ada keduanya -> `#id`, supaya baris log tetap bisa ditunjuk.
     */
    private function subjectLabel(Activity $activity): string
    {
        // Urutan kolomnya penting: `question` sebelum `title` karena FAQ punya
        // keduanya lewat trait, dan yang dikenali orang pertanyaannya.
        $columns = ['name', 'question', 'title', 'subject', 'email', 'key'];

        $subject = $activity->subject;

        if ($subject !== null) {
            foreach ($columns as $column) {
                if (filled($subject->{$column} ?? null)) {
                    return (string) $subject->{$column};
                }
            }
        }

        // Entri otentikasi tidak punya `attributes`/`old` — propertinya datar
        // (`email`, `ip`). Ketiganya digabung supaya percobaan gagal dari email
        // yang tidak terdaftar tetap menampilkan email yang dicoba, bukan `#`.
        $recorded = (array) ($activity->properties['attributes'] ?? [])
            + (array) ($activity->properties['old'] ?? [])
            + $activity->properties->toArray();

        foreach ($columns as $column) {
            if (filled($recorded[$column] ?? null)) {
                return (string) $recorded[$column];
            }
        }

        return '#'.$activity->subject_id;
    }

    /**
     * Perubahan sebagai daftar `atribut: lama -> baru`.
     *
     * Diratakan di server, bukan di Vue: bentuk `properties` milik spatie
     * (`attributes` + `old`) adalah detail pustaka, dan membocorkannya ke
     * komponen berarti mengganti pustaka nanti akan merusak tampilan.
     *
     * @return array<int, array{field: string, from: string|null, to: string|null}>
     */
    private function changes(Activity $activity): array
    {
        $new = (array) ($activity->properties['attributes'] ?? []);
        $old = (array) ($activity->properties['old'] ?? []);

        $render = function ($value): ?string {
            if ($value === null) {
                return null;
            }

            if (is_bool($value)) {
                return $value ? 'true' : 'false';
            }

            if (is_array($value)) {
                return json_encode($value, JSON_UNESCAPED_UNICODE);
            }

            // Isi editor bisa ribuan karakter; tabel audit bukan tempat
            // membaca artikel.
            return str((string) $value)->stripTags()->squish()->limit(120)->toString();
        };

        return collect(array_keys($new + $old))
            ->map(fn (string $field) => [
                'field' => $field,
                'from' => $render($old[$field] ?? null),
                'to' => $render($new[$field] ?? null),
            ])
            ->values()
            ->all();
    }
}
