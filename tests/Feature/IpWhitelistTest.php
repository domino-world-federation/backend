<?php

namespace Tests\Feature;

use App\Models\IpWhitelistRule;
use App\Models\User;
use App\Support\Security\IpWhitelist;
use Database\Seeders\AccessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Daftar IP backoffice — Figma `527:7038`.
 *
 * Yang dikunci di sini bukan CRUD-nya (itu sama dengan modul lain dan sudah
 * dijaga `StandardListTest`), melainkan empat hal yang gagal DIAM-DIAM:
 * tabel kosong yang seharusnya tidak mengunci siapa pun, kedaluwarsa yang harus
 * berlaku tanpa job, irisan CIDR yang tidak boleh lolos, dan penjaga yang
 * menahan orang mengusir dirinya sendiri.
 */
class IpWhitelistTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Middleware-nya dilewati di `local`, dan tes berjalan di `testing` —
     * jadi seluruh berkas ini menguji perilaku yang benar-benar dipakai di
     * produksi. Kalau environment tesnya suatu saat diubah jadi `local`,
     * separuh berkas ini akan lulus tanpa menguji apa pun; tes di bawah
     * memastikan asumsinya masih berlaku.
     */
    public function test_the_suite_runs_where_the_whitelist_is_enforced(): void
    {
        $this->assertFalse(app()->environment('local'));
    }

    // ---------------------------------------------------------------- policy

    public function test_an_empty_table_restricts_nobody(): void
    {
        $user = User::factory()->superAdmin()->create();

        $this->assertTrue(IpWhitelist::allows($user, '198.51.100.7'));
    }

    /**
     * Inti dari kolom "Access Scope": aturan yang tidak menyasar seseorang
     * tidak boleh ikut membatasinya.
     */
    public function test_a_rule_for_somebody_else_does_not_restrict_you(): void
    {
        $editor = User::factory()->withRole('editor')->create();
        $adminRole = Role::findByName('admin', 'web');

        IpWhitelistRule::factory()->forRole($adminRole->id)->create(['ip_range' => '203.0.113.9']);

        $this->assertTrue(IpWhitelist::allows($editor, '198.51.100.7'));
    }

    public function test_a_rule_that_targets_you_starts_restricting_you(): void
    {
        $user = User::factory()->superAdmin()->create();

        IpWhitelistRule::factory()->create(['ip_range' => '203.0.113.9']);

        $this->assertFalse(IpWhitelist::allows($user, '198.51.100.7'));
        $this->assertTrue(IpWhitelist::allows($user, '203.0.113.9'));
    }

    public function test_a_cidr_rule_covers_its_whole_range(): void
    {
        $user = User::factory()->superAdmin()->create();

        IpWhitelistRule::factory()->create(['ip_range' => '203.0.113.0/24']);

        $this->assertTrue(IpWhitelist::allows($user, '203.0.113.200'));
        $this->assertFalse(IpWhitelist::allows($user, '203.0.114.1'));
    }

    public function test_a_user_scoped_rule_only_covers_that_user(): void
    {
        $mine = User::factory()->superAdmin()->create();
        $theirs = User::factory()->superAdmin()->create();

        IpWhitelistRule::factory()->forUser($mine->id)->create(['ip_range' => '203.0.113.5']);

        $this->assertFalse(IpWhitelist::allows($mine, '198.51.100.7'));
        $this->assertTrue(IpWhitelist::allows($theirs, '198.51.100.7'));
    }

    /**
     * Kedaluwarsa berlaku pada detik tanggalnya lewat, bukan saat scheduler
     * berikutnya jalan. Aturan ini masih `is_active = true` di database.
     *
     * Diuji berdampingan dengan aturan yang masih hidup: tanpa itu, tabelnya
     * jadi kosong bagi pengguna ini dan SEMUA alamat lolos — yang benar, tapi
     * lolos karena alasan yang berbeda dari yang sedang diperiksa.
     */
    public function test_an_expired_rule_stops_granting_access(): void
    {
        $user = User::factory()->superAdmin()->create();

        IpWhitelistRule::factory()->create(['ip_range' => '203.0.113.1']);
        $expired = IpWhitelistRule::factory()->expired()->create(['ip_range' => '198.51.100.7']);

        $this->assertTrue($expired->is_active);
        $this->assertTrue(IpWhitelist::allows($user, '203.0.113.1'));
        $this->assertFalse(IpWhitelist::allows($user, '198.51.100.7'));
    }

    public function test_an_inactive_rule_neither_grants_nor_restricts(): void
    {
        $user = User::factory()->superAdmin()->create();

        IpWhitelistRule::factory()->inactive()->create(['ip_range' => '203.0.113.9']);

        $this->assertTrue(IpWhitelist::allows($user, '198.51.100.7'));
    }

    /** Super admin TIDAK dikecualikan — kalau ya, fiturnya tidak berarti apa-apa. */
    public function test_a_super_admin_is_not_exempt(): void
    {
        $user = User::factory()->superAdmin()->create();

        IpWhitelistRule::factory()->create(['ip_range' => '203.0.113.9']);

        $this->assertFalse(IpWhitelist::allows($user, '198.51.100.7'));
    }

    // ------------------------------------------------------------ middleware

    public function test_a_blocked_address_cannot_reach_the_backoffice(): void
    {
        $user = User::factory()->superAdmin()->create();
        IpWhitelistRule::factory()->create(['ip_range' => '203.0.113.9']);

        $this->actingAs($user)
            ->withServerVariables(['REMOTE_ADDR' => '198.51.100.7'])
            ->get('/dashboard')
            ->assertForbidden();
    }

    public function test_an_allowed_address_passes_through(): void
    {
        $user = User::factory()->superAdmin()->create();
        IpWhitelistRule::factory()->create(['ip_range' => '203.0.113.0/24']);

        $this->actingAs($user)
            ->withServerVariables(['REMOTE_ADDR' => '203.0.113.42'])
            ->get('/dashboard')
            ->assertOk();
    }

    /**
     * Halaman login tidak dijaga daftar ini, dan itu disengaja: lingkup sebuah
     * aturan bisa berupa peran, yang mustahil diketahui sebelum ada yang login.
     */
    public function test_the_login_screen_stays_reachable(): void
    {
        IpWhitelistRule::factory()->create(['ip_range' => '203.0.113.9']);

        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.7'])
            ->get('/login')
            ->assertOk();
    }

    // ------------------------------------------------------------ validation

    /**
     * Kolom ketiga adalah alamat yang HARUS tercakup polanya.
     *
     * Ia ada karena penjaga kunci-diri-sendiri ikut berjalan di sini: menyimpan
     * aturan dari alamat yang tidak tercakup aturan itu sendiri memang ditolak,
     * dan tanpa kolom ini tes ini akan lulus karena galat yang salah.
     *
     * @return array<string, array{0: string, 1: bool, 2: string}>
     */
    public static function patterns(): array
    {
        return [
            'ipv4' => ['203.0.113.10', true, '203.0.113.10'],
            'ipv4 cidr' => ['203.0.113.0/24', true, '203.0.113.77'],
            'ipv6' => ['2001:db8::1', true, '2001:db8::1'],
            'ipv6 cidr' => ['2001:db8::/32', true, '2001:db8:1234::9'],
            'prefix too large for ipv4' => ['203.0.113.0/64', false, '203.0.113.1'],
            'not an address' => ['office-wifi', false, '203.0.113.1'],
            'empty prefix' => ['203.0.113.0/', false, '203.0.113.1'],
            'negative-ish prefix' => ['203.0.113.0/-1', false, '203.0.113.1'],
        ];
    }

    #[DataProvider('patterns')]
    public function test_only_real_addresses_and_ranges_are_accepted(string $pattern, bool $valid, string $from): void
    {
        $response = $this->actingAs(User::factory()->superAdmin()->create())
            ->withServerVariables(['REMOTE_ADDR' => $from])
            ->post('/ip-whitelist', $this->payload(['ip_range' => $pattern]));

        $valid
            ? $response->assertSessionHasNoErrors()
            : $response->assertSessionHasErrors('ip_range');
    }

    public function test_overlapping_ranges_in_the_same_scope_are_blocked(): void
    {
        $actor = User::factory()->superAdmin()->create();
        IpWhitelistRule::factory()->create(['ip_range' => '203.0.113.0/24']);

        $this->actingAs($actor)
            ->withServerVariables(['REMOTE_ADDR' => '203.0.113.1'])
            ->post('/ip-whitelist', $this->payload(['ip_range' => '203.0.113.42']))
            ->assertSessionHasErrors('ip_range');
    }

    /**
     * Irisan hanya dilarang DALAM SATU LINGKUP. Satu blok kantor untuk semua
     * admin plus satu alamat di dalamnya untuk seorang kontraktor dengan masa
     * berlaku sendiri adalah hal yang wajar, bukan duplikat.
     */
    public function test_overlapping_ranges_in_different_scopes_are_allowed(): void
    {
        $actor = User::factory()->superAdmin()->create();
        $contractor = User::factory()->withRole('editor')->create();

        IpWhitelistRule::factory()->create(['ip_range' => '203.0.113.0/24']);

        $this->actingAs($actor)
            ->withServerVariables(['REMOTE_ADDR' => '203.0.113.1'])
            ->post('/ip-whitelist', $this->payload([
                'ip_range' => '203.0.113.42',
                'scope' => IpWhitelistRule::SCOPE_USER,
                'user_id' => $contractor->id,
            ]))
            ->assertSessionHasNoErrors();
    }

    public function test_a_temporary_rule_needs_a_future_date(): void
    {
        $actor = User::factory()->superAdmin()->create();

        $this->actingAs($actor)
            ->post('/ip-whitelist', $this->payload([
                'validity' => IpWhitelistRule::VALIDITY_TEMPORARY,
                'expires_at' => now()->subDay()->format('Y-m-d\TH:i'),
            ]))
            ->assertSessionHasErrors('expires_at');
    }

    public function test_a_role_scope_needs_a_role(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create())
            ->post('/ip-whitelist', $this->payload(['scope' => IpWhitelistRule::SCOPE_ROLE]))
            ->assertSessionHasErrors('role_id');
    }

    /**
     * Sasaran yang tidak dipakai lingkupnya dikosongkan saat disimpan — kalau
     * tidak, `role_id` lama bertahan tanpa terlihat di layar mana pun.
     */
    public function test_switching_scope_clears_the_target_it_no_longer_uses(): void
    {
        $actor = User::factory()->superAdmin()->create();
        $role = Role::findByName('editor', 'web');

        $rule = IpWhitelistRule::factory()->forRole($role->id)->create(['ip_range' => '203.0.113.9']);

        $this->actingAs($actor)
            ->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])
            ->put("/ip-whitelist/{$rule->id}", $this->payload([
                'ip_range' => '203.0.113.9',
                'scope' => IpWhitelistRule::SCOPE_ALL,
            ]))->assertSessionHasNoErrors();

        $this->assertNull($rule->fresh()->role_id);
    }

    // -------------------------------------------------------- lockout guard

    public function test_you_cannot_save_a_rule_that_would_lock_you_out(): void
    {
        $actor = User::factory()->superAdmin()->create();

        $this->actingAs($actor)
            ->withServerVariables(['REMOTE_ADDR' => '198.51.100.7'])
            ->post('/ip-whitelist', $this->payload(['ip_range' => '203.0.113.9']))
            ->assertSessionHasErrors('ip_range');

        $this->assertSame(0, IpWhitelistRule::count());
    }

    /**
     * Bahaya yang sebenarnya BUKAN "mematikan satu-satunya aturan".
     *
     * Kalau aturan terakhir dimatikan, tabelnya jadi kosong bagi pengguna ini
     * dan tidak ada yang dibatasi — ia tetap bisa masuk. Yang mengunci adalah
     * mematikan aturan yang mencocokkan alamatmu SEMENTARA aturan lain masih
     * menyasarmu: penegakan tetap menyala, dan yang tersisa tidak memuatmu.
     *
     * Bedanya halus dan itulah kenapa dikunci di sini: penjaga yang hanya
     * memeriksa "apakah aturan ini yang meloloskanku" akan menolak hal yang
     * aman dan meloloskan hal yang berbahaya.
     */
    public function test_you_cannot_switch_off_the_rule_that_lets_you_in(): void
    {
        $actor = User::factory()->superAdmin()->create();

        $mine = IpWhitelistRule::factory()->create(['ip_range' => '203.0.113.42']);
        IpWhitelistRule::factory()->create(['ip_range' => '198.51.100.0/24']);

        $this->actingAs($actor)
            ->withServerVariables(['REMOTE_ADDR' => '203.0.113.42'])
            ->patch("/ip-whitelist/{$mine->id}/status", ['is_active' => false])
            ->assertSessionHasErrors('is_active');

        $this->assertTrue($mine->fresh()->is_active);
    }

    public function test_you_cannot_delete_the_rule_that_lets_you_in(): void
    {
        $actor = User::factory()->superAdmin()->create();

        $mine = IpWhitelistRule::factory()->create(['ip_range' => '203.0.113.42']);
        IpWhitelistRule::factory()->create(['ip_range' => '198.51.100.0/24']);

        $this->actingAs($actor)
            ->withServerVariables(['REMOTE_ADDR' => '203.0.113.42'])
            ->delete("/ip-whitelist/{$mine->id}")
            ->assertSessionHasErrors('rule');

        $this->assertDatabaseCount('ip_whitelist_rules', 2);
    }

    /** Menghapus satu dari dua aturan yang sama-sama mencakupmu tetap boleh. */
    public function test_deleting_a_redundant_rule_is_still_allowed(): void
    {
        $actor = User::factory()->superAdmin()->create();

        $rule = IpWhitelistRule::factory()->create(['ip_range' => '203.0.113.42']);
        IpWhitelistRule::factory()->forUser($actor->id)->create(['ip_range' => '203.0.113.42']);

        $this->actingAs($actor)
            ->withServerVariables(['REMOTE_ADDR' => '203.0.113.42'])
            ->delete("/ip-whitelist/{$rule->id}")
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('ip_whitelist_rules', 1);
    }

    /**
     * Mematikan aturan TERAKHIR yang menyasarmu tetap boleh, dan itu bukan
     * celah: sesudahnya tidak ada satu pun aturan yang menyasarmu, jadi
     * penegakan berhenti berlaku untukmu — keadaan yang sama persis dengan
     * tabel kosong. Menolaknya akan membuat daftar IP mustahil dibongkar
     * kembali lewat layarnya sendiri.
     */
    public function test_switching_off_the_last_rule_is_allowed_because_it_disables_enforcement(): void
    {
        $actor = User::factory()->superAdmin()->create();
        $only = IpWhitelistRule::factory()->create(['ip_range' => '203.0.113.42']);

        $this->actingAs($actor)
            ->withServerVariables(['REMOTE_ADDR' => '203.0.113.42'])
            ->patch("/ip-whitelist/{$only->id}/status", ['is_active' => false])
            ->assertSessionHasNoErrors();

        $this->assertFalse($only->fresh()->is_active);
    }

    // ------------------------------------------------------------ permission

    public function test_the_module_has_its_own_permission_not_the_user_one(): void
    {
        $this->seed(AccessSeeder::class);

        $userManager = User::factory()->create();
        $userManager->givePermissionTo(['users.view', 'users.create', 'users.update', 'users.delete']);

        $this->actingAs($userManager)->get('/ip-whitelist')->assertForbidden();
    }

    public function test_the_built_in_admin_role_cannot_change_the_whitelist(): void
    {
        $admin = User::factory()->withRole('admin')->create();

        $this->actingAs($admin)->get('/ip-whitelist')->assertForbidden();
    }

    public function test_a_super_admin_can_open_the_list(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create())
            ->get('/ip-whitelist')
            ->assertOk();
    }

    /**
     * `created_by_id` diisi controller dan `updated_by_id` diisi trait —
     * "recorded automatically and are read-only" (`527:8163`).
     */
    public function test_the_author_and_editor_are_recorded_without_being_asked(): void
    {
        $rina = User::factory()->superAdmin()->create(['name' => 'Rina']);

        $this->actingAs($rina)
            ->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
            ->post('/ip-whitelist', $this->payload());

        $rule = IpWhitelistRule::sole();

        $this->assertSame($rina->id, $rule->created_by_id);
        $this->assertSame($rina->id, $rule->updated_by_id);
    }

    /** @param array<string, mixed> $overrides @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Head Office',
            'ip_range' => '203.0.113.10',
            'scope' => IpWhitelistRule::SCOPE_ALL,
            'role_id' => null,
            'user_id' => null,
            'validity' => IpWhitelistRule::VALIDITY_PERMANENT,
            'expires_at' => null,
            'notes' => null,
            'is_active' => true,
        ], $overrides);
    }
}
