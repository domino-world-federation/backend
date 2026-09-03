<?php

namespace Tests\Feature\Cms;

use App\Models\FederationStat;
use App\Models\MemberFederation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Federasi anggota — direktori yang mengisi `/federation-members`.
 *
 * Yang dikunci di sini: kontrak "sembilan dari sebelas field opsional", dan
 * dua penjagaan yang mencegah lingkup akun admin jadi yatim.
 */
class FederationTest extends TestCase
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
            'name' => 'Persatuan Domino Indonesia',
            'country' => 'Indonesia',
            'is_active' => true,
        ], $overrides);
    }

    /**
     * Nama dan negara saja sudah cukup.
     *
     * Kontraknya di `types.ts`: sebuah badan bisa diakui jauh sebelum ia
     * menyetorkan nama presiden atau nomor telepon, dan kartunya mencetak baris
     * yang ada alih-alih baris kosong.
     */
    public function test_a_federation_needs_only_a_name_and_a_country(): void
    {
        $this->actingAs($this->actor())
            ->post('/federations', $this->payload())
            ->assertRedirect('/federations');

        $federation = MemberFederation::query()->sole();

        $this->assertSame('Indonesia', $federation->country);
        $this->assertNull($federation->president);
        $this->assertNull($federation->tier);
        $this->assertNull($federation->joined_year);
    }

    public function test_an_unknown_tier_is_refused(): void
    {
        $this->actingAs($this->actor())
            ->post('/federations', $this->payload(['tier' => 'platinum']))
            ->assertSessionHasErrors('tier');
    }

    /** Federasi yang bergabung tahun depan adalah salah ketik, bukan rencana. */
    public function test_a_future_joined_year_is_refused(): void
    {
        $this->actingAs($this->actor())
            ->post('/federations', $this->payload(['joined_year' => now()->year + 1]))
            ->assertSessionHasErrors('joined_year');
    }

    public function test_the_tier_label_comes_from_config(): void
    {
        $federation = MemberFederation::factory()->create(['tier' => 'continent']);

        $this->assertSame('Continent Members', $federation->tier_label);
    }

    // ------------------------------------- penjagaan lingkup akun admin

    /**
     * Federasi yang masih jadi lingkup akun admin tidak boleh dihapus.
     *
     * `member_federation_id` memakai `nullOnDelete`, jadi tanpa penjagaan ini
     * menghapusnya akan diam-diam mencabut lingkup akun itu — dan tidak ada
     * satu pun layar yang memberi tahu.
     */
    public function test_a_federation_still_scoping_an_admin_cannot_be_deleted(): void
    {
        $federation = MemberFederation::factory()->create();
        User::factory()->withRole('editor')->create(['member_federation_id' => $federation->id]);

        $this->actingAs($this->actor())
            ->delete("/federations/{$federation->id}")
            ->assertSessionHasErrors('federation');

        $this->assertModelExists($federation);
    }

    /** Mematikannya juga ditolak — akibatnya sama, cuma lebih halus. */
    public function test_a_federation_still_scoping_an_admin_cannot_be_deactivated(): void
    {
        $federation = MemberFederation::factory()->create();
        User::factory()->withRole('editor')->create(['member_federation_id' => $federation->id]);

        $this->actingAs($this->actor())
            ->patch("/federations/{$federation->id}/status", ['is_active' => false])
            ->assertSessionHasErrors('is_active');

        $this->assertTrue($federation->fresh()->is_active);
    }

    public function test_an_unused_federation_can_be_deleted(): void
    {
        $federation = MemberFederation::factory()->create();

        $this->actingAs($this->actor())
            ->delete("/federations/{$federation->id}")
            ->assertSessionHasNoErrors();

        $this->assertModelMissing($federation);
    }

    // ------------------------------------------------------------ statistik

    /**
     * Satu tabel, dua lingkup — menyimpan yang satu tidak boleh menyentuh yang
     * lain.
     */
    public function test_saving_one_scope_leaves_the_other_alone(): void
    {
        FederationStat::factory()->members()->create(['label' => 'Federations', 'value' => '57']);

        $this->actingAs($this->actor())->put('/federations/stats', [
            'scope' => 'home',
            'stats' => [
                ['label' => 'Member federations', 'value' => '120+', 'is_active' => true],
            ],
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, FederationStat::query()->where('scope', 'home')->count());
        $this->assertSame(1, FederationStat::query()->where('scope', 'members')->count());
        $this->assertSame('57', FederationStat::query()->where('scope', 'members')->sole()->value);
    }

    /** Urutannya dari susunan di layar, dan ditulis ulang tiap simpan. */
    public function test_saving_rewrites_the_rows_in_order(): void
    {
        $actor = $this->actor();

        $this->actingAs($actor)->put('/federations/stats', [
            'scope' => 'home',
            'stats' => [
                ['label' => 'A', 'value' => '1', 'is_active' => true],
                ['label' => 'B', 'value' => '2', 'is_active' => true],
                ['label' => 'C', 'value' => '3', 'is_active' => false],
            ],
        ]);

        $this->actingAs($actor)->put('/federations/stats', [
            'scope' => 'home',
            'stats' => [
                ['label' => 'C', 'value' => '3', 'is_active' => true],
                ['label' => 'A', 'value' => '9', 'is_active' => true],
            ],
        ]);

        $rows = FederationStat::query()->where('scope', 'home')->ordered()->get();

        $this->assertCount(2, $rows);
        $this->assertSame(['C', 'A'], $rows->pluck('label')->all());
        $this->assertSame('9', $rows->last()->value);
    }

    /** "120+" harus muat — nilainya teks, bukan angka. */
    public function test_a_statistic_value_may_be_text(): void
    {
        $this->actingAs($this->actor())->put('/federations/stats', [
            'scope' => 'members',
            'stats' => [['label' => 'Countries', 'value' => '120+', 'is_active' => true]],
        ])->assertSessionHasNoErrors();

        $this->assertSame('120+', FederationStat::query()->sole()->value);
    }

    public function test_an_unknown_scope_is_refused(): void
    {
        $this->actingAs($this->actor())
            ->put('/federations/stats', ['scope' => 'about', 'stats' => []])
            ->assertSessionHasErrors('scope');
    }
}
