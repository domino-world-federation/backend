<?php

namespace Tests\Feature\Cms;

use App\Models\Document;
use App\Models\GalleryItem;
use App\Models\NewsArticle;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * `draft` tidak bisa disetel dari daftar — di keempat modul sekaligus.
 *
 * Permintaan pemilik repo 2026-09-04. Draft bukan tujuan yang dipilih melainkan
 * keadaan yang lahir dari satu tombol: "Save Draft" di dalam formulirnya,
 * bersama isi yang baru saja diketik. Membuatnya bisa dipilih dari daftar
 * berarti ada jalan menarik tulisan yang sudah tayang kembali jadi draft tanpa
 * seorang pun membuka isinya. Menarik dari peredaran tetap bisa — namanya
 * `unpublished`, dan ia memang tidak berpura-pura ada naskah baru.
 *
 * Disapu keempatnya sekali jalan, bukan satu tes per modul: yang gampang
 * terjadi adalah modul KELIMA yang menyalin pola lama, dan tes per modul tidak
 * pernah menangkap yang belum ditulis.
 */
class QuickStatusTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{class-string<Model>, string}>
     */
    public static function modules(): array
    {
        return [
            'news' => [NewsArticle::class, '/news'],
            'gallery' => [GalleryItem::class, '/gallery'],
            'documents' => [Document::class, '/documents'],
            'tournaments' => [Tournament::class, '/tournaments'],
        ];
    }

    /**
     * @param  class-string<Model>  $model
     */
    #[DataProvider('modules')]
    public function test_draft_is_not_a_quick_status(string $model, string $path): void
    {
        $this->assertNotContains(
            'draft',
            $model::QUICK_STATUSES,
            $model.'::QUICK_STATUSES masih memuat draft.'
        );

        // Dan daftarnya tidak kosong — sebuah konstanta kosong akan lolos
        // assertion di atas sambil mematikan seluruh kolom Visibility.
        $this->assertNotEmpty($model::QUICK_STATUSES);
    }

    /**
     * Yang menegakkannya server, bukan komponen Vue.
     *
     * Menghilangkan barisnya dari menu cuma menghilangkan barisnya dari menu.
     * Endpoint-nya tetap ada, tetap menerima PATCH, dan seorang yang menyalin
     * permintaan lama tetap bisa mengembalikan artikel tayang jadi draft.
     *
     * @param  class-string<Model>  $model
     */
    #[DataProvider('modules')]
    public function test_the_endpoint_refuses_draft(string $model, string $path): void
    {
        $record = $model::factory()->create();

        $this->actingAs(User::factory()->superAdmin()->create())
            ->patch("{$path}/{$record->id}/visibility", ['status' => 'draft'])
            ->assertSessionHasErrors('status');
    }

    /**
     * Dan yang sah tetap lewat — kalau tidak, tes di atas lulus karena
     * endpoint-nya menolak SEMUANYA.
     *
     * @param  class-string<Model>  $model
     */
    #[DataProvider('modules')]
    public function test_the_endpoint_still_accepts_unpublished(string $model, string $path): void
    {
        $record = $model::factory()->create();

        $this->actingAs(User::factory()->superAdmin()->create())
            ->patch("{$path}/{$record->id}/visibility", ['status' => 'unpublished'])
            ->assertSessionHasNoErrors();

        $this->assertSame('unpublished', $record->fresh()->status);
    }
}
