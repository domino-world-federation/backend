<?php

namespace App\Http\Resources;

use App\Support\Media\StoredFile;
use App\Support\TournamentRules;
use Illuminate\Http\Request;

/**
 * `TournamentDetail` — perluasan `Tournament` untuk halamannya sendiri.
 *
 * Blok yang tidak punya isi DIHILANGKAN, bukan dikirim array kosong: halaman
 * menggambar blok yang ada dan melewati yang tidak, jadi turnamen yang baru
 * diumumkan menghasilkan halaman lebih pendek — bukan halaman penuh judul tanpa
 * isi.
 */
class TournamentDetailResource extends PublicResource
{
    /** @return array<string, mixed> */
    protected function payload(Request $request): array
    {
        $base = (new TournamentResource($this->resource))->toArray($request);

        // `venue` di daftar cuma nama hall; di sini ia MELEBAR jadi objek.
        // Barisnya diganti, bukan digandakan — satu fakta, satu tempat.
        unset($base['venue']);

        return $base + array_filter([
            'summary' => $this->overview,
            'heroImageUrl' => StoredFile::url($this->hero_image_path),
            'heroImageAlt' => $this->name,
            'dateHeading' => $this->dateHeading(),
            'venue' => $this->venue(),
            'prize' => $this->prize(),
            'eligibility' => $this->eligibilityFacts(),
            'schedule' => $this->scheduleEntries->isEmpty() ? null : $this->scheduleEntries
                ->map(fn ($e) => [
                    'id' => (string) $e->id,
                    'time' => $e->time_label,
                    'title' => $e->activity,
                    // Dipisah bullet oleh komponen, bukan disimpan tergabung.
                    'places' => $e->area === null ? [] : [$e->area],
                ])->all(),
            'format' => $this->formatFacts(),
            'regulations' => $this->documents->isEmpty()
                ? null
                : DocumentResource::bare($this->documents),
            'officials' => $this->officials->isEmpty() ? null : $this->officials
                ->map(fn ($o) => array_filter([
                    'id' => (string) $o->id,
                    'name' => $o->name,
                    'role' => $o->role,
                    'country' => $o->country,
                    'portraitUrl' => StoredFile::url($o->photo_path),
                ], static fn ($v) => $v !== null))->all(),
            'contact' => $this->contact(),

            /*
             * Alamat album galeri turnamen ini (`/gallery/{gallerySlug}`), untuk
             * panah "lihat semua" di kolase halaman detail. Sebelumnya panah itu
             * membawa ke `/gallery` — seluruh arsip — dari halaman yang kolasenya
             * justru hanya berisi foto turnamen ini.
             *
             * Dikirim, bukan ditebak situs publik: slug album lahir dari NAMA
             * turnamen (`dubai-grand-masters-domino-series`), bukan dari slug
             * turnamennya (`dubai-masters-2026`).
             *
             * Dihilangkan kalau albumnya tidak ada ATAU belum punya satu pun aset
             * tayang — `/gallery/albums` membuang album kosong, jadi halaman
             * albumnya akan 404. Panah yang menuju 404 lebih buruk daripada
             * panah ke arsip.
             */
            'gallerySlug' => $this->relationLoaded('galleryAlbum') && $this->galleryAlbum?->has_live_items
                ? $this->galleryAlbum->slug
                : null,
            'winners' => $this->winners->isEmpty() ? null : $this->winners
                ->map(fn ($w) => [
                    'id' => (string) $w->id,
                    'rankLabel' => $w->rank_label,
                    'names' => $w->names,
                    'country' => $w->country,
                    'portraitUrls' => collect($w->portrait_paths ?? [])
                        ->map(fn (string $p) => StoredFile::url($p))
                        ->filter()->values()->all(),
                ])->all(),
        ], static fn ($v) => $v !== null);
    }

    /** "OCT 12-16, 2026" — judul emas di bilah info hero, bukan tab kartu. */
    private function dateHeading(): string
    {
        $start = $this->starts_on;
        $end = $this->ends_on;

        if ($start === null || $end === null) {
            return '';
        }

        return strtoupper(
            $start->month === $end->month && $start->year === $end->year
                ? $start->format('M j').'-'.$end->format('j, Y')
                : $start->format('M j').' - '.$end->format('M j, Y'),
        );
    }

