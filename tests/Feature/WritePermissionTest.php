<?php

namespace Tests\Feature;

use App\Models\ContactMessage;
use App\Models\Document;
use App\Models\Faq;
use App\Models\GalleryItem;
use App\Models\NewsArticle;
use App\Models\NewsCategory;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Izin `.create`, `.update`, dan `.delete` BENAR-BENAR ditegakkan.
 *
 * Ada karena selama berbulan-bulan ia tidak: delapan modul lama mendaftarkan
 * seluruh route tulisnya di dalam satu grup ber-`can:{modul}.view`, jadi peran
 * `viewer` — yang menurut definisinya hanya punya izin `.view` — bisa
 * menghapus artikel berita. Izinnya ada di database dan tercentang di layar
 * Roles; yang tidak ada adalah yang memeriksanya.
 *
 * Kegagalannya diam sempurna: tidak ada galat, tidak ada log, dan layar Roles
 * tetap memperlihatkan kotak centang yang terbaca seolah berarti sesuatu.
 * Karena itu tesnya menyapu seluruh modul sekaligus, bukan satu per satu di
 * berkas masing-masing — yang berikutnya lupa akan tertangkap di sini.
 */
class WritePermissionTest extends TestCase
{
    use RefreshDatabase;

    private function viewer(): User
    {
        return User::factory()->withRole('viewer')->create();
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function writeRoutes(): array
    {
        return [
            'buat berita' => ['post', '/news'],
            'ubah berita' => ['put', '/news/{news}'],
            'sorot berita' => ['patch', '/news/{news}/highlight'],
            'tayangkan berita' => ['patch', '/news/{news}/visibility'],
            'hapus berita' => ['delete', '/news/{news}'],
            'buat kategori berita' => ['post', '/news/categories'],
            'hapus kategori berita' => ['delete', '/news/categories/{newsCategory}'],

            'buat faq' => ['post', '/faq'],
            'ubah faq' => ['put', '/faq/{faq}'],
            'sakelar faq' => ['patch', '/faq/{faq}/status'],
            'hapus faq' => ['delete', '/faq/{faq}'],
            'urutkan faq' => ['put', '/faq/reorder'],
            'tempatkan faq' => ['put', '/faq/pages'],

            'buat dokumen' => ['post', '/documents'],
            'hapus dokumen' => ['delete', '/documents/{document}'],
            'tayangkan dokumen' => ['patch', '/documents/{document}/visibility'],

            'buat galeri' => ['post', '/gallery'],
            'hapus galeri' => ['delete', '/gallery/{gallery}'],
            'tayangkan galeri' => ['patch', '/gallery/{gallery}/visibility'],

            'ubah halaman hukum' => ['put', '/legal-pages/terms'],
            'ubah kontak' => ['put', '/contact-social'],
            'hapus pesan' => ['delete', '/contact-messages/{message}'],

            'tayangkan turnamen' => ['patch', '/tournaments/{tournament}/visibility'],
            'hapus turnamen' => ['delete', '/tournaments/{tournament}'],
        ];
    }

    #[DataProvider('writeRoutes')]
    public function test_a_viewer_cannot_write(string $method, string $path): void
    {
        $viewer = $this->viewer();

        $path = str_replace(
            ['{news}', '{newsCategory}', '{faq}', '{document}', '{gallery}', '{message}', '{tournament}'],
            [
                (string) NewsArticle::factory()->create()->id,
                (string) NewsCategory::factory()->create()->id,
                (string) Faq::factory()->create()->id,
                (string) Document::factory()->create()->id,
                (string) GalleryItem::factory()->create()->id,
                (string) ContactMessage::factory()->create()->id,
                (string) Tournament::factory()->create()->id,
            ],
            $path,
        );

        $this->actingAs($viewer)->{$method}($path)->assertForbidden();
    }

    /**
     * Dan sisi baiknya tetap bekerja: `viewer` memang boleh MEMBACA semuanya.
     *
     * Tanpa pasangan ini, "perbaikan" yang mengunci seluruh modul dari viewer
     * akan lolos tes di atas dengan sempurna.
     */
    public function test_a_viewer_can_still_read(): void
    {
        $viewer = $this->viewer();

        foreach (['/news', '/faq', '/documents', '/gallery', '/legal-pages', '/contact-messages', '/tournaments'] as $path) {
            $this->actingAs($viewer)->get($path)->assertOk("Viewer seharusnya bisa membuka {$path}");
        }
    }
}
