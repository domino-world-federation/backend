<?php

namespace Tests\Feature\Cms;

use App\Models\Champion;
use App\Models\OlympicResult;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Results & Winners — menu terpisah yang dijanjikan `596:11483`.
 *
 * Yang dikunci di sini: turnamen yang belum selesai tidak boleh punya hasil,
 * potret pemenang dipertahankan lewat id barisnya (bukan path dari klien), dan
 * tahun Olympic tetap teks.
 */
class ResultTest extends TestCase
{
    use RefreshDatabase;

    private function actor(): User
    {
        return User::factory()->superAdmin()->create();
    }

    /** @param array<string, mixed> $overrides @return array<string, mixed> */
    private function winner(array $overrides = []): array
    {
        return array_merge([
            'rank_label' => 'CHAMPION',
            'names' => 'Luis Ortega & Mateo Ruiz',
            'country' => 'Spain',
        ], $overrides);
    }

    // ------------------------------------------------ pemenang per turnamen

    /**
     * Hanya turnamen SELESAI yang muncul di daftar.
     *
     * Menawarkan formulir hasil untuk pertandingan yang belum dimainkan
     * mengundang orang mengarang angka.
     */
    public function test_only_completed_tournaments_are_listed(): void
    {
        Tournament::factory()->completed()->create(['name' => 'sudah selesai']);
        Tournament::factory()->create(['name' => 'belum mulai']);
        Tournament::factory()->live()->create(['name' => 'sedang jalan']);

        $this->actingAs($this->actor())
            ->get('/results')
            ->assertOk()
            ->assertSee('sudah selesai')
            ->assertDontSee('belum mulai')
            ->assertDontSee('sedang jalan');
    }

    public function test_an_unfinished_tournament_refuses_its_results_screen(): void
    {
        $tournament = Tournament::factory()->live()->create();

        $this->actingAs($this->actor())
            ->get("/results/{$tournament->id}")
            ->assertSessionHasErrors('tournament');
    }

    public function test_winners_are_saved_in_the_order_they_are_listed(): void
    {
        $tournament = Tournament::factory()->completed()->create();

        $this->actingAs($this->actor())->post("/results/{$tournament->id}", [
            'winners' => [
                $this->winner(),
                $this->winner(['rank_label' => 'RUNNER-UP', 'names' => 'Ana Silva', 'country' => 'Brazil']),
            ],
        ])->assertSessionHasNoErrors();

        $winners = $tournament->fresh()->winners;

        $this->assertCount(2, $winners);
        $this->assertSame(['CHAMPION', 'RUNNER-UP'], $winners->pluck('rank_label')->all());
        $this->assertSame([1, 2], $winners->pluck('position')->all());
    }

    /** Menyimpan menulis ULANG: baris yang dibuang benar-benar hilang. */
    public function test_saving_replaces_the_previous_winners(): void
    {
        $tournament = Tournament::factory()->completed()->create();
        $actor = $this->actor();

        $this->actingAs($actor)->post("/results/{$tournament->id}", [
            'winners' => [$this->winner(), $this->winner(['rank_label' => 'RUNNER-UP'])],
        ]);

        $this->actingAs($actor)->post("/results/{$tournament->id}", [
            'winners' => [$this->winner(['rank_label' => 'THIRD PLACE'])],
        ])->assertSessionHasNoErrors();

        $this->assertCount(1, $tournament->fresh()->winners);
        $this->assertSame('THIRD PLACE', $tournament->fresh()->winners->first()->rank_label);
    }

    /**
     * Potret bertahan lewat ID barisnya saat tidak ada yang diunggah ulang.
     *
     * Kalau ini gagal, menyunting nama seorang juara akan menghapus fotonya.
     */
    public function test_portraits_survive_an_edit_that_uploads_nothing(): void
    {
        Storage::fake('public');
        $tournament = Tournament::factory()->completed()->create();
        $actor = $this->actor();

        $this->actingAs($actor)->post("/results/{$tournament->id}", [
            'winners' => [
                $this->winner() + ['portraits' => [UploadedFile::fake()->image('a.webp', 400, 400)]],
            ],
        ])->assertSessionHasNoErrors();

        $before = $tournament->fresh()->winners->first();
        $this->assertCount(1, $before->portrait_paths);

        $this->actingAs($actor)->post("/results/{$tournament->id}", [
            'winners' => [$this->winner(['id' => $before->id, 'names' => 'Nama Diubah'])],
        ])->assertSessionHasNoErrors();

        $after = $tournament->fresh()->winners->first();

        $this->assertSame('Nama Diubah', $after->names);
        $this->assertSame($before->portrait_paths, $after->portrait_paths);
    }

