<?php

namespace Tests\Feature\Api;

use App\Models\Document;
use App\Models\NewsArticle;
use App\Models\SiteSetting;
use Database\Seeders\FrontendContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Konvensi API publik — dijaga di satu tempat, bukan diingat orang.
 *
 * Berkas ini tidak menguji satu fitur pun. Ia menguji hal-hal yang HARUS SAMA
 * di seluruh endpoint, dan yang paling gampang luntur satu per satu: bentuk
 * galat, gaya penamaan kunci, arti `?limit=`, dan bentuk daftar.
 *
 * Kalau endpoint ke-30 nanti ditulis dengan gaya sendiri, di sinilah
 * ketahuannya — bukan di halaman situs publik yang tiba-tiba kosong.
 */
class ApiConventionTest extends TestCase
{
    use RefreshDatabase;

    // ------------------------------------------------------ bentuk galat

    /**
     * Satu bentuk untuk semua: `{ "message": "…" }`.
     *
     * Dan TANPA isi perut aplikasi. Pesan bawaan Laravel membalas "No query
     * results for model [App\Models\NewsArticle]" — bahkan dengan
     * `APP_DEBUG=false` — yang memberi tahu dunia nama kelas, namespace, dan
     * bahwa ini Laravel dengan route model binding.
     */
    public function test_a_missing_record_answers_a_clean_404(): void
    {
        $body = $this->getJson('/api/v1/news/tidak-ada')->assertNotFound()->json();

        $this->assertSame(['message' => 'Not found.'], $body);
    }

    public function test_an_unknown_route_answers_the_same_404(): void
    {
        $this->getJson('/api/v1/ngawur')
            ->assertNotFound()
            ->assertExactJson(['message' => 'Not found.']);
    }

    public function test_a_wrong_method_answers_405(): void
    {
        $this->postJson('/api/v1/news')
            ->assertStatus(405)
            ->assertExactJson(['message' => 'Method not allowed.']);
    }

    /** 422 tetap bentuk bawaan Laravel: `message` + `errors` per field. */
    public function test_validation_keeps_the_field_errors(): void
    {
        $this->postJson('/api/v1/newsletter', ['email' => 'bukan-email'])
            ->assertStatus(422)
            ->assertJsonStructure(['message', 'errors' => ['email']]);
    }

    /**
     * Backoffice TIDAK ikut berubah bentuk.
     *
     * Penyeragaman di atas dibatasi `api/*` dengan sengaja: halaman galat
     * Inertia yang diseragamkan jadi JSON akan tampil sebagai teks mentah di
     * layar orang.
     */
    public function test_the_backoffice_still_renders_its_own_error_pages(): void
    {
        $this->get('/halaman-yang-tidak-ada')
            ->assertNotFound()
            ->assertDontSee('"message"', escape: false);
    }

    // ------------------------------------------------------------ limit

    /** @return array<string, array{0: string}> */
    public static function listsWithLimit(): array
    {
        return ['news' => ['/api/v1/news'], 'resources' => ['/api/v1/resources'], 'gallery' => ['/api/v1/gallery']];
    }

    /**
     * `?limit=` dijepit, tidak diturut mentah-mentah.
     *
     * Sebelum diseragamkan, dua endpoint memakai `min((int) $x, 48)` — yang
     * MELOLOSKAN nol dan angka negatif. `limit(0)` mengembalikan daftar kosong,
     * dan yang terbaca di halaman adalah "tidak ada isinya", bukan "permintaan
     * Anda salah".
     */
    #[DataProvider('listsWithLimit')]
    public function test_a_zero_or_negative_limit_never_empties_a_list(string $url): void
    {
        NewsArticle::factory()->count(3)->create(['published_at' => now()->subDay()]);

        foreach (['0', '-5'] as $limit) {
            $this->getJson("{$url}?limit={$limit}")->assertOk();
        }

        $this->assertNotEmpty($this->getJson('/api/v1/news?limit=0')->json());
    }

    public function test_a_huge_limit_is_capped(): void
    {
        NewsArticle::factory()->count(3)->create(['published_at' => now()->subDay()]);

        $this->assertLessThanOrEqual(
            48,
            count($this->getJson('/api/v1/news?limit=9999')->assertOk()->json()),
        );
    }

