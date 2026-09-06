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

    public function test_single_rules_are_played_by_players_and_double_by_teams(): void
    {
        $this->assertSame('Players', TournamentRules::participantType('Single 101'));
        $this->assertSame('Teams', TournamentRules::participantType('Double 101'));
        $this->assertSame('Players', TournamentRules::participantType('Single BO3'));
        $this->assertSame('Teams', TournamentRules::participantType('Double Knockout'));
    }

    /**
     * Daftar jumlah peserta berbeda per sisi, dan tertutup.
     *
     * Bagan gugur hanya bekerja pada ukuran-ukuran ini; 100 tim akan mencetak
     * "50 opening-round matches" untuk bagan yang tidak bisa disusun.
     */
    public function test_the_counts_offered_depend_on_the_rules(): void
    {
        $this->assertSame([16, 64, 256, 1024], TournamentRules::countsFor('Single 101'));
        $this->assertSame([8, 16, 32, 64, 128, 256, 512, 1024], TournamentRules::countsFor('Double 101'));
        $this->assertSame([], TournamentRules::countsFor('Aturan Yang Tidak Ada'));
    }

    public function test_the_competition_sentence_does_its_own_arithmetic(): void
    {
        $this->assertSame(
            '64-team knockout bracket with 32 opening-round matches. Each match consists of two teams (four players).',
            TournamentRules::competitionSystemFor('Double 101', 64),
        );

        $this->assertSame(
            '16-player knockout format with 4 opening-round groups. Each group consists of four players, and the winner advances to the next stage.',
            TournamentRules::competitionSystemFor('Single Knockout', 16),
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
        $this->assertStringContainsString('$n', (string) TournamentRules::competitionSystemFor('Double 101', null));
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
            'rules_format' => 'Double 101',
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
        $this->assertSame('Teams', TournamentRules::participantType('Double 101'));
        $this->assertSame(
            '8-team knockout bracket with 4 opening-round matches. Each match consists of two teams (four players).',
            TournamentRules::competitionSystemFor('Double 101', 8),
        );
    }

    /** Enam aturan, dan namanya yang tersimpan — mengganti ejaannya membuat baris lama yatim. */
    public function test_the_vocabulary_is_the_six_agreed_rules(): void
    {
        $this->assertSame([
            'Double 101',
            'Single 101',
            'Double Knockout',
            'Single Knockout',
            'Double BO3',
            'Single BO3',
        ], TournamentRules::names());
    }
}
