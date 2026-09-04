<?php

namespace Tests\Feature\Cms;

use App\Models\BoardMember;
use App\Models\HeritageMilestone;
use App\Models\Partner;
use App\Models\StandingCommittee;
use App\Models\SubCommittee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * People & Governance dan Partners & Heritage.
 *
 * Lima daftar berurut yang bentuknya sama, jadi yang diuji di sini bukan CRUD
 * masing-masing melainkan yang KHAS: pemecahan remit jadi pil, kolom opsional
 * yang memang boleh kosong, dan logo partner yang justru wajib.
 */
class PeopleAndBlocksTest extends TestCase
{
    use RefreshDatabase;

    private function actor(): User
    {
        return User::factory()->superAdmin()->create();
    }

    // ------------------------------------------------------ Executive Board

    /**
     * Potret WAJIB saat menambah anggota — membalik perilaku sebelumnya.
     *
     * `BoardCard` di situs publik menggambar `<NuxtImg :src="member.portraitUrl">`
     * TANPA penjagaan, dan `types.ts` menyatakan `portraitUrl` wajib. Anggota
     * tanpa potret karena itu bukan "kartu tanpa gambar", melainkan gambar
     * rusak — persis alasan yang sudah dipakai logo partner.
     *
     * Boleh kosong saat MENYUNTING: membetulkan salah ketik nama tidak
     * seharusnya menuntut unggah ulang potretnya.
     */
    public function test_a_board_member_needs_a_portrait(): void
    {
        Storage::fake('public');

        $this->actingAs($this->actor())->post('/people', [
            'name' => 'Robbi Darwis',
            'role' => 'President',
            'is_active' => true,
        ])->assertSessionHasErrors('portrait');

        $this->actingAs($this->actor())->post('/people', [
            'name' => 'Robbi Darwis',
            'role' => 'President',
            'is_active' => true,
            'portrait' => UploadedFile::fake()->image('robbi.webp', 800, 800),
        ])->assertSessionHasNoErrors();

        $member = BoardMember::query()->sole();

        $this->assertNotNull($member->portrait_path);
        $this->assertSame(1, $member->position);
    }

    /** Kartunya merender dua baris kalau namanya memuat baris baru. */
    public function test_a_board_member_name_may_span_two_lines(): void
    {
        Storage::fake('public');

        $this->actingAs($this->actor())->post('/people', [
            'name' => "Maria\nSantos",
            'role' => 'Vice President',
            'is_active' => true,
            'portrait' => UploadedFile::fake()->image('maria.webp', 800, 800),
        ])->assertSessionHasNoErrors();

        $this->assertSame("Maria\nSantos", BoardMember::query()->sole()->name);
    }

    public function test_deleting_a_board_member_removes_its_portrait(): void
    {
        Storage::fake('public');
        $actor = $this->actor();

        $this->actingAs($actor)->post('/people', [
            'name' => 'A', 'role' => 'Treasurer', 'is_active' => true,
            'portrait' => UploadedFile::fake()->image('p.webp', 400, 400),
        ]);

        $member = BoardMember::query()->sole();
        $path = $member->portrait_path;
        Storage::disk('public')->assertExists($path);

        $this->actingAs($actor)->delete("/people/{$member->id}");

        Storage::disk('public')->assertMissing($path);
    }

    // ------------------------------------------------------- Sub-committees

    /** `href` boleh kosong: halaman tujuannya belum tentu ada. */
    public function test_a_sub_committee_may_have_no_link(): void
    {
        $this->actingAs($this->actor())->put('/people/sub-committees', [
            'committees' => [['name' => 'Technical Committee', 'href' => null, 'is_active' => true]],
        ])->assertSessionHasNoErrors();

        $this->assertNull(SubCommittee::query()->sole()->href);
    }

    public function test_saving_rewrites_sub_committees_in_order(): void
    {
        $actor = $this->actor();

        $this->actingAs($actor)->put('/people/sub-committees', [
            'committees' => [
                ['name' => 'A', 'href' => null, 'is_active' => true],
                ['name' => 'B', 'href' => null, 'is_active' => true],
            ],
        ]);

        $this->actingAs($actor)->put('/people/sub-committees', [
            'committees' => [['name' => 'B', 'href' => '/b', 'is_active' => true]],
        ]);

        $rows = SubCommittee::query()->ordered()->get();

        $this->assertCount(1, $rows);
        $this->assertSame('B', $rows->first()->name);
        $this->assertSame('/b', $rows->first()->href);
    }

    // --------------------------------------------------- Standing committees