    // -------------------------------------------------- bentuk response

    /**
     * Kunci response camelCase, TANPA kecuali.
     *
     * `/settings` sempat jadi pengecualian karena kuncinya diambil apa adanya
     * dari kolom database (`primary_email`). Satu response yang berbeda
     * gayanya memaksa pemakainya mengingat pengecualian.
     */
    public function test_every_response_key_is_camel_case(): void
    {
        $this->seed(FrontendContentSeeder::class);

        foreach (['/api/v1/news', '/api/v1/settings', '/api/v1/members', '/api/v1/tournaments', '/api/v1/faqs'] as $url) {
            foreach ($this->flatKeys($this->getJson($url)->assertOk()->json()) as $key) {
                $this->assertDoesNotMatchRegularExpression(
                    '/[_-]/',
                    $key,
                    "Kunci `{$key}` di {$url} bukan camelCase.",
                );
            }
        }
    }

    /**
     * `/settings` mengirim naskah publik, BUKAN config internal.
     *
     * `form_recipient_email` ada di kelompok pengaturan yang sama — ia yang
     * menentukan ke mana pemberitahuan formulir dirutekan — dan sampai
     * 2026-09-05 ia ikut terkirim ke internet karena endpointnya menyapu
     * seluruh kelompok. Nilainya kebetulan sama dengan alamat yang memang
     * tayang, jadi tidak ada yang melihatnya; mengarahkannya ke kotak masuk
     * internal akan menerbitkannya tanpa satu pun tanda.
     *
     * Tes ini menjaga arahnya: pengaturan baru di kelompok `contact` TIDAK
     * tayang sampai seseorang memasukkannya ke daftar putih.
     */
    public function test_settings_does_not_publish_internal_configuration(): void
    {
        $this->seed(FrontendContentSeeder::class);

        SiteSetting::putMany([
            'primary_email' => 'contact@dwf.test',
            'footer_address_label' => 'Headquarters, Lausanne, CH',
            'social_instagram' => 'dominoworldfederation',

            // Dua yang TIDAK boleh tayang: satu config internal yang sudah ada,
            // satu pengaturan baru yang belum diputuskan untuk tayang.
            'form_recipient_email' => 'kotak-masuk-internal@dwf.test',
            'sesuatu_yang_baru' => 'belum diputuskan untuk tayang',
        ], SiteSetting::GROUP_CONTACT);

        $body = $this->getJson('/api/v1/settings')->assertOk()->json();

        $this->assertArrayNotHasKey('formRecipientEmail', $body);
        $this->assertArrayNotHasKey('sesuatuYangBaru', $body);
        $this->assertStringNotContainsString('kotak-masuk-internal', json_encode($body));

        // Yang memang publik tetap ada — daftar putihnya bukan sekadar kosong.
        $this->assertArrayHasKey('primaryEmail', $body);
        $this->assertArrayHasKey('footerAddressLabel', $body);
        $this->assertArrayHasKey('socialInstagram', $body);
    }

    /**
     * `fileUrl` bisa menunjuk host lain daripada host API.
     *
     * Host API sengaja cuma melayani `/api` — ia yang beredar di internet, dan
     * membuka rute lain di sana memperluas permukaan yang justru dipisahkan
     * supaya kecil. Jadi unduhan dokumen diberi namanya sendiri, dan
     * `MEDIA_DOWNLOAD_URL` yang menggeser host-nya.
     *
     * PATH-nya harus tetap dari route yang sama: yang digeser host, bukan rute,
     * dan sebuah string yang dirangkai tangan akan tetap menunjuk tempat lama
     * saat rutenya dipindah.
     */
    public function test_the_download_host_can_be_moved_off_the_api_host(): void
    {
        $document = Document::factory()->create(['status' => 'published', 'published_at' => now()->subDay()]);

        config(['dwf.document_download_url' => 'https://fed-media.contoh.test/']);

        $this->assertSame(
            "https://fed-media.contoh.test/media/documents/{$document->id}",
            $document->downloadUrl(),
        );

        // Kosong berarti perilaku lama — URL mengikuti host permintaan, yang
        // benar di lokal tempat semuanya satu host.
        config(['dwf.document_download_url' => null]);

        $this->assertSame(url("/media/documents/{$document->id}"), $document->downloadUrl());
    }

