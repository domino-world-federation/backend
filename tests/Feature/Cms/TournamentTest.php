<?php

namespace Tests\Feature\Cms;

use App\Models\Document;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Turnamen — formulir `585:11241`.
 *
 * Yang dikunci di sini bukan CRUD-nya (itu sama dengan modul lain dan sudah
 * dijaga `StandardListTest`), melainkan yang khas turnamen: dua keadaan yang
 * DITURUNKAN dari tanggal, aturan silang antar-tanggal yang tidak bisa ditulis
 * sebagai aturan validasi biasa, dan kelompok berulang yang ditulis ulang tiap
 * simpan.
 */
class TournamentTest extends TestCase
{
    use RefreshDatabase;

    private function actor(): User
    {
        return User::factory()->superAdmin()->create();
    }

    /** @param array<string, mixed> $overrides @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Asian Domino Open 2026',
            'coverage' => 'Continental',
            'starts_on' => '2027-03-18',
            'ends_on' => '2027-03-21',
            'city' => 'Bangkok',
            'country' => 'Thailand',
            'rules_format' => 'Double 101',
            'attendance' => 'Offline',
            'hero_image' => UploadedFile::fake()->image('hero.webp', 1600, 900),
            'overview' => str_repeat('Turnamen ini mempertemukan federasi anggota se-Asia. ', 3),

            'venue_name' => 'Bangkok Convention Hall',
            'venue_address' => '99 Ratchadaphisek Road, Bangkok',
            'venue_lat' => 13.7563,
            'venue_lng' => 100.5018,

            'eligibility' => 'Open to all DWF member federations',
            'registration_method' => 'Through national federation',

            'game_format' => 'Double-101',
            'participant_type' => 'Teams',
            'competition_system' => '16 groups of four; top two advance to knockout',
            'scoring' => 'First team to reach 101 points wins the match',

            'posting' => 'now',
        ], $overrides);
    }

    // ------------------------------------------------------------ menyimpan

    public function test_a_tournament_is_created_with_its_officials_and_schedule(): void
    {
        Storage::fake('public');

        $this->actingAs($this->actor())->post('/tournaments', $this->payload([
            'officials' => [
                ['name' => 'Maria Santos', 'role' => 'Chief Referee', 'country' => 'Spain'],
                ['name' => 'Kenji Mori', 'role' => 'Deputy Referee', 'country' => 'Japan'],
            ],
            'schedule' => [
                ['held_on' => '2027-03-18', 'starts_at' => '09:00', 'activity' => 'Opening Ceremony', 'area' => 'Main Hall'],
                ['held_on' => '2027-03-19', 'starts_at' => '10:00', 'activity' => 'Group Stage'],
            ],
        ]))->assertRedirect('/tournaments');

        $tournament = Tournament::query()->sole();

        $this->assertSame('asian-domino-open-2026', $tournament->slug);
        $this->assertCount(2, $tournament->officials);
        $this->assertCount(2, $tournament->scheduleEntries);

        // Urutannya dari posisi di formulir, bukan dari jam mulai.
        $this->assertSame(
            ['Opening Ceremony', 'Group Stage'],
            $tournament->scheduleEntries->pluck('activity')->all(),
        );
        $this->assertSame([1, 2], $tournament->officials->pluck('position')->all());
    }

    /**
     * Kelompok berulang DITULIS ULANG tiap simpan.
     *
     * Baris yang dihapus benar-benar hilang, dan urutan baru benar-benar
     * tersimpan — bukan ditumpuk di atas yang lama.
     */
    public function test_saving_replaces_the_repeating_groups(): void
    {
        Storage::fake('public');
        $actor = $this->actor();

        $this->actingAs($actor)->post('/tournaments', $this->payload([
            'officials' => [
                ['name' => 'A', 'role' => 'Referee', 'country' => 'Spain'],
                ['name' => 'B', 'role' => 'Referee', 'country' => 'Japan'],
            ],
        ]));

        $tournament = Tournament::query()->sole();

        $this->actingAs($actor)->put("/tournaments/{$tournament->id}", $this->payload([
            'hero_image' => null,
            'officials' => [['name' => 'B', 'role' => 'Chief Referee', 'country' => 'Japan']],
        ]))->assertSessionHasNoErrors();

        $officials = $tournament->fresh()->officials;

        $this->assertCount(1, $officials);
        $this->assertSame('B', $officials->first()->name);
        $this->assertSame('Chief Referee', $officials->first()->role);
    }

    // ------------------------------------------------ keadaan yang diturunkan

