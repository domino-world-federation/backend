<?php

namespace Tests\Feature\Cms;

use App\Models\Document;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
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

    /**
     * Formulir yang LENGKAP — sejak 2026-09-18 seluruh field wajib, jadi yang
     * ini mengisi semuanya dan tiap tes hanya menimpa yang sedang ia uji.
     *
     * Dokumennya dibuat di sini, bukan diminta dari pemanggil: minimal satu
     * lampiran kini wajib, dan tes yang tidak sedang menguji lampiran tidak
     * seharusnya perlu tahu soal itu.
     *
     * @param  array<string, mixed>  $overrides  @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        $document = Document::factory()->create([
            'category' => 'Tournament Documents',
            'status' => Document::STATUS_PUBLISHED,
        ]);

        return array_merge([
            'name' => 'Asian Domino Open 2026',
            'slug' => 'asian-domino-open-2026',
            'coverage' => 'Continental',
            'starts_on' => '2027-03-18',
            'ends_on' => '2027-03-21',
            'city' => 'Bangkok',
            'country' => 'Thailand',
            'rules_format' => 'Double 101',
            'hero_image' => UploadedFile::fake()->image('hero.webp', 1600, 900),
            'overview' => str_repeat('Turnamen ini mempertemukan federasi anggota se-Asia. ', 3),

            'venue_name' => 'Bangkok Convention Hall',
            'venue_address' => '99 Ratchadaphisek Road, Bangkok',
            'venue_lat' => 13.7563,
            'venue_lng' => 100.5018,

            'prize_amount' => 50000,
            'prize_currency' => 'USD',
            'prize_description' => 'Gold medal and 500 ranking points',
            'prize_image' => UploadedFile::fake()->image('prize.webp', 800, 800),

            'contact_email' => 'open@dwf.test',
            'contact_phone' => '+66 2 000 0000',

            'officials' => [$this->official('Maria Santos', 'Chief Referee', 'Spain')],

            'registration_starts_on' => '2027-01-01',
            'registration_ends_on' => '2027-03-01',
            'dwf_id_requirement' => 'Required for all participants',
            'eligibility' => 'Open to all DWF member federations',
            'registration_method' => 'Through national federation',

            'schedule' => [
                ['held_on' => '2027-03-18', 'starts_at' => '09:00', 'activity' => 'Opening Ceremony', 'area' => 'Main Hall'],
            ],

            'participant_count' => 16,
            'documents' => [$document->id],

            'participant_type' => 'Teams',
            'competition_system' => '16 groups of four; top two advance to knockout',
            'scoring' => 'First team to reach 101 points wins the match',

            'posting' => 'now',
        ], $overrides);
    }

    /**
     * Satu baris ofisial LENGKAP, termasuk fotonya — yang juga wajib sejak
     * 2026-09-18.
     *
     * @return array<string, mixed>
     */
    private function official(string $name, string $role, string $country): array
    {
        return [
            'name' => $name,
            'role' => $role,
            'country' => $country,
            'photo' => UploadedFile::fake()->image('official.webp', 400, 400),
        ];
    }

    // ------------------------------------------------------------ menyimpan

    public function test_a_tournament_is_created_with_its_officials_and_schedule(): void
    {
        Storage::fake('public');

        $this->actingAs($this->actor())->post('/tournaments', $this->payload([
            'officials' => [
                $this->official('Maria Santos', 'Chief Referee', 'Spain'),
                $this->official('Kenji Mori', 'Deputy Referee', 'Japan'),
            ],
            'schedule' => [
                ['held_on' => '2027-03-18', 'starts_at' => '09:00', 'activity' => 'Opening Ceremony', 'area' => 'Main Hall'],
                ['held_on' => '2027-03-19', 'starts_at' => '10:00', 'activity' => 'Group Stage', 'area' => 'Hall B'],
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
                $this->official('A', 'Referee', 'Spain'),
                $this->official('B', 'Referee', 'Japan'),
            ],
        ]));

        $tournament = Tournament::query()->sole();

        $this->actingAs($actor)->put("/tournaments/{$tournament->id}", $this->payload([
            'hero_image' => null,
            'prize_image' => null,
            'officials' => [$this->official('B', 'Chief Referee', 'Japan')],
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
            ->post('/tournaments', $this->payload([
                'registration_starts_on' => '2027-01-01',
                'registration_ends_on' => null,
            ]))
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
            ->post('/tournaments', $this->payload(['prize_amount' => 50000, 'prize_currency' => null]))
            ->assertSessionHasErrors('prize_currency');
    }

    /**
     * Seluruh formulir wajib sejak 2026-09-18 — termasuk yang desainnya tandai
     * opsional.
     *
     * Tes ini menggantikan "mengosongkan nominal hadiah ikut mengosongkan mata
     * uangnya": nominal hadiah memang tidak bisa dikosongkan lagi. Yang diuji
     * mewakili tiap kelompok yang dulu opsional, bukan seluruh daftar satu per
     * satu — daftar lengkapnya ada di `TournamentRequest::rules()`.
     */
    public function test_every_field_is_required_including_the_ones_that_used_to_be_optional(): void
    {
        Storage::fake('public');

        $this->actingAs($this->actor())
            ->post('/tournaments', $this->payload([
                'slug' => '',
                'prize_amount' => null,
                'contact_email' => null,
                'dwf_id_requirement' => null,
                'participant_count' => null,
                'officials' => [],
                'schedule' => [],
                'documents' => [],
            ]))
            ->assertSessionHasErrors([
                'slug', 'prize_amount', 'contact_email', 'dwf_id_requirement',
                'participant_count', 'officials', 'schedule', 'documents',
            ]);

        $this->assertSame(0, Tournament::query()->count());
    }

    /**
     * Foto ofisial wajib — tapi baris yang SUDAH punya foto tersimpan tidak
     * dituntut mengunggahnya ulang saat turnamennya disunting.
     */
    public function test_an_official_needs_a_photo_unless_one_is_already_stored(): void
    {
        Storage::fake('public');
        $actor = $this->actor();

        $this->actingAs($actor)
            ->post('/tournaments', $this->payload([
                'officials' => [['name' => 'A', 'role' => 'Referee', 'country' => 'Spain']],
            ]))
            ->assertSessionHasErrors('officials.0.photo');

        $this->actingAs($actor)->post('/tournaments', $this->payload())->assertSessionHasNoErrors();

        $tournament = Tournament::query()->sole();
        $stored = $tournament->officials()->sole();

        $this->actingAs($actor)->put("/tournaments/{$tournament->id}", $this->payload([
            'hero_image' => null,
            'prize_image' => null,
            'officials' => [[
                'id' => $stored->id,
                'name' => $stored->name,
                'role' => $stored->role,
                'country' => $stored->country,
            ]],
        ]))->assertSessionHasNoErrors();
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
            'officials' => [$this->official('A', 'Referee', 'Spain')],
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
        Storage::fake('public');

        $this->actingAs(User::factory()->superAdmin()->create())
            ->post('/tournaments', $this->payload([
                'overview' => '<p>Turnamen tahunan yang diikuti federasi dari lima benua, digelar selama sepekan penuh.</p><script>alert(1)</script>',
            ]));

        $overview = Tournament::query()->latest('id')->value('overview');

        $this->assertStringNotContainsString('<script', $overview);
        $this->assertStringContainsString('<p>', $overview);
    }

    /**
     * Picker lampiran hanya menawarkan `Tournament Documents`.
     *
     * Sebelum 2026-09-09 ia menawarkan seluruh perpustakaan, jadi statuta
     * federasi bisa menempel di sebuah turnamen dan kategorinya tidak berarti
     * apa-apa selain label yang tercetak di kartu. Ini yang membuat
     * `config/dwf.php` boleh menyebut halaman detail turnamen sebagai tempat
     * kategori ini tayang tanpa berbohong.
     */
    public function test_only_tournament_documents_can_be_attached(): void
    {
        $eligible = Document::factory()->create([
            'category' => 'Tournament Documents',
            'status' => Document::STATUS_PUBLISHED,
        ]);
        Document::factory()->create([
            'category' => 'Governance Documents',
            'status' => Document::STATUS_PUBLISHED,
        ]);

        $this->actingAs(User::factory()->superAdmin()->create())
            ->get('/tournaments/create')
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->has('documentOptions', 1)
                ->where('documentOptions.0.value', $eligible->id));
    }
}