    /** Id milik turnamen lain ditolak — path dari klien tidak pernah dipercaya. */
    public function test_a_winner_id_from_another_tournament_is_refused(): void
    {
        $mine = Tournament::factory()->completed()->create();
        $other = Tournament::factory()->completed()->create();

        $stranger = $other->winners()->create([
            'rank_label' => 'CHAMPION', 'names' => 'X', 'country' => 'Peru', 'position' => 1,
        ]);

        $this->actingAs($this->actor())
            ->post("/results/{$mine->id}", ['winners' => [$this->winner(['id' => $stranger->id])]])
            ->assertSessionHasErrors('winners.0.id');
    }

    // ------------------------------------------------------ Champions Hall

    /**
     * Potret OPSIONAL, dan itu disengaja — R16 di PRD situs publik. Kartu tanpa
     * potret jatuh ke panel gradien alih-alih menempelkan wajah seseorang pada
     * gelar yang tidak pernah ada.
     */
    public function test_a_champion_can_be_added_without_a_portrait(): void
    {
        $this->actingAs($this->actor())->post('/results/champions', [
            'event' => '2024 World Championship',
            'name' => 'Marcus Johnson',
            'is_active' => true,
        ])->assertSessionHasNoErrors();

        $champion = Champion::query()->sole();

        $this->assertNull($champion->portrait_path);
        $this->assertSame(1, $champion->position);
    }

    public function test_champions_keep_their_own_order(): void
    {
        $actor = $this->actor();

        foreach (['A', 'B', 'C'] as $name) {
            $this->actingAs($actor)->post('/results/champions', [
                'event' => '2024', 'name' => $name, 'is_active' => true,
            ]);
        }

        $this->assertSame(['A', 'B', 'C'], Champion::query()->ordered()->pluck('name')->all());
    }

    public function test_deleting_a_champion_removes_its_portrait(): void
    {
        Storage::fake('public');
        $actor = $this->actor();

        $this->actingAs($actor)->post('/results/champions', [
            'event' => '2024', 'name' => 'A', 'is_active' => true,
            'portrait' => UploadedFile::fake()->image('p.webp', 400, 400),
        ]);

        $champion = Champion::query()->sole();
        $path = $champion->portrait_path;

        Storage::disk('public')->assertExists($path);

        $this->actingAs($actor)->delete("/results/champions/{$champion->id}");

        Storage::disk('public')->assertMissing($path);
        $this->assertDatabaseCount('champions', 0);
    }

    // ----------------------------------------------------- Olympic results

    /** "2024–25" wajar untuk musim lintas tahun — tahunnya teks, bukan angka. */
    public function test_an_olympic_year_may_span_two_years(): void
    {
        $this->actingAs($this->actor())->put('/results/olympic', [
            'results' => [[
                'year' => '2024–25',
                'event' => 'World Series',
                'category' => 'Doubles',
                'winners' => 'Ana Silva & Marco Reis',
                'federation' => 'Brazil',
                'is_active' => true,
            ]],
        ])->assertSessionHasNoErrors();

        $this->assertSame('2024–25', OlympicResult::query()->sole()->year);
    }

    public function test_saving_rewrites_the_olympic_table_in_order(): void
    {
        $actor = $this->actor();

        $row = fn (string $year) => [
            'year' => $year, 'event' => 'E', 'category' => 'C',
            'winners' => 'W', 'federation' => 'F', 'is_active' => true,
        ];

        $this->actingAs($actor)->put('/results/olympic', [
            'results' => [$row('2022'), $row('2023'), $row('2024')],
        ]);

        $this->actingAs($actor)->put('/results/olympic', [
            'results' => [$row('2024'), $row('2022')],
        ]);

        $this->assertSame(['2024', '2022'], OlympicResult::query()->ordered()->pluck('year')->all());
    }

    public function test_the_olympic_export_carries_every_row(): void
    {
        OlympicResult::query()->create([
            'year' => '2024', 'event' => 'World Series', 'category' => 'Singles',
            'winners' => 'Ana Silva', 'federation' => 'Brazil', 'position' => 1,
        ]);

        $csv = $this->actingAs($this->actor())->get('/results/olympic/export')->streamedContent();

        $this->assertStringContainsString('World Series', $csv);
        $this->assertStringContainsString('Ana Silva', $csv);
    }

    // ------------------------------------------------------------ izin

    public function test_a_viewer_cannot_change_results(): void
    {
        $viewer = User::factory()->withRole('viewer')->create();
        $tournament = Tournament::factory()->completed()->create();

        $this->actingAs($viewer)
            ->post("/results/{$tournament->id}", ['winners' => [$this->winner()]])
            ->assertForbidden();
    }
}
