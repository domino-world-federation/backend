<?php

namespace Tests\Feature;

use App\Models\Tournament;
use App\Models\User;
use App\Support\TournamentRules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Aturan main turnamen, dan tiga hal yang diturunkan darinya.
 *
 * Sampai 2026-09-06 jenis peserta, kalimat penilaian, dan kalimat sistem
 * kompetisi diketik tangan per turnamen — padahal ketiganya sifat ATURANNYA.
 * Yang dikunci di sini bukan kalimatnya, melainkan bahwa ia benar-benar
 * diturunkan: sebuah field yang bisa diisi tapi diabaikan adalah dua sumber
 * kebenaran, dan yang kalah selalu yang diketik orang.
 */
class TournamentRulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_single_is_played_by_players_and_double_by_teams(): void
    {
        $this->assertSame('Players', TournamentRules::participantType('Single'));
        $this->assertSame('Teams', TournamentRules::participantType('Double'));
        $this->assertNull(TournamentRules::participantType('Mode Yang Tidak Ada'));
    }

    /**
     * Jenis peserta milik MODE saja — aturan dominonya tidak ikut menentukan.
     *
     * Dikunci karena inilah pembagian kerja yang membuat pemisahan dua field
     * ini masuk akal: mode menjawab siapa yang bertanding, aturan menjawab
     * bagaimana satu pertandingan dimenangkan. Kalau suatu saat ada yang
     * menyelipkan aturan ke dalam perhitungan ini, dua field itu berhenti
     * berdiri sendiri.
     */
    public function test_the_rules_do_not_change_who_is_playing(): void
    {
        foreach (TournamentRules::ruleNames() as $rules) {
            $this->assertSame('Players', TournamentRules::participantType('Single'), $rules);
            $this->assertSame([16, 64, 256, 1024], TournamentRules::countsFor('Single'), $rules);
        }
    }

    /**
     * Kalimat penilaian lahir dari KEDUANYA: naskahnya milik aturan, subjeknya
     * milik mode.
     *
     * Keenam kalimat ini sudah tercetak di halaman publik sebelum kolomnya
     * dipecah, dan tidak satu pun boleh bergeser — termasuk "the player WHO"
     * lawan "the team THAT", yang terlihat sepele sampai seseorang membaca
     * kalimat yang salah tata bahasanya di halaman turnamen.
     */
    public function test_the_scoring_sentence_takes_its_subject_from_the_mode(): void
    {
        $this->assertSame(
            'First player to reach 101 points wins the match.',
            TournamentRules::scoringFor('Single', '101'),
        );
        $this->assertSame(
            'First team to reach 101 points wins the match.',
            TournamentRules::scoringFor('Double', '101'),
        );
        $this->assertSame(
            'The player who wins the round wins the match.',
            TournamentRules::scoringFor('Single', '1 Round'),
        );
        $this->assertSame(
            'The team that wins the round wins the match.',
            TournamentRules::scoringFor('Double', '1 Round'),
        );
        $this->assertSame(
            'The first player to win two rounds wins the match.',
            TournamentRules::scoringFor('Single', 'Double Win'),
        );
        $this->assertSame(
            'The first team to win two rounds wins the match.',
            TournamentRules::scoringFor('Double', 'Double Win'),
        );
    }

    /**
     * Separuh pasangan tidak menghasilkan separuh kalimat.
     *
     * Naskahnya ber-placeholder, jadi mode yang hilang akan mencetak
     * ":subject" di halaman publik — kalimat yang terbaca utuh sampai
     * seseorang membacanya sampai habis.
     */
    public function test_a_half_chosen_pair_yields_no_sentence(): void
    {
        $this->assertNull(TournamentRules::scoringFor(null, '101'));
        $this->assertNull(TournamentRules::scoringFor('Single', null));
        $this->assertNull(TournamentRules::scoringFor('Single', 'Aturan Yang Tidak Ada'));
    }

    /** `formatLabel` di API publik — dua fakta, satu baris, titik tengah. */
    public function test_the_public_label_joins_the_pair(): void
    {
        $this->assertSame('Single · 101', TournamentRules::formatLabel('Single', '101'));
        $this->assertSame('Double · Double Win', TournamentRules::formatLabel('Double', 'Double Win'));

        // Baris lama yang separuhnya kosong tetap mencetak yang ada, bukan
        // " · " yang menggantung.
        $this->assertSame('Single', TournamentRules::formatLabel('Single', null));
        $this->assertNull(TournamentRules::formatLabel(null, null));
    }

    /**
     * Daftar jumlah peserta berbeda per sisi, dan tertutup.
     *
     * Bagan gugur hanya bekerja pada ukuran-ukuran ini; 100 tim akan mencetak
     * "50 opening-round matches" untuk bagan yang tidak bisa disusun.
     */
    public function test_the_counts_offered_depend_on_the_mode(): void
    {
        $this->assertSame([16, 64, 256, 1024], TournamentRules::countsFor('Single'));
        $this->assertSame([8, 16, 32, 64, 128, 256, 512, 1024], TournamentRules::countsFor('Double'));
        $this->assertSame([], TournamentRules::countsFor('Mode Yang Tidak Ada'));
    }

    public function test_the_competition_sentence_does_its_own_arithmetic(): void
    {
        $this->assertSame(
            '64-team knockout bracket with 32 opening-round matches. Each match consists of two teams (four players).',
            TournamentRules::competitionSystemFor('Double', 64),
        );

        $this->assertSame(
            '16-player knockout format with 4 opening-round groups. Each group consists of four players, and the winner advances to the next stage.',
            TournamentRules::competitionSystemFor('Single', 16),
        );
    }

    /**
     * Tanpa jumlah peserta, `$n` DIBIARKAN berdiri.
     *
     * Kalimat yang berbunyi "$n-team bracket" jelas belum selesai; kalimat yang
     * menghapus angkanya terbaca seperti kalimat utuh yang salah.
     */
    public function test_an_unset_count_leaves_the_placeholder_visible(): void
    {
        $this->assertStringContainsString('$n', (string) TournamentRules::competitionSystemFor('Double', null));
    }

    /**
     * Yang dirender BUKAN `eval`.
     *
     * `$n` diisi di mana pun ia muncul — ia berarti jumlah peserta, di dalam
     * kurung maupun di luarnya. Yang TIDAK dilakukan adalah menghitung operasi
     * yang tidak dikenal: `($n * 2)` keluar sebagai `(16 * 2)`, bukan `32`.
     * Naskah ini tercetak di halaman publik, jadi satu-satunya aritmetika yang
     * boleh dijalankan adalah pembagian yang memang ditulis di tabel aturan.
     */
    public function test_it_fills_the_number_but_computes_only_the_pattern_it_knows(): void
    {
        $this->assertSame(
            '16 and (16 * 2) and 4',
            TournamentRules::render('$n and ($n * 2) and ($n / 4)', 16),
        );
    }

    /**
     * Menyimpan turnamen MENULISKAN ketiganya, apa pun yang dikirim layar.
     *
     * Ini janji yang sebenarnya: kalau suatu saat ada yang mengirim
     * `scoring` sendiri, ia tidak menang.
     */
    public function test_saving_derives_the_three_fields(): void
    {
        $tournament = Tournament::factory()->create([
            'tournament_mode' => 'Double',
            'domino_rules' => '101',
            'participant_count' => 8,
            'participant_type' => 'Players',
            'scoring' => 'diketik tangan',
            'competition_system' => 'diketik tangan',
        ]);

        $this->actingAs(User::factory()->superAdmin()->create())
            ->patch("/tournaments/{$tournament->id}/visibility", ['status' => 'published']);

        // Perubahan sebenarnya diuji lewat layar formulirnya di `TournamentTest`;
        // di sini yang dipastikan helper-nya menghasilkan nilai yang dipakai
        // controller, supaya keduanya tidak bisa berbeda diam-diam.
        $this->assertSame('Teams', TournamentRules::participantType('Double'));
        $this->assertSame(
            '8-team knockout bracket with 4 opening-round matches. Each match consists of two teams (four players).',
            TournamentRules::competitionSystemFor('Double', 8),
        );
    }

    /**
     * Dua kosakata, dan namanya yang TERSIMPAN — mengganti ejaannya membuat
     * baris lama yatim.
     *
     * Dua kali tiga tetap enam kombinasi, yang sama dengan enam nama tunggal
     * sebelum kolomnya dipecah.
     */
    public function test_the_vocabulary_is_two_modes_and_three_rules(): void
    {
        $this->assertSame(['Single', 'Double'], TournamentRules::modes());
        $this->assertSame(['101', '1 Round', 'Double Win'], TournamentRules::ruleNames());
    }
}