    /**
     * `stage` dan `visibility` menjawab pertanyaan yang BERBEDA.
     *
     * Turnamen bisa `published` (halamannya tayang) dan `completed`
     * (pertandingannya sudah usai) sekaligus — dan itu memang keadaan yang
     * paling lazim untuk arsip.
     */
    public function test_stage_is_derived_from_dates_not_from_visibility(): void
    {
        $upcoming = Tournament::factory()->create();
        $live = Tournament::factory()->live()->create();
        $done = Tournament::factory()->completed()->create();

        $this->assertSame('upcoming', $upcoming->stage);
        $this->assertSame('live', $live->stage);
        $this->assertSame('completed', $done->stage);

        $this->assertSame('posted', $done->visibility);
    }

    /**
     * `upcoming` adalah anggota KEEMPAT keadaan pendaftaran, dan ia harus ada.
     *
     * Tanpanya, "pendaftaran belum dibuka" dan "pendaftaran sudah berakhir"
     * sama-sama dipikul `closed` — dan kartunya mencetak pil CLOSED di atas tab
     * "Registration opens Nov 1".
     */
    public function test_registration_state_separates_not_yet_open_from_closed(): void
    {
        $notYet = Tournament::factory()->create([
            'registration_starts_on' => now()->addWeek(),
            'registration_ends_on' => now()->addMonth(),
        ]);

        $open = Tournament::factory()->create([
            'registration_starts_on' => now()->subWeek(),
            'registration_ends_on' => now()->addWeek(),
        ]);

        $over = Tournament::factory()->create([
            'registration_starts_on' => now()->subMonth(),
            'registration_ends_on' => now()->subWeek(),
        ]);

        $this->assertSame('upcoming', $notYet->registration_state);
        $this->assertSame('open', $open->registration_state);
        $this->assertSame('closed', $over->registration_state);
    }

    /** Pendaftaran yang masih menerima saat pertandingan sudah jalan. */
    public function test_registration_reads_ongoing_once_the_tournament_started(): void
    {
        $tournament = Tournament::factory()->live()->create([
            'registration_starts_on' => now()->subMonth(),
            'registration_ends_on' => now()->addWeek(),
        ]);

        $this->assertSame('ongoing', $tournament->registration_state);
    }

    /** Tanpa tanggal pendaftaran sama sekali = tertutup, bukan terbuka. */
    public function test_a_tournament_without_registration_dates_is_closed(): void
    {
        $this->assertSame('closed', Tournament::factory()->create()->registration_state);
    }

    // ----------------------------------------------------------- validasi

    /** "if provided, Registration End Date is also required" (`596:11304`). */
    public function test_a_registration_start_without_an_end_is_refused(): void
    {
        Storage::fake('public');

        $this->actingAs($this->actor())
            ->post('/tournaments', $this->payload(['registration_starts_on' => '2027-01-01']))
            ->assertSessionHasErrors('registration_ends_on');
    }

    /** "must … close before tournament start" (`596:11304`). */
    public function test_registration_must_close_before_the_tournament_starts(): void
    {
        Storage::fake('public');

        $this->actingAs($this->actor())
            ->post('/tournaments', $this->payload([
                'registration_starts_on' => '2027-01-01',
                'registration_ends_on' => '2027-03-20',
            ]))
            ->assertSessionHasErrors('registration_ends_on');
    }

    /** "must fall within tournament dates" (`596:11371`). */
    public function test_a_schedule_item_outside_the_tournament_dates_is_refused(): void
    {
        Storage::fake('public');

        $this->actingAs($this->actor())
            ->post('/tournaments', $this->payload([
                'schedule' => [
                    ['held_on' => '2027-04-01', 'starts_at' => '09:00', 'activity' => 'Terlalu jauh'],
                ],
            ]))
            ->assertSessionHasErrors('schedule.0.held_on');
    }

    /** "required when Prize Pool Amount is filled" (`596:11158`). */
    public function test_a_prize_amount_without_a_currency_is_refused(): void
    {
        Storage::fake('public');

        $this->actingAs($this->actor())
            ->post('/tournaments', $this->payload(['prize_amount' => 50000]))
            ->assertSessionHasErrors('prize_currency');
    }

