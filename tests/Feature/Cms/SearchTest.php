<?php

namespace Tests\Feature\Cms;

use App\Models\Document;
use App\Models\NewsArticle;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pencarian lintas modul di topbar — `/search`.
 *
 * Dua hal yang dijaga di sini lebih penting daripada "hasilnya ketemu":
 *
 *   1. **Izin disaring per kelompok.** Satu kotak pencarian tidak boleh jadi
 *      jalan memutar untuk membaca judul dari modul yang seluruh sidebarnya
 *      sudah disembunyikan — judul adalah isi.
 *   2. **Tiap hasil menaut ke tempat yang bisa dibuka orangnya.** Sebagian
 *      modul hanya punya layar sunting, dan menautkannya untuk yang cuma boleh
 *      membaca menghasilkan daftar yang setengahnya 403 — yang terbaca sebagai
 *      "pencariannya rusak", bukan "saya tidak punya izin".
 */
class SearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_is_sent_to_the_login_screen(): void
    {
        $this->get('/search?q=apa')->assertRedirect('/login');
    }

    /** Satu huruf cocok dengan hampir seluruh tabel — itu bukan jawaban. */
    public function test_a_term_shorter_than_two_characters_searches_nothing(): void
    {
        NewsArticle::factory()->create(['title' => 'Anti-Doping Update']);

        $this->actingAs(User::factory()->superAdmin()->create())
            ->getJson('/search?q=A')
            ->assertOk()
            ->assertExactJson(['groups' => []]);
    }

    /** PostgreSQL peka huruf besar-kecil pada `LIKE`; yang dipakai `ILIKE`. */
    public function test_the_search_ignores_letter_case(): void
    {
        NewsArticle::factory()->create(['title' => 'DWF Annual Congress']);

        $this->actingAs(User::factory()->superAdmin()->create())
            ->getJson('/search?q=dwf annual')
            ->assertOk()
            ->assertJsonPath('groups.0.module', 'news')
            ->assertJsonPath('groups.0.items.0.label', 'DWF Annual Congress');
    }

    /** Layar baca ada untuk News, jadi hasilnya menaut ke sana apa adanya. */
    public function test_a_module_with_a_read_screen_links_straight_to_the_record(): void
    {
        $article = NewsArticle::factory()->create(['title' => 'Congress Recap']);

        $this->actingAs(User::factory()->superAdmin()->create())
            ->getJson('/search?q=Congress')
            ->assertJsonPath('groups.0.items.0.href', '/news/'.$article->id);
    }

    /**
     * Turnamen hanya punya layar SUNTING. Yang boleh menyunting diantar ke
     * sana; yang tidak diantar ke daftarnya dengan kata kuncinya sudah terisi —
     * bukan ke halaman yang akan menolaknya.
     */
    public function test_an_edit_only_module_never_links_somewhere_forbidden(): void
    {
        $tournament = Tournament::factory()->create(['name' => 'Jakarta Open']);

        $this->actingAs(User::factory()->withRole('editor')->create())
            ->getJson('/search?q=Jakarta')
            ->assertJsonPath('groups.0.items.0.href', '/tournaments/'.$tournament->id.'/edit');

        $this->actingAs(User::factory()->withRole('viewer')->create())
            ->getJson('/search?q=Jakarta')
            ->assertJsonPath('groups.0.items.0.href', '/tournaments?q=Jakarta');
    }

    /**
     * Modul yang tidak boleh dilihat tidak ikut dicari sama sekali.
     *
     * `viewer` tidak punya `users.view`, jadi kelompok User Management tidak
     * boleh muncul — walau namanya cocok.
     */
    public function test_a_module_you_cannot_view_is_not_searched(): void
    {
        User::factory()->create(['name' => 'Budi Hartono', 'email' => 'budi@dwf.test']);
        $viewer = User::factory()->withRole('viewer')->create();

        $modules = collect(
            $this->actingAs($viewer)->getJson('/search?q=Budi')->json('groups'),
        )->pluck('module');

        $this->assertFalse($modules->contains('users'));

        // Dan super admin memang menemukannya — pembanding yang membuktikan
        // hasilnya hilang karena izin, bukan karena datanya tidak cocok.
        $modules = collect(
            $this->actingAs(User::factory()->superAdmin()->create())
                ->getJson('/search?q=Budi')->json('groups'),
        )->pluck('module');

        $this->assertTrue($modules->contains('users'));
    }

    /** Satu kelompok tidak boleh berubah jadi halaman daftar kedua. */
    public function test_each_group_is_capped(): void
    {
        Document::factory()->count(9)->create(['title' => 'Regulasi Turnamen']);

        $this->actingAs(User::factory()->superAdmin()->create())
            ->getJson('/search?q=Regulasi')
            ->assertJsonCount(5, 'groups.0.items');
    }
}
