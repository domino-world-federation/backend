<?php

namespace Tests\Feature;

use App\Models\ContactMessage;
use App\Models\Document;
use App\Models\Faq;
use App\Models\GalleryItem;
use App\Models\NewsArticle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Yang dikunci di sini adalah KESERAGAMANNYA, bukan satu modul.
 *
 * Tiap modul punya tesnya sendiri untuk aturan khususnya. Berkas ini menjaga
 * janji yang berlaku untuk SEMUANYA: setiap daftar bisa diekspor, setiap ekspor
 * mengikuti filter yang sedang aktif, setiap sakelar status bekerja tanpa
 * membuka formulir, dan setiap perubahan mencatat siapa pelakunya.
 *
 * Tanpa berkas ini, modul berikutnya akan dibangun dengan tiga dari empat janji
 * itu dan tidak ada yang menyadarinya sampai seseorang memakainya.
 */
class StandardListTest extends TestCase
{
    use RefreshDatabase;

    private function actor(): User
    {
        return User::factory()->superAdmin()->create();
    }

    /** @return array<int, array{0: string}> */
    public static function exportRoutes(): array
    {
        return [
            ['/news/export'],
            ['/faq/export'],
            ['/documents/export'],
            ['/gallery/export'],
            ['/contact-messages/export'],
            ['/users/export'],
            ['/activity-log/export'],
            ['/ip-whitelist/export'],
            ['/tournaments/export'],
            ['/federations/export'],
            ['/newsletter/export'],
            ['/integrity-reports/export'],
        ];
    }

    #[DataProvider('exportRoutes')]
    public function test_every_list_can_be_exported_as_csv(string $url): void
    {
        $response = $this->actingAs($this->actor())->get($url);

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

        // BOM UTF-8 di awal berkas. Tanpa itu Excel di Windows membaca judul
        // beraksen sebagai sampah, dan yang disalahkan selalu datanya.
        $this->assertStringStartsWith("\xEF\xBB\xBF", $response->streamedContent());
    }

    #[DataProvider('exportRoutes')]
    public function test_no_export_is_reachable_by_a_guest(string $url): void
    {
        $this->get($url)->assertRedirect('/login');
    }

    public function test_exports_follow_the_active_search(): void
    {
        Faq::factory()->create(['question' => 'Bagaimana cara mendaftar?']);
        Faq::factory()->create(['question' => 'Di mana turnamennya?']);

        $csv = $this->actingAs($this->actor())->get('/faq/export?q=mendaftar')->streamedContent();

        $this->assertStringContainsString('Bagaimana cara mendaftar?', $csv);
        $this->assertStringNotContainsString('Di mana turnamennya?', $csv);
    }

    /**
     * Sakelar status di daftar tidak boleh menuntut formulir yang lengkap.
     *
     * Itu sebabnya ia route sendiri dan bukan `update`: `update` menolak kalau
     * gambar hero belum ada, dan menyalakan sebuah baris tidak seharusnya gagal
     * karena alasan yang tidak ada hubungannya.
     */
    public function test_status_toggles_work_without_the_full_form(): void
    {
        $actor = $this->actor();

        $faq = Faq::factory()->create(['is_active' => true]);
        $this->actingAs($actor)->patch("/faq/{$faq->id}/status", ['is_active' => false])->assertRedirect();
        $this->assertFalse($faq->fresh()->is_active);

        // Documents naik dari sakelar dua keadaan ke Visibility empat keadaan
        // (`369:5236`) — jadi sakelarnya kini berbentuk sama dengan Gallery.
        $press = Document::factory()->create();
        $this->actingAs($actor)->patch("/documents/{$press->id}/visibility", ['status' => 'unpublished'])->assertRedirect();
        $this->assertSame('unpublished', $press->fresh()->status);

        $item = GalleryItem::factory()->create();
        $this->actingAs($actor)->patch("/gallery/{$item->id}/visibility", ['status' => 'unpublished'])->assertRedirect();
        $this->assertSame('unpublished', $item->fresh()->visibility);
    }

    /**
     * `updated_by_id` diisi trait `TracksEditor` lewat event `saving` model,
     * bukan oleh controller.
     *
     * Karena itu ia terisi di SEMUA jalur simpan — formulir, sakelar cepat,
     * pengurutan — tanpa ada satu pun controller yang harus mengingatnya.
     */
    public function test_every_content_model_records_who_changed_it(): void
    {
        $rina = User::factory()->superAdmin()->create(['name' => 'Rina']);

        $faq = Faq::factory()->create();
        $press = Document::factory()->create();
        $item = GalleryItem::factory()->create();
        $article = NewsArticle::factory()->create();

        $this->actingAs($rina)->patch("/faq/{$faq->id}/status", ['is_active' => false]);
        $this->actingAs($rina)->patch("/documents/{$press->id}/visibility", ['status' => 'unpublished']);
        $this->actingAs($rina)->patch("/gallery/{$item->id}/visibility", ['status' => 'unpublished']);
        $this->actingAs($rina)->patch("/news/{$article->id}/highlight", ['is_highlighted' => true]);

        $this->assertSame($rina->id, $faq->fresh()->updated_by_id);
        $this->assertSame($rina->id, $press->fresh()->updated_by_id);
        $this->assertSame($rina->id, $item->fresh()->updated_by_id);
        $this->assertSame($rina->id, $article->fresh()->updated_by_id);
    }

    /**
     * Ekspor pengguna TIDAK boleh membawa rahasia.
     *
     * Berkas CSV berpindah lewat surel dan folder bersama — tempat yang tidak
     * punya satu pun perlindungan yang dipunyai tabel aslinya.
     */
    public function test_the_user_export_carries_no_secrets(): void
    {
        $user = User::factory()->superAdmin()->create(['email' => 'rina@dwf-domino.org']);

        $csv = $this->actingAs($user)->get('/users/export')->streamedContent();

        $this->assertStringContainsString('rina@dwf-domino.org', $csv);
        $this->assertStringNotContainsString($user->password, $csv);
        $this->assertStringNotContainsString('two_factor', $csv);
    }

    /** Isi pesan ikut diekspor — itu memang yang diminta orang dari layar ini. */
    public function test_the_message_export_carries_the_body(): void
    {
        ContactMessage::factory()->create([
            'subject' => 'Pertanyaan keanggotaan',
            'message' => 'Federasi kami ingin mendaftar.',
        ]);

        $csv = $this->actingAs($this->actor())->get('/contact-messages/export')->streamedContent();

        $this->assertStringContainsString('Federasi kami ingin mendaftar.', $csv);
    }

    /** Jejak audit tetap hanya-baca — ekspor menyalin keluar, tidak mengubah. */
    public function test_the_activity_log_still_refuses_to_be_changed(): void
    {
        $actor = $this->actor();

        $this->actingAs($actor)->post('/activity-log')->assertMethodNotAllowed();
        $this->actingAs($actor)->delete('/activity-log')->assertMethodNotAllowed();
    }
}