    /** Mata uang dikosongkan kalau nominalnya dihapus. */
    public function test_clearing_the_prize_amount_clears_its_currency(): void
    {
        Storage::fake('public');
        $actor = $this->actor();

        $this->actingAs($actor)->post('/tournaments', $this->payload([
            'prize_amount' => 50000,
            'prize_currency' => 'USD',
        ]))->assertSessionHasNoErrors();

        $tournament = Tournament::query()->sole();
        $this->assertSame('USD', $tournament->prize_currency);

        $this->actingAs($actor)->put("/tournaments/{$tournament->id}", $this->payload([
            'hero_image' => null,
            'prize_amount' => null,
            'prize_currency' => 'USD',
        ]))->assertSessionHasNoErrors();

        $this->assertNull($tournament->fresh()->prize_currency);
    }

    /**
     * "select up to 10 existing PUBLISHED documents" (`596:11467`).
     *
     * Menautkan draf berarti halaman turnamen memuat tautan ke berkas yang
     * belum boleh dilihat siapa pun.
     */
    public function test_only_published_documents_can_be_attached(): void
    {
        Storage::fake('public');

        $live = Document::factory()->create();
        $draft = Document::factory()->create(['status' => 'draft', 'published_at' => null]);

        $this->actingAs($this->actor())
            ->post('/tournaments', $this->payload(['documents' => [$draft->id]]))
            ->assertSessionHasErrors('documents');

        $this->actingAs($this->actor())
            ->post('/tournaments', $this->payload(['documents' => [$live->id]]))
            ->assertSessionHasNoErrors();

        $this->assertSame([$live->id], Tournament::query()->sole()->documents->pluck('id')->all());
    }

    /**
     * Foto ofisial dipertahankan lewat ID barisnya, BUKAN path yang dikirim
     * klien — path dari klien adalah jalan masuk untuk menunjuk berkas mana pun
     * di disk.
     */
    public function test_an_unknown_official_id_is_refused(): void
    {
        Storage::fake('public');
        $actor = $this->actor();

        $this->actingAs($actor)->post('/tournaments', $this->payload([
            'officials' => [['name' => 'A', 'role' => 'Referee', 'country' => 'Spain']],
        ]));

        $mine = Tournament::query()->sole();
        $other = Tournament::factory()->create();
        $stranger = $other->officials()->create([
            'name' => 'X', 'role' => 'Referee', 'country' => 'Peru', 'position' => 1,
        ]);

        $this->actingAs($actor)->put("/tournaments/{$mine->id}", $this->payload([
            'hero_image' => null,
            'officials' => [['id' => $stranger->id, 'name' => 'A', 'role' => 'Referee', 'country' => 'Spain']],
        ]))->assertSessionHasErrors('officials.0.id');
    }

    // ------------------------------------------------------------- daftar

    public function test_the_stage_filter_narrows_by_dates(): void
    {
        Tournament::factory()->create(['name' => 'nanti']);
        Tournament::factory()->completed()->create(['name' => 'sudah']);

        $csv = $this->actingAs($this->actor())
            ->get('/tournaments/export?stage=completed')
            ->streamedContent();

        $this->assertStringContainsString('sudah', $csv);
        $this->assertStringNotContainsString('nanti', $csv);
    }

    public function test_deleting_a_tournament_takes_its_children_with_it(): void
    {
        $tournament = Tournament::factory()->create();
        $tournament->officials()->create(['name' => 'A', 'role' => 'R', 'country' => 'ID', 'position' => 1]);
        $tournament->scheduleEntries()->create([
            'held_on' => $tournament->starts_on, 'starts_at' => '09:00', 'activity' => 'Opening', 'position' => 1,
        ]);

        $this->actingAs($this->actor())->delete("/tournaments/{$tournament->id}");

        $this->assertDatabaseCount('tournaments', 0);
        $this->assertDatabaseCount('tournament_officials', 0);
        $this->assertDatabaseCount('tournament_schedule_entries', 0);
    }

    /**
     * `overview` ditulis lewat editor teks kaya, jadi ia HTML — dan ia tampil
     * di halaman turnamen situs publik.
     *
     * Sempat terlewat sampai 2026-09-03: satu-satunya kolom editor di repo ini
     * yang menyimpan mentah, sementara News, FAQ, dan Legal Pages sudah punya
     * tes yang sama sejak awal.
     */
    public function test_the_overview_is_purified(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create())
            ->post('/tournaments', $this->payload([
                'overview' => '<p>Turnamen tahunan yang diikuti federasi dari lima benua, digelar selama sepekan penuh.</p><script>alert(1)</script>',
            ]));

        $overview = Tournament::query()->latest('id')->value('overview');

        $this->assertStringNotContainsString('<script', $overview);
        $this->assertStringContainsString('<p>', $overview);
    }
}
