<?php

namespace App\Http\Requests\Cms;

use App\Models\Document;
use App\Models\Tournament;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validasi formulir Add Tournament (`585:11241`).
 *
 * Tiap baris di bawah punya pasangannya di desain: teks kecil di bawah field
 * ("Required • 2–120 characters.") adalah kontrak, bukan hiasan, dan angkanya
 * disalin apa adanya.
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
            'slug' => [
                'nullable', 'string', 'max:180', 'regex:/^[a-z0-9-]+$/',
                Rule::unique('tournaments', 'slug')->ignore($tournament?->id),
            ],
            'coverage' => ['required', Rule::in($options['coverage'])],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'city' => ['required', 'string', 'max:120'],
            'country' => ['required', 'string', 'max:120'],
            'rules_format' => ['required', Rule::in($options['rules_formats'])],
            'attendance' => ['required', Rule::in($options['attendance'])],

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

            // --- Prize Information (opsional) ---
            'prize_amount' => ['nullable', 'numeric', 'min:0'],
            'prize_currency' => ['nullable', Rule::in($options['currencies'])],
            'prize_description' => ['nullable', 'string', 'max:240'],
            'prize_image' => ['nullable', ...$image],

            // --- Tournament Contact (opsional) ---
            'contact_email' => ['nullable', 'email', 'max:160'],
            'contact_phone' => ['nullable', 'string', 'max:40'],

            // --- Officials & Referees (berulang) ---
            'officials' => ['array', 'max:50'],

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
            'officials.*.photo' => ['nullable', ...$image],

            // --- Eligibility & Registration ---
            'registration_starts_on' => ['nullable', 'date'],
            'registration_ends_on' => ['nullable', 'date'],
            'dwf_id_requirement' => ['nullable', Rule::in($options['dwf_id_requirements'])],
            'eligibility' => ['required', Rule::in($options['eligibility'])],
            'registration_method' => ['required', Rule::in($options['registration_methods'])],

            // --- Schedule (berulang) ---
            'schedule' => ['array', 'max:200'],
            'schedule.*.held_on' => ['required', 'date'],
            'schedule.*.starts_at' => ['required', 'date_format:H:i'],
            'schedule.*.activity' => ['required', 'string', 'min:3', 'max:120'],
            'schedule.*.area' => ['nullable', 'string', 'max:120'],

            // --- Tournament Format ---
            'game_format' => ['required', 'string', 'min:2', 'max:80'],
            'participant_count' => ['nullable', 'integer', 'min:1'],
            'participant_type' => ['required', Rule::in($options['participant_types'])],
            'competition_system' => ['required', 'string', 'min:10', 'max:500'],
            'scoring' => ['required', 'string', 'min:10', 'max:500'],

            // --- Regulations & Rules ---
            // "select up to 10 existing published documents".
            'documents' => ['array', 'max:'.$options['max_documents']],
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
            $this->checkCurrencyAccompaniesAmount($validator);
            $this->checkDocumentsArePublished($validator);
        });
    }

    /**
     * "if provided, Registration End Date is also required" dan "must be on or
     * after Registration Start Date and before tournament start" (`596:11304`).
     *
     * Ketiganya diperiksa di sini, bukan lewat `after_or_equal` biasa, karena
     * keduanya opsional: aturan bawaan akan diam saja kalau salah satu kosong,
     * dan yang lolos adalah jendela pendaftaran separuh jadi.
     */
    private function checkRegistrationWindow(Validator $validator): void
    {
        $start = $this->input('registration_starts_on');
        $end = $this->input('registration_ends_on');

        if (filled($start) && blank($end)) {
            $validator->errors()->add('registration_ends_on', __('backoffice.tournaments.registration_end_required'));

            return;
        }

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

    /** "required when Prize Pool Amount is filled" (`596:11158`). */
    private function checkCurrencyAccompaniesAmount(Validator $validator): void
    {
        if (filled($this->input('prize_amount')) && blank($this->input('prize_currency'))) {
            $validator->errors()->add('prize_currency', __('backoffice.tournaments.currency_required'));
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
            'game_format' => __('backoffice.tournaments.game_format'),
            'participant_type' => __('backoffice.tournaments.participant_type'),
            'competition_system' => __('backoffice.tournaments.competition_system'),
            'scoring' => __('backoffice.tournaments.scoring'),
            'eligibility' => __('backoffice.tournaments.eligibility'),
            'registration_method' => __('backoffice.tournaments.registration_method'),
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