    /** "Players, KYC, federation content" jadi tiga pil. */
    public function test_a_remit_is_split_into_pills(): void
    {
        $this->actingAs($this->actor())->put('/people/committees', [
            'committees' => [[
                'name' => 'Membership Committee',
                'remit' => 'Players, KYC, federation content',
                'is_active' => true,
            ]],
        ])->assertSessionHasNoErrors();

        $this->assertSame(
            ['Players', 'KYC', 'federation content'],
            StandingCommittee::query()->sole()->remit,
        );
    }

    /**
     * Koma di ujung adalah salah ketik yang lazim; tanpa penyaringan ia jadi
     * pil kosong di halaman publik.
     */
    public function test_empty_remit_parts_are_dropped(): void
    {
        $this->actingAs($this->actor())->put('/people/committees', [
            'committees' => [['name' => 'X', 'remit' => 'Rules, , Appeals,', 'is_active' => true]],
        ]);

        $this->assertSame(['Rules', 'Appeals'], StandingCommittee::query()->sole()->remit);
    }

    public function test_a_committee_may_have_no_remit_at_all(): void
    {
        $this->actingAs($this->actor())->put('/people/committees', [
            'committees' => [['name' => 'X', 'remit' => null, 'is_active' => true]],
        ])->assertSessionHasNoErrors();

        $this->assertSame([], StandingCommittee::query()->sole()->remit);
    }

    // ------------------------------------------------------------- Partners

    /**
     * Logo WAJIB saat membuat — slot strip tanpa logo adalah slot kosong, dan
     * tidak ada yang bisa digambar sebagai gantinya.
     */
    public function test_a_partner_needs_a_logo(): void
    {
        $this->actingAs($this->actor())
            ->post('/blocks/partners', ['name' => 'Tanpa logo', 'is_active' => true])
            ->assertSessionHasErrors('logo');
    }

    /** URL boleh kosong — pertanyaan terbuka #6 di PRD situs publik. */
    public function test_a_partner_may_have_no_website(): void
    {
        Storage::fake('public');

        $this->actingAs($this->actor())->post('/blocks/partners', [
            'name' => 'Global Sports Network',
            'logo' => UploadedFile::fake()->image('logo.webp', 400, 200),
            'is_active' => true,
        ])->assertSessionHasNoErrors();

        $this->assertNull(Partner::query()->sole()->website_url);
    }

    /** Menyunting tanpa mengunggah ulang mempertahankan logonya. */
    public function test_editing_a_partner_keeps_its_logo(): void
    {
        Storage::fake('public');
        $actor = $this->actor();

        $this->actingAs($actor)->post('/blocks/partners', [
            'name' => 'A',
            'logo' => UploadedFile::fake()->image('logo.webp', 400, 200),
            'is_active' => true,
        ]);

        $partner = Partner::query()->sole();
        $path = $partner->logo_path;

        $this->actingAs($actor)->post("/blocks/partners/{$partner->id}", [
            'name' => 'A diubah',
            'is_active' => true,
        ])->assertSessionHasNoErrors();

        $this->assertSame($path, $partner->fresh()->logo_path);
        $this->assertSame('A diubah', $partner->fresh()->name);
    }

    // ------------------------------------------------------------- Heritage

    /** "1990s" sama sahnya dengan "1974" — tahunnya penanda, bukan angka. */
    public function test_a_milestone_year_may_be_a_decade(): void
    {
        Storage::fake('public');

        $this->actingAs($this->actor())->post('/blocks/heritage', [
            'year' => '1990s',
            'title' => 'Expansion across Asia',
            'summary' => 'Member federations doubled in a decade.',
            'is_active' => true,
            'image' => UploadedFile::fake()->image('asia.webp', 1200, 900),
            'image_alt' => 'Delegasi federasi Asia pada kongres 1994',
        ])->assertSessionHasNoErrors();

        $this->assertSame('1990s', HeritageMilestone::query()->sole()->year);
    }

    public function test_milestones_keep_their_own_order(): void
    {
        $actor = $this->actor();

        Storage::fake('public');

        foreach (['1974', '1998', '2026'] as $year) {
            $this->actingAs($actor)->post('/blocks/heritage', [
                'year' => $year, 'title' => "T{$year}", 'summary' => 'Ringkasan.', 'is_active' => true,
                'image' => UploadedFile::fake()->image("m{$year}.webp", 1200, 900),
                'image_alt' => "Tonggak {$year}",
            ]);
        }

        $this->assertSame(
            ['1974', '1998', '2026'],
            HeritageMilestone::query()->ordered()->pluck('year')->all(),
        );
    }

    // ----------------------------------------------------------------- izin

    public function test_a_viewer_cannot_change_these_lists(): void
    {
        $viewer = User::factory()->withRole('viewer')->create();

        $this->actingAs($viewer)
            ->put('/people/committees', ['committees' => []])
            ->assertForbidden();

        $this->actingAs($viewer)
            ->post('/blocks/partners', ['name' => 'X', 'is_active' => true])
            ->assertForbidden();
    }
}
