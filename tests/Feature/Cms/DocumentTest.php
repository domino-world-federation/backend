<?php

namespace Tests\Feature\Cms;

use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_pdf_is_stored_with_its_size(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $this->actingAs(User::factory()->superAdmin()->create())->post('/documents', [
            'title' => 'DWF Annual Report 2025',
            'category' => 'Publication',
            'posting' => 'now',
            'file' => UploadedFile::fake()->create('report.pdf', 512, 'application/pdf'),
        ])->assertRedirect('/documents');

        $document = Document::first();

        $this->assertNotNull($document);
        Storage::disk('public')->assertExists($document->file_path);
        $this->assertGreaterThan(0, $document->file_size);
    }

    public function test_a_non_pdf_is_rejected(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $this->actingAs(User::factory()->superAdmin()->create())
            ->post('/documents', [
                'title' => 'Bukan PDF',
                'posting' => 'now',
                'file' => UploadedFile::fake()->image('gambar.webp', 1200, 800),
            ])
            ->assertSessionHasErrors('file');
    }

    public function test_an_unknown_category_is_rejected(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $this->actingAs(User::factory()->superAdmin()->create())
            ->post('/documents', [
                'title' => 'Kategori aneh',
                'category' => 'Kategori Yang Tidak Ada',
                'posting' => 'now',
                'file' => UploadedFile::fake()->create('a.pdf', 10, 'application/pdf'),
            ])
            ->assertSessionHasErrors('category');
    }

    public function test_editing_the_title_alone_keeps_the_file(): void
    {
        Storage::fake('public');
        $path = UploadedFile::fake()->create('lama.pdf', 10, 'application/pdf')->store('documents', 'public');

        $document = Document::query()->create([
            'title' => 'Lama', 'slug' => 'lama', 'file_path' => $path,
            'file_size' => 10240, 'status' => 'published', 'published_at' => now(),
        ]);

        $this->actingAs(User::factory()->superAdmin()->create())
            ->put("/documents/{$document->id}", ['title' => 'Baru', 'posting' => 'now'])
            ->assertRedirect('/documents');

        $document->refresh();
        $this->assertSame('Baru', $document->title);
        $this->assertSame($path, $document->file_path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_replacing_the_file_deletes_the_old_one(): void
    {
        Storage::fake('public');
        $old = UploadedFile::fake()->create('lama.pdf', 10, 'application/pdf')->store('documents', 'public');

        $document = Document::query()->create([
            'title' => 'Lama', 'slug' => 'lama-2', 'file_path' => $old,
            'file_size' => 10240, 'status' => 'published', 'published_at' => now(),
        ]);

        $this->actingAs(User::factory()->superAdmin()->create())->put("/documents/{$document->id}", [
            'title' => 'Lama',
            'posting' => 'now',
            'file' => UploadedFile::fake()->create('baru.pdf', 20, 'application/pdf'),
        ]);

        Storage::disk('public')->assertMissing($old);
        Storage::disk('public')->assertExists($document->fresh()->file_path);
    }

    public function test_the_size_label_is_human_readable(): void
    {
        $document = new Document(['file_size' => 2_411_724]);
        $this->assertSame('2.3 MB', $document->file_size_label);

        $small = new Document(['file_size' => 51_200]);
        $this->assertSame('50 KB', $small->file_size_label);
    }

    // -------------------------------------------- Visibility (`369:5236`)

    /**
     * Naik dari sakelar dua keadaan ke Visibility empat keadaan.
     *
     * Migrasinya memetakan `is_active` lama, bukan membuangnya: dokumen yang
     * sudah tayang tidak boleh diam-diam turun jadi draft hanya karena kolomnya
     * berganti bentuk.
     */
    public function test_a_document_can_be_scheduled_from_the_form(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $this->actingAs(User::factory()->superAdmin()->create())->post('/documents', [
            'title' => 'Aturan 2027',
            'category' => 'Rules & Regulations',
            'posting' => 'schedule',
            'published_at' => now()->addWeek()->format('Y-m-d\TH:i'),
            'file' => UploadedFile::fake()->create('a.pdf', 10, 'application/pdf'),
        ])->assertSessionHasNoErrors();

        $document = Document::query()->latest('id')->sole();

        $this->assertSame('scheduled', $document->status);
        $this->assertTrue($document->published_at->isFuture());
        $this->assertSame('scheduled', $document->visibility);
    }

    /** Terjadwal tanpa tanggal ditolak — sama seperti News dan Gallery. */
    public function test_scheduling_without_a_date_is_refused(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $this->actingAs(User::factory()->superAdmin()->create())
            ->post('/documents', [
                'title' => 'Tanpa tanggal',
                'posting' => 'schedule',
                'file' => UploadedFile::fake()->create('a.pdf', 10, 'application/pdf'),
            ])
            ->assertSessionHasErrors('published_at');
    }

    public function test_the_quick_switch_refuses_scheduled_without_a_future_date(): void
    {
        $document = Document::factory()->create(['published_at' => now()->subDay()]);

        $this->actingAs(User::factory()->superAdmin()->create())
            ->patch("/documents/{$document->id}/visibility", ['status' => 'scheduled'])
            ->assertSessionHasErrors('status');
    }

    /**
     * Pengunggah dan penayang dicatat terpisah, lewat trait `TracksPublication`
     * yang sama dengan Gallery.
     */
    public function test_the_uploader_and_the_publisher_are_recorded_separately(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $rina = User::factory()->superAdmin()->create(['name' => 'Rina']);
        $budi = User::factory()->superAdmin()->create(['name' => 'Budi']);

        $this->actingAs($rina)->post('/documents', [
            'title' => 'Laporan Tahunan',
            'posting' => 'schedule',
            'published_at' => now()->addWeek()->format('Y-m-d\TH:i'),
            'file' => UploadedFile::fake()->create('a.pdf', 10, 'application/pdf'),
        ])->assertSessionHasNoErrors();

        $document = Document::query()->latest('id')->sole();

        $this->assertSame($rina->id, $document->created_by_id);
        $this->assertNull($document->published_by_id, 'Terjadwal belum pernah tayang.');

        $this->actingAs($budi)->patch("/documents/{$document->id}/visibility", ['status' => 'published']);

        $document->refresh();
        $this->assertSame($rina->id, $document->created_by_id);
        $this->assertSame($budi->id, $document->published_by_id);
    }

    /** Satu dokumen yang berkasnya benar-benar ada di media publik. */
    private function documentWithFile(string $status = 'published'): Document
    {
        Storage::fake('public');

        $path = UploadedFile::fake()->create('regulasi.pdf', 10, 'application/pdf')
            ->store('documents', 'public');

        return Document::query()->create([
            'title' => 'Regulasi Turnamen v3',
            'slug' => 'regulasi-turnamen-v3',
            'file_path' => $path,
            'file_size' => 10240,
            'status' => $status,
            'published_at' => $status === 'published' ? now()->subDay() : null,
        ]);
    }

    /**
     * URL lama MENGALIHKAN ke berkas di media publik.
     *
     * Sampai 2026-09-06 rute ini yang menyajikan bytenya dan memeriksa sakelar
     * Visibility tiap permintaan. Berkas dokumen sekarang tinggal di media
     * publik, jadi rutenya tinggal pengalihan — dipertahankan karena URL-nya
     * sudah tercetak di situs publik dan mungkin tersimpan di bookmark orang.
     */
    public function test_the_old_download_url_redirects_to_the_media_file(): void
    {
        $document = $this->documentWithFile();

        $this->get("/media/documents/{$document->id}")
            ->assertRedirect(Storage::disk('public')->url($document->file_path));
    }

    /**
     * Dokumen yang DITURUNKAN tetap bisa diunduh, dan tesnya menyatakannya.
     *
     * Ini harga yang dibayar saat berkas dokumen pindah ke media publik, dan ia
     * dikunci di sini SEBAGAI perilaku yang disengaja — bukan dibiarkan tidak
     * teruji supaya tidak terlihat. Sakelar Visibility menyembunyikan barisnya
     * dari situs; berkasnya tetap ada di host media, dan yang sudah memegang
     * tautannya tetap bisa mengambilnya.
     *
     * Kalau suatu saat dokumen perlu bisa ditarik kembali, tes inilah yang
     * harus dibalik lebih dulu.
     */
    public function test_unpublishing_hides_the_row_but_not_the_file(): void
    {
        $document = $this->documentWithFile('draft');

        // Tidak muncul di situs publik.
        $this->getJson('/api/v1/resources')->assertOk()->assertJsonCount(0);

        // Berkasnya tetap ada di media, dan URL-nya tetap menunjuk ke sana.
        Storage::disk('public')->assertExists($document->file_path);
        $this->assertNotNull(Storage::disk('public')->url($document->file_path));
    }

    /** Unggahan mendarat di media publik, bukan di disk bawaan Laravel. */
    public function test_an_uploaded_document_lands_in_public_media(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $this->actingAs(User::factory()->superAdmin()->create())->post('/documents', [
            'title' => 'Regulasi Baru',
            'category' => 'Publication',
            'posting' => 'now',
            'file' => UploadedFile::fake()->create('regulasi.pdf', 12, 'application/pdf'),
        ])->assertRedirect('/documents');

        $path = Document::query()->latest('id')->value('file_path');

        $this->assertNotNull($path);

        Storage::disk('public')->assertExists($path);
        Storage::disk('local')->assertMissing($path);
    }

    /**
     * `fileUrl` di API adalah URL media statis, bukan rute PHP.
     *
     * Itu yang membuat unduhannya tidak lagi bergantung pada satu host yang
     * menjalankan PHP — sebab 404 yang menghabiskan dua hari pada 2026-09-05.
     */
    public function test_the_api_sends_a_static_media_url(): void
    {
        $document = $this->documentWithFile();

        $url = $this->getJson('/api/v1/resources')->assertOk()->json('0.fileUrl');

        $this->assertSame(Storage::disk('public')->url($document->file_path), $url);
        $this->assertStringNotContainsString('/media/documents/', (string) $url);
    }
}
