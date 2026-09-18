<?php

namespace App\Http\Requests\Cms;

use App\Models\Document;
use App\Models\Tournament;
use App\Support\TournamentRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validasi formulir Add Tournament (`585:11241`).
 *
 * Tiap baris di bawah punya pasangannya di desain: teks kecil di bawah field
 * ("Required • 2–120 characters.") adalah kontrak, bukan hiasan, dan angkanya
 * disalin apa adanya.
 *
 * **SELURUH field wajib sejak 2026-09-18**, atas permintaan pemilik repo —
 * termasuk yang desainnya tandai opsional (hadiah, kontak, pendaftaran, foto
 * ofisial, area jadwal, jumlah peserta, dokumen), dan minimal satu baris
 * ofisial dan satu baris jadwal. Dua pengecualian yang disengaja, dan
 * keduanya bukan "opsional" melainkan "sudah terisi":
 *
 *   - Gambar (hero, hadiah, foto ofisial) saat MENYUNTING: tidak mengunggah
 *     apa pun berarti mempertahankan yang tersimpan. Menuntut unggah ulang
 *     berarti memperbaiki satu typo di nama memaksa mengunggah tiga gambar
 *     lagi. Yang tetap dituntut adalah gambar yang memang BELUM ada.
 *   - `published_at`, yang hanya berarti kalau Publish Time = Schedule.
 */
class TournamentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $tournament = $this->route('tournament');
        $options = config('dwf.tournaments');
        $uploads = config('dwf.uploads');

        $image = [
            'image',
            'mimes:'.implode(',', $uploads['image_mimes']),
            'max:'.$uploads['image_max_kb'],
        ];

        return [
            // --- Basic Information ---
            'name' => ['required', 'string', 'max:160'],
            // Wajib, tapi layar mengisinya sendiri dari nama selama orangnya
            // belum menyentuh kolom ini — jadi "wajib" tidak berarti harus
            // mengetik alamat URL dengan tangan.
            'slug' => [
                'required', 'string', 'max:180', 'regex:/^[a-z0-9-]+$/',
                Rule::unique('tournaments', 'slug')->ignore($tournament?->id),
            ],
            'coverage' => ['required', Rule::in($options['coverage'])],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'city' => ['required', 'string', 'max:120'],
            'country' => ['required', 'string', 'max:120'],
            'rules_format' => ['required', Rule::in(TournamentRules::names())],

            // "Primary image displayed in the tournament hero area." Wajib saat
            // membuat; saat menyunting, tidak mengunggah apa pun berarti
            // mempertahankan yang lama.
            'hero_image' => [$tournament === null ? 'required' : 'nullable', ...$image],

            // "50–3,000 characters" (`596:11302`).
            'overview' => ['required', 'string', 'min:50', 'max:3000'],

            // --- Venue ---
            'venue_name' => ['required', 'string', 'min:2', 'max:120'],
            'venue_address' => ['required', 'string', 'min:5', 'max:200'],
            'venue_lat' => ['required', 'numeric', 'between:-90,90'],
            'venue_lng' => ['required', 'numeric', 'between:-180,180'],

            // --- Prize Information ---
            'prize_amount' => ['required', 'numeric', 'min:0'],
            'prize_currency' => ['required', Rule::in($options['currencies'])],
            'prize_description' => ['required', 'string', 'max:240'],
            // Wajib saat membuat, dan saat menyunting turnamen yang BELUM
            // punya gambar hadiah — turnamen lama yang dibuat ketika field ini
            // masih opsional tidak boleh lolos hanya karena ia disunting.
            'prize_image' => [
                $tournament === null || blank($tournament->prize_image_path) ? 'required' : 'nullable',
                ...$image,
            ],

            // --- Tournament Contact ---
            'contact_email' => ['required', 'email', 'max:160'],
            'contact_phone' => ['required', 'string', 'max:40'],

            // --- Officials & Referees (berulang) ---
            'officials' => ['required', 'array', 'min:1', 'max:50'],

            /*
             * `id`, BUKAN `photo_path`.
             *
             * Saat menyunting, ofisial yang fotonya tidak diunggah ulang harus
             * mempertahankan foto lamanya — dan cara termudah adalah formulir
             * mengirim balik path-nya. Itu juga cara termudah membiarkan orang
             * mengetik path apa pun ke dalam kolom itu. Yang dikirim balik
             * karena itu id barisnya, dan controller yang mencari path-nya
             * sendiri; `Rule::exists` di bawah mengunci id itu ke turnamen ini.
             */
            'officials.*.id' => [
                'nullable', 'integer',
                Rule::exists('tournament_officials', 'id')
                    ->where('tournament_id', $tournament?->id ?? 0),
            ],
            'officials.*.name' => ['required', 'string', 'max:120'],
            'officials.*.role' => ['required', 'string', 'max:120'],
            'officials.*.country' => ['required', 'string', 'max:120'],
            // `nullable` di SINI, wajibnya di `checkOfficialsHavePhotos()`:
            // baris yang sudah punya foto tersimpan tidak mengirim ulang
            // berkasnya, dan aturan statis tidak bisa melihat itu.
            'officials.*.photo' => ['nullable', ...$image],

            // --- Eligibility & Registration ---
            'registration_starts_on' => ['required', 'date'],
            'registration_ends_on' => ['required', 'date'],
            'dwf_id_requirement' => ['required', Rule::in($options['dwf_id_requirements'])],
            'eligibility' => ['required', Rule::in($options['eligibility'])],
            'registration_method' => ['required', Rule::in($options['registration_methods'])],

            // --- Schedule (berulang) ---
            'schedule' => ['required', 'array', 'min:1', 'max:200'],
            'schedule.*.held_on' => ['required', 'date'],
            'schedule.*.starts_at' => ['required', 'date_format:H:i'],
            'schedule.*.activity' => ['required', 'string', 'min:3', 'max:120'],
            'schedule.*.area' => ['required', 'string', 'max:120'],

            // --- Tournament Format ---
            /*
             * `attendance` dan `game_format` TIDAK lagi diterima dari layar.
             *
             * Yang pertama dipatok "Offline" — seluruh turnamen federasi ini
             * digelar langsung. Yang kedua disembunyikan karena `rules_format`
             * sudah mengatakannya: "Double 101" adalah formatnya, dan dua kolom
             * untuk satu fakta berarti dua jawaban yang suatu saat berbeda.
             */

            /*
             * Jumlah peserta dibatasi daftar milik ATURANNYA, bukan sekadar
             * bilangan bulat. Babak gugur hanya bekerja pada pangkat dua, dan
             * kalimat sistem kompetisi menghitung `($n / 2)` atau `($n / 4)` —
             * 100 tim akan mencetak "50 opening-round matches" untuk bagan yang
             * tidak bisa disusun.
             *
             * Daftarnya dibaca dari `rules_format` yang DIKIRIM di permintaan
             * yang sama, jadi mengganti aturan dan jumlahnya sekaligus tetap
             * divalidasi terhadap pasangan yang benar.
             */
            'participant_count' => [
                'required',
                'integer',
                Rule::in(TournamentRules::countsFor($this->string('rules_format')->toString())),
            ],

            /*
             * `participant_type`, `competition_system` dan `scoring` TIDAK lagi
             * diterima dari permintaan: ketiganya diturunkan dari aturannya di
             * controller. Menerimanya berarti dua sumber kebenaran, dan yang
             * dikirim klien adalah yang kalah — sebuah field yang bisa diisi
             * tapi tidak pernah dipakai.
             */

            // --- Regulations & Rules ---
            // "select up to 10 existing published documents".
            'documents' => ['required', 'array', 'min:1', 'max:'.$options['max_documents']],
            'documents.*' => [Rule::exists('documents', 'id')],

            'posting' => ['required', Rule::in(['draft', 'now', 'schedule'])],
            'published_at' => ['required_if:posting,schedule', 'nullable', 'date'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $this->checkRegistrationWindow($validator);
            $this->checkScheduleWithinTournament($validator);
            $this->checkOfficialsHavePhotos($validator);
            $this->checkDocumentsArePublished($validator);
        });
    }

    /**
     * "must be on or after Registration Start Date and before tournament start"
     * (`596:11304`).
     *
     * Cabang "mulai diisi, akhir kosong" sudah dibuang: sejak keduanya wajib,
     * aturan `required` yang menangkapnya, dan mempertahankan cabang itu
     * berarti field yang sama mencetak dua galat untuk satu kesalahan.
     */
    private function checkRegistrationWindow(Validator $validator): void
    {
        $start = $this->input('registration_starts_on');
        $end = $this->input('registration_ends_on');

        if (blank($start) || blank($end)) {
            return;
        }

        if (strtotime($end) < strtotime($start)) {
            $validator->errors()->add('registration_ends_on', __('backoffice.tournaments.registration_end_before_start'));
        }

        if (filled($this->input('starts_on')) && strtotime($end) >= strtotime($this->input('starts_on'))) {
            $validator->errors()->add('registration_ends_on', __('backoffice.tournaments.registration_end_after_kickoff'));
        }
    }

    /** "must fall within tournament dates" (`596:11371`). */
    private function checkScheduleWithinTournament(Validator $validator): void
    {
        $start = $this->input('starts_on');
        $end = $this->input('ends_on');

        if (blank($start) || blank($end)) {
            return;
        }

        foreach ((array) $this->input('schedule', []) as $index => $entry) {
            $day = $entry['held_on'] ?? null;

            if (blank($day)) {
                continue;
            }

            if (strtotime($day) < strtotime($start) || strtotime($day) > strtotime($end)) {
                $validator->errors()->add(
                    "schedule.{$index}.held_on",
                    __('backoffice.tournaments.schedule_outside_dates'),
                );
            }
        }
    }

    /**
     * Tiap ofisial wajib punya foto — yang baru diunggah, ATAU yang sudah
     * tersimpan di baris itu.
     *
     * Tidak bisa jadi aturan statis: saat menyunting, baris yang fotonya tidak
     * diganti tidak mengirim berkas apa pun, dan hanya id barisnya yang bisa
     * mengatakan apakah foto lama itu ada. Id-nya sudah dikunci ke turnamen ini
     * oleh `Rule::exists` di atas, jadi yang dicocokkan di sini memang baris
     * miliknya — bukan foto ofisial turnamen lain.
     *
     * (Pemeriksaan "mata uang wajib kalau nominal diisi" yang dulu ada di
     * tempat ini sudah dibuang: keduanya kini wajib, jadi `required` yang
     * menangkapnya, dan mempertahankannya berarti dua galat untuk satu hal.)
     */
    private function checkOfficialsHavePhotos(Validator $validator): void
    {
        $tournament = $this->route('tournament');

        $withPhoto = $tournament === null
            ? []
            : $tournament->officials()->whereNotNull('photo_path')->pluck('id')->all();

        foreach ((array) $this->input('officials', []) as $index => $official) {
            if ($this->hasFile("officials.{$index}.photo")) {
                continue;
            }

            $id = $official['id'] ?? null;

            if (filled($id) && in_array((int) $id, $withPhoto, true)) {
                continue;
            }

            $validator->errors()->add(
                "officials.{$index}.photo",
                __('backoffice.tournaments.official_photo_required'),
            );
        }
    }

    /**
     * "select up to 10 existing PUBLISHED documents" (`596:11467`).
     *
     * Menautkan draf berarti halaman turnamen memuat tautan ke berkas yang
     * belum boleh dilihat siapa pun — dan yang menemukannya adalah pengunjung,
     * bukan kita.
     */
    private function checkDocumentsArePublished(Validator $validator): void
    {
        $ids = array_filter((array) $this->input('documents', []));

        if ($ids === []) {
            return;
        }

        $publishedCount = Document::query()->whereKey($ids)->live()->count();

        if ($publishedCount !== count(array_unique($ids))) {
            $validator->errors()->add('documents', __('backoffice.tournaments.documents_must_be_live'));
        }
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'name' => __('backoffice.tournaments.name'),
            'coverage' => __('backoffice.tournaments.coverage'),
            'starts_on' => __('backoffice.tournaments.starts_on'),
            'ends_on' => __('backoffice.tournaments.ends_on'),
            'hero_image' => __('backoffice.tournaments.hero_image'),
            'overview' => __('backoffice.tournaments.overview'),
            'venue_name' => __('backoffice.tournaments.venue_name'),
            'venue_address' => __('backoffice.tournaments.venue_address'),
            'venue_lat' => __('backoffice.tournaments.map_location'),
            'venue_lng' => __('backoffice.tournaments.map_location'),
            'eligibility' => __('backoffice.tournaments.eligibility'),
            'registration_method' => __('backoffice.tournaments.registration_method'),

            // Field yang baru wajib 2026-09-18. Tanpa nama tampilannya, galat
            // `required` bawaan Laravel mencetak nama kolomnya mentah —
            // "The dwf id requirement field is required".
            'slug' => __('backoffice.news.field_slug'),
            'prize_amount' => __('backoffice.tournaments.prize_amount'),
            'prize_currency' => __('backoffice.tournaments.prize_currency'),
            'prize_description' => __('backoffice.tournaments.prize_description'),
            'prize_image' => __('backoffice.tournaments.prize_image'),
            'contact_email' => __('backoffice.tournaments.contact_email'),
            'contact_phone' => __('backoffice.tournaments.contact_phone'),
            'registration_starts_on' => __('backoffice.tournaments.registration_starts_on'),
            'registration_ends_on' => __('backoffice.tournaments.registration_ends_on'),
            'dwf_id_requirement' => __('backoffice.tournaments.dwf_id_requirement'),
            'participant_count' => __('backoffice.tournaments.participant_count'),
            'officials' => __('backoffice.tournaments.section_officials'),
            'schedule' => __('backoffice.tournaments.section_schedule'),
            'documents' => __('backoffice.tournaments.documents'),
        ];
    }

    /** Status turnamen dari pilihan Publish Time — dikunci di satu tempat. */
    public function resolvedStatus(): string
    {
        return match ($this->input('posting')) {
            'draft' => Tournament::STATUS_DRAFT,
            'schedule' => Tournament::STATUS_SCHEDULED,
            default => Tournament::STATUS_PUBLISHED,
        };
    }
}
