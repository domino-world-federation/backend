<?php

namespace Tests\Feature\Cms;

use App\Models\Faq;
use App\Models\FaqCategory;
use App\Models\FaqPlacement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class FaqTest extends TestCase
{
    use RefreshDatabase;

    private ?FaqCategory $category = null;

    /** Satu kategori per tes — slug-nya unik, jadi ia tidak boleh dibuat dua kali. */
    private function category(): FaqCategory
    {
        return $this->category ??= FaqCategory::query()->create([
            'name' => 'General', 'slug' => 'general', 'is_active' => true, 'position' => 1,
        ]);
    }

    /** @param array<int, string> $pages */
    private function make(array $pages, bool $active = true): Faq
    {
        $faq = Faq::query()->create([
            'faq_category_id' => $this->category()->id,
            'question' => 'Q'.uniqid(),
            'answer' => '<p>A</p>',
            'is_active' => $active,
            'position' => Faq::nextPosition(),
        ]);

        foreach (array_values($pages) as $index => $page) {
            $faq->placements()->create([
                'page' => $page,
                'position' => FaqPlacement::nextPositionOn($page),
            ]);
        }

        return $faq;
    }

    public function test_the_answer_is_purified(): void
    {
        $category = $this->category();

        $this->actingAs(User::factory()->superAdmin()->create())->post('/faq', [
            'faq_category_id' => $category->id,
            'question' => 'Berbahaya',
            'answer' => '<p>Aman</p><script>alert(1)</script>',
            'pages' => [],
            'is_active' => true,
        ]);

        $answer = Faq::where('question', 'Berbahaya')->value('answer');
        $this->assertStringNotContainsString('<script', $answer);
    }

    public function test_reordering_writes_sequential_positions(): void
    {
        $a = $this->make([]);
        $b = $this->make([]);

        $this->actingAs(User::factory()->superAdmin()->create())
            ->put('/faq/reorder', ['ids' => [$b->id, $a->id]])
            ->assertRedirect();

        $this->assertSame(1, $b->fresh()->position);
        $this->assertSame(2, $a->fresh()->position);
    }

    // ------------------------------------------- urutan milik HALAMAN

    /**
     * Inti perbaikan 2026-09-03, dan bug yang melahirkannya.
     *
     * Sebelum ini `faqs.position` cuma satu angka yang dipakai bersama, jadi
     * mengurutkan Home ikut mengurutkan Domino — tanpa satu pun layar yang
     * memberi tahu. Tes ini gagal kalau peringkatnya kembali jadi milik FAQ.
     */
    public function test_each_page_keeps_its_own_order(): void
    {
        $a = $this->make(['home', 'domino']);
        $b = $this->make(['home', 'domino']);

        $this->actingAs(User::factory()->superAdmin()->create())
            ->put('/faq/pages', ['page' => 'home', 'ids' => [$b->id, $a->id]])
            ->assertRedirect();

        $this->assertSame([$b->id, $a->id], $this->orderOn('home'));
        $this->assertSame([$a->id, $b->id], $this->orderOn('domino'));
    }

    public function test_saving_a_page_removes_what_was_taken_off_it(): void
    {
        $kept = $this->make(['home']);
        $dropped = $this->make(['home']);

        $this->actingAs(User::factory()->superAdmin()->create())
            ->put('/faq/pages', ['page' => 'home', 'ids' => [$kept->id]])
            ->assertRedirect();

        $this->assertSame([$kept->id], $this->orderOn('home'));
        // FAQ-nya sendiri TIDAK ikut terhapus — ia cuma tidak lagi di halaman itu.
        $this->assertModelExists($dropped);
    }

    public function test_a_page_still_refuses_a_fourth_question(): void
    {
        $ids = collect(range(1, 4))->map(fn () => $this->make([])->id)->all();

        $this->actingAs(User::factory()->superAdmin()->create())
            ->put('/faq/pages', ['page' => 'home', 'ids' => $ids])
            ->assertSessionHasErrors('ids');

        $this->assertSame([], $this->orderOn('home'));
    }

    /**
     * Menyunting jawaban tidak boleh memindahkan pertanyaannya.
     *
     * Kalau penempatan ditulis ulang dari nol tiap simpan, tiap pembetulan
     * salah ketik akan melempar pertanyaannya ke urutan terakhir di halamannya.
     */
    public function test_editing_a_question_keeps_its_place_on_the_page(): void
    {
        $first = $this->make(['home']);
        $second = $this->make(['home']);

        $this->actingAs(User::factory()->superAdmin()->create())
            ->put("/faq/{$first->id}", [
                'faq_category_id' => $this->category()->id,
                'question' => 'Diperbaiki',
                'answer' => '<p>A</p>',
                'pages' => ['home'],
                'is_active' => true,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame([$first->id, $second->id], $this->orderOn('home'));
    }

    public function test_deleting_a_question_takes_its_placements_with_it(): void
    {
        $faq = $this->make(['home']);

        $this->actingAs(User::factory()->superAdmin()->create())
            ->delete("/faq/{$faq->id}")
            ->assertRedirect();

        $this->assertDatabaseCount('faq_placements', 0);
    }

    public function test_the_pages_screen_shows_each_page_separately(): void
    {
        $this->make(['home']);
        $this->make(['domino']);

        $this->actingAs(User::factory()->superAdmin()->create())
            ->get('/faq/pages')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->component('Faq/Pages')
                ->has('pages', 3)
                ->has('pages.0.faqs', 1)
                ->has('library'));
    }

    // ------------------------------------------------------------ API

    public function test_the_api_reads_the_page_order_not_the_global_one(): void
    {
        $a = $this->make(['home']);
        $b = $this->make(['home']);

        $this->actingAs(User::factory()->superAdmin()->create())
            ->put('/faq/pages', ['page' => 'home', 'ids' => [$b->id, $a->id]]);

        $body = $this->getJson('/api/v1/faqs?placement=home')->assertOk()->json();

        $this->assertSame([(string) $b->id, (string) $a->id], array_column($body, 'id'));
    }

    /** Tanpa `?page=`, seluruh daftar keluar — itu yang dibaca `/page/faq`. */
    public function test_the_api_without_a_page_returns_everything(): void
    {
        $this->make(['home']);
        $this->make([]);

        $this->assertCount(2, $this->getJson('/api/v1/faqs')->assertOk()->json());
    }

    /**
     * Tab kategori di halaman FAQ lengkap lahir dari pertanyaannya sendiri,
     * jadi tanpa field ini situs publik tidak punya cara mengisinya.
     */
    public function test_the_api_sends_the_category(): void
    {
        $this->make([]);

        $body = $this->getJson('/api/v1/faqs')->assertOk()->json();

        $this->assertSame('general', $body[0]['category']['slug']);
        $this->assertSame('General', $body[0]['category']['name']);
    }

    public function test_the_api_hides_inactive_questions(): void
    {
        $this->make(['home'], active: false);

        $this->assertSame([], $this->getJson('/api/v1/faqs?placement=home')->assertOk()->json());
    }

    /** @return array<int, int> */
    private function orderOn(string $page): array
    {
        return FaqPlacement::query()
            ->where('page', $page)
            ->orderBy('position')
            ->pluck('faq_id')
            ->all();
    }

    /**
     * Formulir FAQ TIDAK bisa memindahkan pertanyaan antar halaman.
     *
     * "Apply to Page" dibuang dari sana pada 2026-09-03: sederet centang tidak
     * bisa mengatakan peringkat, jadi ia selalu menempel di ujung — diam-diam.
     * Field yang dikirim tangan pun harus diabaikan, kalau tidak jalur lamanya
     * hidup kembali lewat pintu belakang.
     */
    public function test_the_question_form_cannot_change_placements(): void
    {
        $faq = $this->make(['home']);

        $this->actingAs(User::factory()->superAdmin()->create())
            ->put("/faq/{$faq->id}", [
                'faq_category_id' => $this->category()->id,
                'question' => 'Diperbarui',
                'answer' => '<p>A</p>',
                'pages' => ['domino', 'tournament'],
                'is_active' => true,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame([$faq->id], $this->orderOn('home'));
        $this->assertSame([], $this->orderOn('domino'));
    }

    public function test_an_unknown_page_is_rejected(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create())
            ->put('/faq/pages', ['page' => 'halaman-yang-tidak-ada', 'ids' => []])
            ->assertSessionHasErrors('page');
    }

    /**
     * Pertanyaan nonaktif TETAP memakai slotnya.
     *
     * Membalik aturan lama ("nonaktif tidak dihitung"), yang masuk akal selama
     * kuotanya cuma angka di balik centang. Sekarang halamannya digambar utuh:
     * daftar berisi lima baris sementara penghitungnya menulis "3 dari 3"
     * adalah layar yang membantah dirinya sendiri. Yang nonaktif ditandai dan
     * bisa dikeluarkan dengan satu klik di layar yang sama.
     */
    public function test_an_inactive_question_still_holds_its_slot(): void
    {
        $ids = [
            $this->make([])->id,
            $this->make([])->id,
            $this->make([], active: false)->id,
            $this->make([])->id,
        ];

        $this->actingAs(User::factory()->superAdmin()->create())
            ->put('/faq/pages', ['page' => 'home', 'ids' => $ids])
            ->assertSessionHasErrors('ids');
    }

    public function test_the_same_question_cannot_be_placed_twice_on_one_page(): void
    {
        $faq = $this->make([]);

        $this->actingAs(User::factory()->superAdmin()->create())
            ->put('/faq/pages', ['page' => 'home', 'ids' => [$faq->id, $faq->id]])
            ->assertSessionHasErrors('ids.1');
    }
}
