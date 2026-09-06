<?php

namespace App\Support;

/**
 * Aturan main turnamen, dan segala yang mengikutinya.
 *
 * Sampai 2026-09-06 "Tournament Rules Format" cuma sebuah string di daftar, dan
 * tiga hal yang sebenarnya ditentukan olehnya diketik tangan per turnamen:
 * jenis peserta, kalimat penilaian, dan kalimat sistem kompetisi. Hasilnya
 * turnamen dengan aturan yang sama membawa kalimat yang berbeda-beda susunannya,
 * dan tidak ada satu tempat pun untuk membetulkannya sekaligus.
 *
 * Sekarang ketiganya diturunkan dari aturannya. Yang tersimpan di baris turnamen
 * tetap kalimat jadinya — bukan kuncinya — supaya `/api/v1/tournaments/{slug}`
 * tidak berubah bentuk dan halaman publik tidak perlu tahu apa pun tentang ini.
 */
final class TournamentRules
{
    /**
     * Nama aturan yang boleh disimpan.
     *
     * @return array<int, string>
     */
    public static function names(): array
    {
        return array_keys(config('dwf.tournaments.rules_formats'));
    }

    /**
     * Bentuk untuk layar: nama, sisi, pilihan jumlah peserta, dan label kolomnya.
     *
     * Dikirim UTUH ke formulir, bukan diminta ulang tiap kali pilihannya
     * berubah: enam aturan dengan empat field pendek tidak sebanding dengan satu
     * perjalanan ke server per klik, dan label yang berubah beberapa ratus
     * milidetik setelah pilihannya terbaca sebagai layar yang tersendat.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function options(): array
    {
        return collect(config('dwf.tournaments.rules_formats'))
            ->map(fn (array $rule, string $name) => [
                'value' => $name,
                'label' => $name,
                'side' => $rule['side'],
                'participantType' => self::participantType($name),
                'counts' => self::countsFor($name),

                // Naskah MENTAH, `$n` belum diisi: layar mengisinya sendiri
                // supaya kalimatnya berubah saat jumlah pesertanya diubah,
                // tanpa perjalanan ke server. Yang disimpan tetap yang dirender
                // di sini, di controller.
                'scoring' => $rule['scoring'],
                'competitionSystem' => $rule['competition_system'],
            ])
            ->values()
            ->all();
    }

    /** `single` atau `double`, atau null kalau aturannya tidak dikenal. */
    public static function side(?string $name): ?string
    {
        return config("dwf.tournaments.rules_formats.{$name}.side");
    }

    /**
     * Jumlah peserta yang boleh dipilih untuk aturan ini.
     *
     * @return array<int, int>
     */
    public static function countsFor(?string $name): array
    {
        $side = self::side($name);

        return $side === null ? [] : config("dwf.tournaments.participant_counts.{$side}", []);
    }

    /** "Players" atau "Teams" — label peserta, bukan lagi pilihan di layar. */
    public static function participantType(?string $name): ?string
    {
        $side = self::side($name);

        return $side === null ? null : config("dwf.tournaments.participant_types.{$side}");
    }

    public static function scoringFor(?string $name): ?string
    {
        return config("dwf.tournaments.rules_formats.{$name}.scoring");
    }

    /** Kalimat sistem kompetisi, dengan `$n` sudah diisi jumlah pesertanya. */
    public static function competitionSystemFor(?string $name, ?int $participants): ?string
    {
        $template = config("dwf.tournaments.rules_formats.{$name}.competition_system");

        return $template === null ? null : self::render($template, $participants);
    }

    /**
     * Mengisi `$n` dan menghitung `($n / k)`.
     *
     * **Bukan `eval`, dan bukan pola umum.** Yang dihasilkan di sini tercetak di
     * halaman publik; hanya dua bentuk yang dikenali, dan apa pun selain itu
     * dibiarkan apa adanya alih-alih dijalankan.
     *
     * Pembagiannya dibulatkan ke atas supaya babak dengan peserta ganjil — yang
     * tidak bisa terjadi dengan daftar tertutup `participant_counts`, tapi bisa
     * pada baris lama — tetap menyebut jumlah pertandingan yang cukup untuk
     * semuanya, bukan satu yang kurang.
     *
     * Tanpa jumlah peserta, `$n` dibiarkan berdiri: kalimat yang berbunyi
     * "$n-team bracket" jelas belum selesai, sedangkan kalimat yang menghapus
     * angkanya terbaca seperti kalimat utuh yang salah.
     */
    public static function render(string $template, ?int $participants): string
    {
        if ($participants === null) {
            return $template;
        }

        $filled = preg_replace_callback(
            '/\(\$n\s*\/\s*(\d+)\)/',
            fn (array $m) => (string) (int) ceil($participants / max(1, (int) $m[1])),
            $template,
        );

        return str_replace('$n', (string) $participants, $filled);
    }
}