    /** @return array<string, mixed>|null */
    private function venue(): ?array
    {
        if (blank($this->venue_name)) {
            return null;
        }

        return array_filter([
            'name' => $this->venue_name,
            'address' => $this->venue_address,
            'country' => $this->country,
            'coordinates' => [
                'lat' => (float) $this->venue_lat,
                'lng' => (float) $this->venue_lng,
            ],
            'imageUrl' => StoredFile::url($this->hero_image_path),
            'imageAlt' => $this->venue_name,
        ], static fn ($v) => $v !== null);
    }

    /**
     * Blok hadiah, atau `null` kalau turnamennya tanpa hadiah.
     *
     * Bentuknya SAMA untuk hadiah tunai dan barang — yang berbeda hanya kalimat
     * di `headline`. Situs publik mencetak `headline` apa adanya, jadi ia tidak
     * perlu tahu ada dua jenis hadiah, dan kontrak API tidak berubah saat jenis
     * "Physical Item" ditambahkan (`700:10891`).
     *
     *   cash — "USD 50.000 Prize pool", dirangkai DI SINI: satu headline di
     *          desain, bukan angka dan mata uang terpisah.
     *   item — nama barangnya apa adanya ("Handphone").
     *   none — `null`, dan blok hadiahnya tidak digambar.
     *
     * @return array<string, mixed>|null
     */
    private function prize(): ?array
    {
        $headline = match ($this->prize_type) {
            'cash' => $this->prize_amount === null
                ? null
                : trim($this->prize_currency.' '.number_format((float) $this->prize_amount, 0, ',', '.').' Prize pool'),
            'item' => $this->prize_name,
            default => null,
        };

        // Baris lama yang jenisnya cash tapi nominalnya kosong — atau item
        // tanpa nama — tidak punya apa pun untuk dijadikan judul. Lebih baik
        // tanpa blok daripada blok hadiah dengan judul kosong.
        if (blank($headline)) {
            return null;
        }

        return array_filter([
            'headline' => $headline,
            'note' => $this->prize_description,
            'imageUrl' => StoredFile::url($this->prize_image_path),
            // Keterangan kalau ada, judulnya kalau tidak — keterangan kini
            // opsional untuk kedua jenis, dan gambar tanpa alt adalah gambar
            // yang tidak ada bagi pembaca layar.
            'imageAlt' => $this->prize_description ?? $headline,
        ], static fn ($v) => $v !== null);
    }

    /**
     * Blok "Eligibility & Registration" — pasangan label dan nilai.
     *
     * Kolom di database, pasangan di API: desainnya menggambar empat kartu
     * berbentuk sama, dan halaman itu tidak perlu tahu nama kolom kami.
     *
     * @return array<int, array<string, string>>|null
     */
    private function eligibilityFacts(): ?array
    {
        $facts = array_filter([
            'Registration period' => $this->registrationPeriod(),
            'Eligibility' => $this->eligibility,
            'Registration method' => $this->registration_method,
            'DWF ID' => $this->dwf_id_requirement,
        ]);

        return $facts === [] ? null : $this->pairs($facts);
    }

    /** @return array<int, array<string, string>>|null */
    private function formatFacts(): ?array
    {
        $facts = array_filter([
            'Game format' => TournamentRules::formatLabel($this->tournament_mode, $this->domino_rules),
            'Participants' => $this->participant_count === null
                ? null
                : "{$this->participant_count} {$this->participant_type}",
            'Competition system' => $this->competition_system,
            'Scoring' => $this->scoring,
        ]);

        return $facts === [] ? null : $this->pairs($facts);
    }

    /** @param array<string, string> $facts @return array<int, array<string, string>> */
    private function pairs(array $facts): array
    {
        $out = [];
        $i = 0;

        foreach ($facts as $label => $value) {
            $out[] = ['id' => (string) ++$i, 'label' => $label, 'value' => $value];
        }

        return $out;
    }

    private function registrationPeriod(): ?string
    {
        $start = $this->registration_starts_on;
        $end = $this->registration_ends_on;

        if ($start === null || $end === null) {
            return null;
        }

        return $start->format('M d').'–'.$end->format('M d, Y');
    }

    /** @return array<string, string>|null */
    private function contact(): ?array
    {
        if (blank($this->contact_email) && blank($this->contact_phone)) {
            return null;
        }

        return array_filter([
            'email' => $this->contact_email,
            'phone' => $this->contact_phone,
        ], static fn ($v) => $v !== null);
    }
}
