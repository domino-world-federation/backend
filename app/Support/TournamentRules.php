<?php

namespace App\Support;

/**
 * Aturan main turnamen, dan segala yang mengikutinya.
 *
 * Sampai 2026-09-06 "Tournament Rules Format" cuma sebuah string di daftar, dan
 * tiga hal yang sebenarnya ditentukan olehnya diketik tangan per turnamen:
 * jenis peserta, kalimat penilaian, dan kalimat sistem kompetisi. Hasilnya
 * turnamen dengan aturan yang sama membawa kalimat yang berbeda-beda susunannya,
 * dan tidak ada satu tempat pun untuk membetulkannya sekaligus. Sejak itu
 * ketiganya diturunkan dari aturannya.
 *
 * Sejak 2026-09-23 aturannya sendiri DUA field, bukan satu: mode turnamen
 * (Single/Double) dan aturan domino (101 / 1 Round / Double Win). Enam nama
 * lama adalah hasil silang keduanya, jadi yang berubah cuma cara menanyakannya
 * — kombinasinya tetap enam yang sama.
 *
 * Pembagian kerjanya, dan ia bukan sembarang:
 *
 *   - **Mode** menentukan siapa yang bertanding — sisi, jenis peserta, jumlah
 *     peserta yang boleh dipilih, dan kalimat sistem kompetisi.
 *   - **Aturan** menentukan bagaimana satu pertandingan dimenangkan, dan itu
 *     satu kalimat saja: penilaian.
 *   - Kalimat penilaian tetap butuh KEDUANYA, karena subjeknya datang dari
 *     mode: "First player to reach 101" dan "First team to reach 101" adalah
 *     kalimat yang sama dengan subjek berbeda.
 */
final class TournamentRules
{
    /**
     * Nama mode yang boleh disimpan.
     *
     * @return array<int, string>
     */
    public static function modes(): array
    {
        return array_keys(config('dwf.tournaments.tournament_modes'));
    }

    /**
     * Nama aturan domino yang boleh disimpan.
     *
     * **`strval` bukan hiasan.** PHP mengubah kunci array yang berupa angka
     * menjadi INTEGER, jadi `'101' => [...]` di config lahir kembali sebagai
     * `101`. Tanpa pengecoran ini, nilai yang dikirim ke `SelectField` adalah
     * `101` sementara yang tersimpan di kolomnya `'101'` — dan `SelectField`
     * membandingkan dengan `===`, jadi membuka turnamen beraturan 101 akan
     * menampilkan dropdown KOSONG. Tidak ada galat di mana pun; yang terjadi
     * cuma simpan berikutnya menulis `null` ke kolom yang tadinya benar.
     *
     * @return array<int, string>
     */
    public static function ruleNames(): array
    {
        return array_map('strval', array_keys(config('dwf.tournaments.domino_rules')));
    }

    /**
     * Mode untuk layar: nama, sisi, pilihan jumlah peserta, dan naskah yang
     * dirangkai formulir sendiri.
     *
     * Dikirim UTUH ke formulir, bukan diminta ulang tiap kali pilihannya
     * berubah: dua mode dan tiga aturan dengan beberapa field pendek tidak
     * sebanding dengan satu perjalanan ke server per klik, dan kalimat yang
     * berubah beberapa ratus milidetik setelah pilihannya terbaca sebagai layar
     * yang tersendat.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function modeOptions(): array
    {
        return collect(config('dwf.tournaments.tournament_modes'))
            ->map(fn (array $mode, string $name) => [
                'value' => $name,
                'label' => $name,
                'side' => $mode['side'],
                'participantType' => self::participantType($name),
                'counts' => self::countsFor($name),

                // Dipakai formulir untuk merangkai kalimat penilaian bersama
                // naskah milik aturannya — lihat `scoringFor()`, yang melakukan
                // hal yang sama di server.
                'subject' => $mode['subject'],
                'relative' => $mode['relative'],

                // Naskah MENTAH, `$n` belum diisi: layar mengisinya sendiri
                // supaya kalimatnya berubah saat jumlah pesertanya diubah,
                // tanpa perjalanan ke server. Yang disimpan tetap yang dirender
                // di sini, di controller.
                'competitionSystem' => $mode['competition_system'],
            ])
            ->values()
            ->all();
    }

    /**
     * Aturan domino untuk layar. Naskah penilaiannya masih ber-placeholder;
     * subjeknya milik mode, dan mode dipilih di kotak sebelahnya.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function ruleOptions(): array
    {
        return collect(config('dwf.tournaments.domino_rules'))
            // `$name` di-hint `int|string`, bukan `string`: lihat `ruleNames()`
            // — kunci `101` tiba sebagai integer, dan type hint `string` akan
            // melempar TypeError begitu aturan bernama angka ditambahkan.
            ->map(fn (array $rule, int|string $name) => [
                'value' => (string) $name,
                'label' => (string) $name,
                'scoring' => $rule['scoring'],
            ])
            ->values()
            ->all();
    }

    /** `single` atau `double`, atau null kalau modenya tidak dikenal. */
    public static function side(?string $mode): ?string
    {
        return config("dwf.tournaments.tournament_modes.{$mode}.side");
    }

    /**
     * Jumlah peserta yang boleh dipilih untuk mode ini.
     *
     * @return array<int, int>
     */
    public static function countsFor(?string $mode): array
    {
        $side = self::side($mode);

        return $side === null ? [] : config("dwf.tournaments.participant_counts.{$side}", []);
    }

    /** "Players" atau "Teams" — label peserta, bukan lagi pilihan di layar. */
    public static function participantType(?string $mode): ?string
    {
        $side = self::side($mode);

        return $side === null ? null : config("dwf.tournaments.participant_types.{$side}");
    }

    /**
     * Kalimat penilaian — naskah milik ATURAN, subjek milik MODE.
     *
     * Null kalau salah satunya tidak dikenal: kalimat setengah jadi yang
     * menyebut ":subject" lebih buruk daripada kolom kosong, karena ia terbaca
     * seperti kalimat utuh sampai seseorang membacanya sampai habis.
     */
    public static function scoringFor(?string $mode, ?string $rules): ?string
    {
        $template = config("dwf.tournaments.domino_rules.{$rules}.scoring");
        $modeConfig = config("dwf.tournaments.tournament_modes.{$mode}");

        if ($template === null || $modeConfig === null) {
            return null;
        }

        return strtr($template, [
            ':subject' => $modeConfig['subject'],
            ':relative' => $modeConfig['relative'],
        ]);
    }

    /** Kalimat sistem kompetisi, dengan `$n` sudah diisi jumlah pesertanya. */
    public static function competitionSystemFor(?string $mode, ?int $participants): ?string
    {
        $template = config("dwf.tournaments.tournament_modes.{$mode}.competition_system");

        return $template === null ? null : self::render($template, $participants);
    }

    /**
     * Satu baris siap cetak untuk `formatLabel` di API publik — "Single · 101".
     *
     * Titik tengah, bukan spasi. Dirangkai dengan spasi, "Single Double Win"
     * terbaca sebagai satu nama aturan yang tidak ada; pemisahnya yang
     * memberitahu pembaca bahwa ini dua fakta. Menggantikan `rules_format`
     * mentah yang dikirim sebelum kolomnya dipecah, dan bentuknya sengaja tetap
     * satu string: situs publik mencetaknya apa adanya dan tidak pernah
     * mengurainya.
     */
    public static function formatLabel(?string $mode, ?string $rules): ?string
    {
        $parts = array_values(array_filter([$mode, $rules], fn (?string $v) => filled($v)));

        return $parts === [] ? null : implode(' · ', $parts);
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
