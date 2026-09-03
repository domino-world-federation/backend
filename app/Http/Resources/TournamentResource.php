<?php

namespace App\Http\Resources;

use App\Support\Media\StoredFile;
use Illuminate\Http\Request;

/**
 * `Tournament` — bentuk kartu di rail `/tournaments`.
 *
 * ── Resource ini yang menanggung aturan §5.1: API yang memutuskan cara sebuah
 * nilai DIBACA. ──
 *
 * Empat field di bawah sudah berupa teks siap tampil, bukan data mentah:
 * `dateLabel`, `registrationLabel`, `location`, dan `formatLabel`. Alasannya
 * ditulis di `types.ts` dan bukan soal selera — halaman yang menghitung
 * "in 3 days" dari timestamp akan terus menulis "3 days" sampai minggu
 * berikutnya begitu responsnya masuk edge cache.
 *
 * Konsekuensinya untuk yang men-cache: `registrationLabel` dihitung SAAT
 * REQUEST dan tidak boleh disimpan lebih lama dari sehari (risiko K3 di PRD).
 */
class TournamentResource extends PublicResource
{
    /** @return array<string, mixed> */
    protected function payload(Request $request): array
    {
        return [
            'id' => $this->idString(),
            'slug' => $this->slug,
            'name' => $this->name,
            'category' => $this->coverage,
            'status' => $this->stage,
            'registration' => $this->registration_state,
            'location' => $this->location,
            'imageUrl' => StoredFile::url($this->hero_image_path),
            'imageAlt' => $this->name,
            'dateLabel' => $this->dateLabel(),
            'registrationLabel' => $this->registrationLabel(),
            'attendance' => $this->attendance,
            'formatLabel' => $this->game_format,
            'startsAt' => $this->starts_on?->toIso8601String(),
            'endsAt' => $this->ends_on?->toIso8601String(),
            'venue' => $this->venue_name,
            'country' => $this->country,
            'href' => "/tournaments/{$this->slug}",
        ];
    }

    /**
     * "Mar 18 - 21, 2027" — dan "Dec 30, 2026 - Jan 2, 2027" saat melintasi
     * bulan atau tahun.
     *
     * Ditulis di sini, bukan di halaman: tab kartunya selebar 360px dan
     * keputusan "bulan disebut sekali atau dua kali" harus sama di setiap
     * tempat yang mencetaknya.
     */
    private function dateLabel(): string
    {
        $start = $this->starts_on;
        $end = $this->ends_on;

        if ($start === null || $end === null) {
            return '';
        }

        if ($start->isSameDay($end)) {
            return $start->format('M j, Y');
        }

        if ($start->year !== $end->year) {
            return $start->format('M j, Y').' - '.$end->format('M j, Y');
        }

        return $start->month === $end->month
            ? $start->format('M j').' - '.$end->format('j, Y')
            : $start->format('M j').' - '.$end->format('M j, Y');
    }

    /**
     * Tab di tepi bawah gambar, dan ia HARUS sepakat dengan `registration`.
     *
     * Pil mencetak keadaannya, tab ini mencetak rinciannya, dan keduanya duduk
     * berjarak satu inci. Turnamen yang sedang berlangsung sengaja mengembalikan
     * `null` — kartunya lalu membuang tabnya alih-alih mencetak tenggat untuk
     * pendaftaran yang sudah tidak menerima siapa pun.
     */
    private function registrationLabel(): ?string
    {
        return match ($this->registration_state) {
            'open' => $this->closingLabel(),
            'upcoming' => 'Registration opens '.$this->registration_starts_on?->format('M j'),
            'closed' => 'Registration closed',
            default => null,
        };
    }

    private function closingLabel(): string
    {
        $end = $this->registration_ends_on;

        if ($end === null) {
            return 'Registration open';
        }

        $days = (int) now()->startOfDay()->diffInDays($end, absolute: false);

        return match (true) {
            $days <= 0 => 'Registration ends today',
            $days === 1 => 'Registration ends tomorrow',
            $days <= 14 => "Registration ends in {$days} days",
            default => 'Registration ends '.$end->format('M j'),
        };
    }
}