    /** Yang keluar di response ikut host itu, bukan cuma helper-nya. */
    public function test_the_api_sends_the_moved_download_host(): void
    {
        Document::factory()->create(['status' => 'published', 'published_at' => now()->subDay()]);

        config(['dwf.document_download_url' => 'https://fed-media.contoh.test']);

        $url = $this->getJson('/api/v1/resources')->assertOk()->json('0.fileUrl');

        $this->assertStringStartsWith('https://fed-media.contoh.test/media/documents/', $url);
    }

    /**
     * Daftar adalah array TELANJANG, bukan `{ "data": [...] }`.
     *
     * `client.ts` di situs publik membacanya begitu; pembungkus bawaan Laravel
     * menghasilkan `response.map is not a function` di setiap halaman sekaligus.
     */
    public function test_every_list_endpoint_returns_a_bare_array(): void
    {
        $this->seed(FrontendContentSeeder::class);

        foreach (['/api/v1/news', '/api/v1/members', '/api/v1/partners', '/api/v1/champions', '/api/v1/stats'] as $url) {
            $this->assertIsList($this->getJson($url)->assertOk()->json(), "{$url} bukan array telanjang.");
        }
    }

    /** Setiap `id` adalah STRING (§5.3) — di mana pun ia muncul. */
    public function test_every_id_is_a_string(): void
    {
        $this->seed(FrontendContentSeeder::class);

        foreach (['/api/v1/members', '/api/v1/partners', '/api/v1/tournaments', '/api/v1/faqs'] as $url) {
            foreach ($this->getJson($url)->assertOk()->json() as $row) {
                $this->assertIsString($row['id'], "`id` di {$url} bukan string.");
            }
        }
    }

    /** Tidak ada endpoint publik yang menerima selain GET dan POST. */
    public function test_the_public_api_exposes_no_destructive_verbs(): void
    {
        foreach (Route::getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'api/')) {
                continue;
            }

            $this->assertEmpty(
                array_intersect($route->methods(), ['PUT', 'PATCH', 'DELETE']),
                "Route publik {$route->uri()} menerima verb yang mengubah.",
            );
        }
    }

    // ----------------------------------------------------- penyaring

    /** @return array<string, array{0: string, 1: string}> */
    public static function closedFilters(): array
    {
        return [
            'scope' => ['/api/v1/stats?scope=member', 'scope'],
            'tier' => ['/api/v1/members?tier=ngawur', 'tier'],
            'registration' => ['/api/v1/tournaments?registration=ngawur', 'registration'],
            'placement' => ['/api/v1/faqs?placement=ngawur', 'placement'],
        ];
    }

    /**
     * Penyaring berdaftar tertutup MENOLAK nilai yang tidak dikenal.
     *
     * `?scope=member` — kurang satu huruf — dulu diam-diam membalas statistik
     * BERANDA: data yang masuk akal, dari daftar yang salah, tanpa satu pun
     * tanda bahwa permintaannya keliru. Sekarang 422, dan pesannya menyebutkan
     * nilai yang sah.
     */
    #[DataProvider('closedFilters')]
    public function test_an_unknown_filter_value_is_refused(string $url, string $field): void
    {
        $body = $this->getJson($url)->assertStatus(422)->json();

        $this->assertStringContainsString($field, $body['message']);
        $this->assertStringContainsString('must be one of', $body['message']);
    }

    /**
     * Penyaring berisi teks bebas TIDAK menolak — nilainya diketik orang di
     * CMS, jadi yang tidak cocok memang wajar mengembalikan daftar kosong.
     * Itu jawaban, bukan galat.
     */
    public function test_an_unknown_free_text_filter_returns_an_empty_list(): void
    {
        $this->getJson('/api/v1/news?category=kategori-yang-tidak-ada')
            ->assertOk()
            ->assertExactJson([]);
    }

    /** @param mixed $value @return array<int, string> */
    private function flatKeys($value): array
    {
        $keys = [];

        if (is_array($value)) {
            foreach ($value as $key => $child) {
                if (is_string($key)) {
                    $keys[] = $key;
                }

                $keys = array_merge($keys, $this->flatKeys($child));
            }
        }

        return $keys;
    }
}
