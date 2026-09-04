<?php

namespace Tests\Feature\Cms;

use App\Models\NewsArticle;
use App\Models\NewsCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class NewsTest extends TestCase
{
    use RefreshDatabase;

    private function actor(): User
    {
        return User::factory()->superAdmin()->create();
    }

    public function test_the_list_filters_by_status_category_and_search(): void
    {
        $sport = NewsCategory::factory()->create(['name' => 'Tournament']);
        $other = NewsCategory::factory()->create(['name' => 'DWF']);

        NewsArticle::factory()->create(['title' => 'Madrid hosts the final', 'news_category_id' => $sport->id]);
        NewsArticle::factory()->draft()->create(['title' => 'Draft about rules', 'news_category_id' => $other->id]);

        $this->actingAs($this->actor())
            ->get('/news?status=draft')
            ->assertInertia(fn (AssertableInertia $p) => $p->has('articles.data', 1)
                ->where('articles.data.0.title', 'Draft about rules'));

        $this->actingAs($this->actor())
            ->get('/news?category='.$sport->id)
            ->assertInertia(fn (AssertableInertia $p) => $p->has('articles.data', 1)
                ->where('articles.data.0.title', 'Madrid hosts the final'));

        $this->actingAs($this->actor())
            ->get('/news?q=madrid')
            ->assertInertia(fn (AssertableInertia $p) => $p->has('articles.data', 1));
    }

    public function test_posting_now_publishes_and_save_draft_does_not(): void
    {
        Storage::fake('public');
        $category = NewsCategory::factory()->create();

        $payload = fn (string $posting) => [
            'title' => 'Berita '.$posting,
            'news_category_id' => $category->id,
            'body' => '<p>Isi</p>',
            'is_highlighted' => false,
            'posting' => $posting,
            'hero' => UploadedFile::fake()->image('hero.webp', 1920, 800),
            'landscape' => UploadedFile::fake()->image('landscape.webp', 1600, 900),
        ];

        $this->actingAs($this->actor())->post('/news', $payload('now'))->assertRedirect('/news');
        $this->actingAs($this->actor())->post('/news', $payload('draft'))->assertRedirect('/news');

        $this->assertSame('published', NewsArticle::where('title', 'Berita now')->value('status'));

        $draft = NewsArticle::where('title', 'Berita draft')->first();
        $this->assertSame('draft', $draft->status);
        $this->assertNull($draft->published_at);
    }

    public function test_scheduling_requires_a_date(): void
    {
        Storage::fake('public');
        $category = NewsCategory::factory()->create();

        $this->actingAs($this->actor())
            ->post('/news', [
                'title' => 'Terjadwal',
                'news_category_id' => $category->id,
                'body' => '<p>Isi</p>',
                'is_highlighted' => false,
                'posting' => 'schedule',
                'hero' => UploadedFile::fake()->image('hero.webp', 1920, 800),
                'landscape' => UploadedFile::fake()->image('landscape.webp', 1600, 900),
            ])
            ->assertSessionHasErrors('published_at');
    }

    public function test_the_body_is_purified_before_it_is_stored(): void
    {
        Storage::fake('public');
        $category = NewsCategory::factory()->create();

        $this->actingAs($this->actor())->post('/news', [
            'title' => 'Dengan skrip',
            'news_category_id' => $category->id,
            'body' => '<p>Aman</p><script>alert(1)</script><img src=x onerror=alert(1)>',
            'is_highlighted' => false,
            'posting' => 'now',
            'hero' => UploadedFile::fake()->image('hero.webp', 1920, 800),
            'landscape' => UploadedFile::fake()->image('landscape.webp', 1600, 900),
        ]);

        $body = NewsArticle::where('title', 'Dengan skrip')->value('body');

        $this->assertStringNotContainsString('<script', $body);
        $this->assertStringNotContainsString('onerror', $body);
        $this->assertStringContainsString('Aman', $body);
    }

    public function test_a_small_image_is_rejected(): void
    {
        Storage::fake('public');
        $category = NewsCategory::factory()->create();

        $this->actingAs($this->actor())
            ->post('/news', [
                'title' => 'Gambar kecil',
                'news_category_id' => $category->id,
                'body' => '<p>Isi</p>',
                'is_highlighted' => false,
                'posting' => 'now',
                'hero' => UploadedFile::fake()->image('hero.webp', 640, 480),
            ])
            ->assertSessionHasErrors('hero');
    }

    public function test_editing_without_a_new_image_keeps_the_existing_one(): void
    {
        Storage::fake('public');
        $article = NewsArticle::factory()->create(['hero_image_path' => 'news/lama.jpg']);

        $this->actingAs($this->actor())->put("/news/{$article->id}", [
            'title' => 'Judul baru',
            'news_category_id' => $article->news_category_id,
            'body' => '<p>Isi</p>',
            'is_highlighted' => false,
            'posting' => 'now',
        ])->assertRedirect('/news');

        $this->assertSame('news/lama.jpg', $article->fresh()->hero_image_path);
    }

    public function test_deleting_an_article_removes_its_images(): void
    {
        Storage::fake('public');
        $path = UploadedFile::fake()->image('hero.webp', 1920, 800)->store('news', 'public');
        $article = NewsArticle::factory()->create(['hero_image_path' => $path]);

        Storage::disk('public')->assertExists($path);

        $this->actingAs($this->actor())->delete("/news/{$article->id}");

        Storage::disk('public')->assertMissing($path);
        $this->assertDatabaseMissing('news_articles', ['id' => $article->id]);
    }

    public function test_a_category_in_use_cannot_be_deleted(): void
    {
        $category = NewsCategory::factory()->create();
        NewsArticle::factory()->create(['news_category_id' => $category->id]);

        $this->actingAs($this->actor())
            ->delete("/news/categories/{$category->id}")
            ->assertSessionHasErrors('category');

        $this->assertDatabaseHas('news_categories', ['id' => $category->id]);
    }

    // --- Layar "Manage Category" (`433:6116`) --------------------------------

    public function test_the_manage_screen_reports_how_often_each_category_is_used(): void
    {
        // Angka itu yang menentukan tombol hapus mati atau hidup, jadi ia harus
        // benar-benar dihitung, bukan diperkirakan dari relasi yang dimuat.
        $used = NewsCategory::factory()->create(['name' => 'Tournament']);
        $free = NewsCategory::factory()->create(['name' => 'DWF']);
        NewsArticle::factory()->count(2)->create(['news_category_id' => $used->id]);

        $this->actingAs($this->actor())
            ->get('/news/categories')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->component('News/Categories/Index')
                ->has('categories', 2));

        $rows = collect(
            $this->actingAs($this->actor())
                ->get('/news/categories')
                ->viewData('page')['props']['categories']
        )->keyBy('name');

        $this->assertSame(2, $rows['Tournament']['usage']);
        $this->assertSame(0, $rows['DWF']['usage']);
    }

    public function test_adding_and_renaming_keep_you_on_the_same_screen(): void
    {
        // Menyunting di tempat berarti tidak berpindah halaman sama sekali.
        $this->actingAs($this->actor())
            ->from('/news/categories')
            ->post('/news/categories', ['name' => 'Baru', 'is_active' => true])
            ->assertRedirect('/news/categories');

        $category = NewsCategory::where('name', 'Baru')->first();
        $this->assertNotNull($category);

        $this->actingAs($this->actor())
            ->from('/news/categories')
            ->put("/news/categories/{$category->id}", ['name' => 'Diubah', 'is_active' => true])
            ->assertRedirect('/news/categories');

        $this->assertSame('Diubah', $category->fresh()->name);
    }

    public function test_status_can_be_switched_without_entering_edit_mode(): void
    {
        $category = NewsCategory::factory()->create(['is_active' => true]);

        $this->actingAs($this->actor())
            ->from('/news/categories')
            ->put("/news/categories/{$category->id}", ['name' => $category->name, 'is_active' => false]);

        $this->assertFalse($category->fresh()->is_active);
    }

    public function test_the_separate_list_and_form_screens_are_gone(): void
    {
        // Ketiganya dulu melakukan hal yang sama; yang paling sederhana sudah
        // mencakup dua lainnya.
        $this->actingAs($this->actor())->get('/news/categories/create')->assertNotFound();
        $this->actingAs($this->actor())->get('/news/categories/manage')->assertNotFound();
    }

    public function test_a_duplicate_name_is_refused_with_a_message_on_the_field(): void
    {
        NewsCategory::factory()->create(['name' => 'Tournament']);

        $this->actingAs($this->actor())
            ->post('/news/categories', ['name' => 'Tournament', 'is_active' => true])
            ->assertSessionHasErrors('name');
    }

    public function test_the_categories_route_is_not_swallowed_by_the_article_route(): void
    {
        // `/news/categories` harus mendarat di daftar kategori, bukan dicocokkan
        // sebagai `/news/{article}` dengan id "categories".
        $this->actingAs($this->actor())
            ->get('/news/categories')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p->component('News/Categories/Index'));
    }

    public function test_an_article_can_be_read_without_opening_its_form(): void
    {
        $category = NewsCategory::factory()->create(['name' => 'Tournament']);
        $article = NewsArticle::factory()->create([
            'title' => 'Madrid hosts the final',
            'news_category_id' => $category->id,
        ]);

        $this->actingAs($this->actor())
            ->get("/news/{$article->id}")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p->component('News/Articles/Show')
                ->where('article.title', 'Madrid hosts the final')
                ->where('article.category', 'Tournament')
                ->has('article.visibility'));
    }

    public function test_create_is_not_read_as_an_article_id(): void
    {
        // `/news/{article}` dibatasi angka. Tanpa itu "create" ikut cocok dan
        // formulir tambah berubah jadi 404 model yang tidak ditemukan.
        $this->actingAs($this->actor())
            ->get('/news/create')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p->component('News/Articles/Form'));
    }

    public function test_visibility_can_be_changed_straight_from_the_list(): void
    {
        $article = NewsArticle::factory()->draft()->create();

        $this->actingAs($this->actor())
            ->patch("/news/{$article->id}/visibility", ['status' => 'published'])
            ->assertRedirect();

        $article->refresh();

        $this->assertSame('published', $article->status);
        // Yang belum pernah punya tanggal tayang mendapatkannya sekarang; tanpa
        // itu kolom Published tetap kosong di baris yang jelas sudah tayang.
        $this->assertNotNull($article->published_at);
    }

    public function test_scheduling_from_the_list_is_refused_without_a_schedule(): void
    {
        $article = NewsArticle::factory()->create(['published_at' => now()->subDay()]);

        $this->actingAs($this->actor())
            ->patch("/news/{$article->id}/visibility", ['status' => 'scheduled'])
            ->assertSessionHasErrors('status');

        $this->assertNotSame('scheduled', $article->refresh()->status);
    }

    public function test_an_unpublished_article_is_not_live(): void
    {
        $article = NewsArticle::factory()->create();

        $this->actingAs($this->actor())
            ->patch("/news/{$article->id}/visibility", ['status' => 'unpublished'])
            ->assertRedirect();

        $this->assertSame('unpublished', $article->refresh()->visibility);
        $this->assertFalse(NewsArticle::query()->live()->whereKey($article->id)->exists());
    }

    public function test_the_highlight_toggle_records_who_changed_it(): void
    {
        $editor = User::factory()->superAdmin()->create(['name' => 'Rina']);
        $article = NewsArticle::factory()->create(['is_highlighted' => false]);

        $this->actingAs($editor)
            ->patch("/news/{$article->id}/highlight", ['is_highlighted' => true])
            ->assertRedirect();

        $article->refresh();

        $this->assertTrue($article->is_highlighted);
        $this->assertSame($editor->id, $article->updated_by_id);
    }

    public function test_the_export_follows_the_active_filters(): void
    {
        $wanted = NewsCategory::factory()->create(['name' => 'Tournament']);
        $other = NewsCategory::factory()->create(['name' => 'DWF']);

        NewsArticle::factory()->create(['title' => 'Madrid hosts the final', 'news_category_id' => $wanted->id]);
        NewsArticle::factory()->create(['title' => 'Something else', 'news_category_id' => $other->id]);

        $response = $this->actingAs($this->actor())->get('/news/export?category='.$wanted->id);

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $csv = $response->streamedContent();

        $this->assertStringContainsString('Madrid hosts the final', $csv);
        $this->assertStringNotContainsString('Something else', $csv);
    }

    public function test_export_is_not_read_as_an_article_id(): void
    {
        $this->actingAs($this->actor())->get('/news/export')->assertOk();
    }

    public function test_both_images_are_required_when_creating(): void
    {
        Storage::fake('public');
        $category = NewsCategory::factory()->create();

        // Desain `252:4480` memberi tanda wajib pada KEDUANYA. Saat menyunting
        // keduanya opsional — kalau tidak, memperbaiki satu typo di judul
        // memaksa mengunggah ulang gambar yang sudah ada.
        $this->actingAs($this->actor())->post('/news', [
            'title' => 'Tanpa gambar',
            'news_category_id' => $category->id,
            'body' => '<p>Isi.</p>',
            'is_highlighted' => false,
            'posting' => 'now',
        ])->assertSessionHasErrors(['hero', 'landscape']);
    }

    public function test_only_webp_is_accepted(): void
    {
        Storage::fake('public');
        $category = NewsCategory::factory()->create();

        // Ukuran dan rasionya BENAR; yang salah cuma formatnya. Tanpa
        // memisahkan keduanya, tes ini akan tetap hijau kalau suatu saat aturan
        // formatnya hilang tapi aturan dimensinya bertahan.
        $this->actingAs($this->actor())->post('/news', [
            'title' => 'Format salah',
            'news_category_id' => $category->id,
            'body' => '<p>Isi.</p>',
            'is_highlighted' => false,
            'posting' => 'now',
            'hero' => UploadedFile::fake()->image('hero.png', 1920, 800),
            'landscape' => UploadedFile::fake()->image('landscape.png', 1600, 900),
        ])->assertSessionHasErrors(['hero', 'landscape']);
    }

    public function test_the_ratio_from_the_design_is_enforced(): void
    {
        Storage::fake('public');
        $category = NewsCategory::factory()->create();

        // Cukup besar, format benar, rasio salah — 1920×1080 bukan 12:5.
        $this->actingAs($this->actor())->post('/news', [
            'title' => 'Rasio salah',
            'news_category_id' => $category->id,
            'body' => '<p>Isi.</p>',
            'is_highlighted' => false,
            'posting' => 'now',
            'hero' => UploadedFile::fake()->image('hero.webp', 1920, 1080),
            'landscape' => UploadedFile::fake()->image('landscape.webp', 1600, 900),
        ])->assertSessionHasErrors('hero');
    }

    public function test_a_bigger_image_with_the_right_ratio_is_accepted(): void
    {
        Storage::fake('public');
        $category = NewsCategory::factory()->create();

        // 3840×1600 mengisi kotak hero sama baiknya dengan 1920×800 dan lebih
        // tajam di layar retina. Menolaknya berarti memaksa orang MENGECILKAN
        // gambar yang sudah benar.
        $this->actingAs($this->actor())->post('/news', [
            'title' => 'Dua kali lipat',
            'news_category_id' => $category->id,
            'body' => '<p>Isi.</p>',
            'is_highlighted' => false,
            'posting' => 'now',
            'hero' => UploadedFile::fake()->image('hero.webp', 3840, 1600),
            'landscape' => UploadedFile::fake()->image('landscape.webp', 3200, 1800),
        ])->assertRedirect('/news');

        $this->assertSame(1, NewsArticle::query()->count());
    }

    public function test_the_editor_keeps_headings_strike_highlight_and_images(): void
    {
        Storage::fake('public');
        $category = NewsCategory::factory()->create();

        $this->actingAs($this->actor())->post('/news', [
            'title' => 'Isi kaya',
            'news_category_id' => $category->id,
            'body' => '<h2>Bab</h2><p><s>coret</s> <mark>sorot</mark></p>'
                .'<p><img src="/storage/editor/a.webp" alt="gambar"></p>',
            'is_highlighted' => false,
            'posting' => 'now',
            'hero' => UploadedFile::fake()->image('hero.webp', 1920, 800),
            'landscape' => UploadedFile::fake()->image('landscape.webp', 1600, 900),
        ])->assertRedirect('/news');

        $body = NewsArticle::query()->firstOrFail()->body;

        /*
         * Keempatnya adalah tombol yang MEMANG ADA di toolbar editor, dan
         * daftar izin bawaan `mews/purifier` membuang semuanya tanpa satu pun
         * pesan galat — orang menekan "Heading 2", menyimpan, dan judulnya
         * kembali jadi paragraf. Tes ini yang menahan `config/purifier.php`
         * kalau suatu saat ia dipublikasikan ulang dari vendor.
         */
        $this->assertStringContainsString('<h2>Bab</h2>', $body);
        $this->assertStringContainsString('<s>coret</s>', $body);
        $this->assertStringContainsString('<mark>sorot</mark>', $body);
        $this->assertStringContainsString('/storage/editor/a.webp', $body);
    }

    public function test_the_portrait_image_is_gone_end_to_end(): void
    {
        Storage::fake('public');
        $category = NewsCategory::factory()->create();

        $this->actingAs($this->actor())->post('/news', [
            'title' => 'Tanpa potret',
            'news_category_id' => $category->id,
            'body' => '<p>Isi.</p>',
            'is_highlighted' => false,
            'posting' => 'now',
            'hero' => UploadedFile::fake()->image('hero.webp', 1920, 800),
            'landscape' => UploadedFile::fake()->image('landscape.webp', 1600, 900),
            'portrait' => UploadedFile::fake()->image('portrait.webp', 800, 1200),
        ])->assertRedirect('/news');

        $article = NewsArticle::query()->firstOrFail();

        // Kolomnya sudah dibuang lewat migrasi; berkas potret yang tetap dikirim
        // diabaikan diam-diam, bukan disimpan ke kolom yang tidak ada.
        $this->assertArrayNotHasKey('portrait_image_path', $article->getAttributes());
        $this->assertNotNull($article->hero_image_path);
    }

    /**
     * Batas ukuran gambar 1 MB, bukan 2 MB.
     *
     * Diturunkan 2026-09-03 karena situs publik memakai `provider: "none"` di
     * `@nuxt/image` — tidak ada yang mengecilkan gambar, jadi byte yang
     * diunggah adalah byte yang dikirim ke setiap pengunjung. Diukur dengan
     * `cwebp`: hero 1920x800 muat lapang di bawah 1 MB bahkan pada q95;
     * yang ditolak adalah 4K yang belum dikecilkan.
     */
    public function test_an_image_over_the_size_cap_is_refused(): void
    {
        Storage::fake('public');
        $category = NewsCategory::factory()->create();

        $tooBig = UploadedFile::fake()->image('hero.webp', 1920, 800)->size(1500);

        $this->actingAs(User::factory()->superAdmin()->create())->post('/news', [
            'news_category_id' => $category->id,
            'title' => 'Gambar terlalu berat',
            'body' => '<p>Isi.</p>',
            'posting' => 'now',
            'hero' => $tooBig,
            'landscape' => UploadedFile::fake()->image('landscape.webp', 1600, 900),
        ])->assertSessionHasErrors('hero');
    }

    /** Dan yang di bawah batas tetap lolos — supaya batasnya bukan sekadar penolakan. */
    public function test_an_image_within_the_cap_is_accepted(): void
    {
        Storage::fake('public');
        $category = NewsCategory::factory()->create();

        $this->actingAs(User::factory()->superAdmin()->create())->post('/news', [
            'news_category_id' => $category->id,
            'title' => 'Gambar pas',
            'body' => '<p>Isi.</p>',
            'posting' => 'now',
            'is_highlighted' => false,
            'hero' => UploadedFile::fake()->image('hero.webp', 1920, 800)->size(900),
            'landscape' => UploadedFile::fake()->image('landscape.webp', 1600, 900)->size(400),
        ])->assertSessionHasNoErrors();
    }
}
