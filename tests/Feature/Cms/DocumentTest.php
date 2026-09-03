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
            'category' => 'Annual Report',
            'posting' => 'now',
            'file' => UploadedFile::fake()->create('report.pdf', 512, 'application/pdf'),
        ])->assertRedirect('/documents');

        $document = Document::first();

        $this->assertNotNull($document);
        Storage::disk('local')->assertExists($document->file_path);
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
        Storage::fake('local');
        $path = UploadedFile::fake()->create('lama.pdf', 10, 'application/pdf')->store('documents', 'local');

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
        Storage::disk('local')->assertExists($path);
    }

    public function test_replacing_the_file_deletes_the_old_one(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $old = UploadedFile::fake()->create('lama.pdf', 10, 'application/pdf')->store('documents', 'local');

        $document = Document::query()->create([
            'title' => 'Lama', 'slug' => 'lama-2', 'file_path' => $old,
            'file_size' => 10240, 'status' => 'published', 'published_at' => now(),
        ]);

        $this->actingAs(User::factory()->superAdmin()->create())->put("/documents/{$document->id}", [
            'title' => 'Lama',
            'posting' => 'now',
            'file' => UploadedFile::fake()->create('baru.pdf', 20, 'application/pdf'),
        ]);

        Storage::disk('local')->assertMissing($old);
        Storage::disk('local')->assertExists($document->fresh()->file_path);
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
            'category' => 'Regulation',
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

    /** Hanya yang benar-benar tayang yang dihitung `live()`. */
    public function test_live_skips_drafts_and_future_schedules(): void
    {
        Document::factory()->create(['title' => 'tayang']);
        Document::factory()->create(['title' => 'draf', 'status' => 'draft', 'published_at' => null]);
        Document::factory()->create([
            'title' => 'nanti', 'status' => 'scheduled', 'published_at' => now()->addWeek(),
        ]);
        Document::factory()->create([
            'title' => 'sudah', 'status' => 'scheduled', 'published_at' => now()->subWeek(),
        ]);

        $live = Document::query()->live()->pluck('title')->all();

        sort($live);
        $this->assertSame(['sudah', 'tayang'], $live);
    }

    // ------------------------------------------------ berkas berpenjaga

    private function documentWithFile(string $status = 'published'): Document
    {
        Storage::fake('public');
        Storage::fake('local');

        $path = UploadedFile::fake()->create('regulasi.pdf', 10, 'application/pdf')
            ->store('documents', 'local');

        return Document::query()->create([
            'title' => 'Regulasi Turnamen v3',
            'slug' => 'regulasi-turnamen-v3',
            'file_path' => $path,
            'file_size' => 10240,
            'status' => $status,
            'published_at' => $status === 'published' ? now()->subDay() : null,
        ]);
    }

    public function test_a_published_document_can_be_downloaded_by_anyone(): void
    {
        $document = $this->documentWithFile();

        $this->get("/media/documents/{$document->id}")
            ->assertOk()
            ->assertDownload('regulasi-turnamen-v3.pdf');
    }

    /**
     * INTI perubahan 2026-09-03.
     *
     * Sebelumnya berkas dokumen disajikan web server langsung lewat symlink,
     * jadi menurunkan sebuah dokumen tidak menurunkan berkasnya — yang diatur
     * sakelar Visibility cuma daftarnya. Nama berkas acak menahan TEBAKAN,
     * bukan tautan yang sudah beredar.
     *
     * 404, bukan 403: 403 mengakui bahwa berkasnya ADA dan cuma sedang
     * ditahan — untuk dokumen yang belum dirilis, keberadaannya sendiri kadang
     * yang rahasia.
     */
    public function test_an_unpublished_document_is_not_downloadable_by_the_public(): void
    {
        $document = $this->documentWithFile('draft');

        $this->get("/media/documents/{$document->id}")->assertNotFound();
    }

    /** Admin yang boleh melihat modulnya tetap bisa memeriksa isinya. */
    public function test_an_editor_can_still_download_a_draft(): void
    {
        $document = $this->documentWithFile('draft');

        $this->actingAs(User::factory()->superAdmin()->create())
            ->get("/media/documents/{$document->id}")
            ->assertOk();
    }

    /**
     * Nama unduhannya dari JUDUL, bukan dari nama di disk.
     *
     * Nama di disk 40 karakter acak — benar sebagai penyimpanan, tapi berkas
     * bernama `a1b2c3….pdf` di folder Downloads tidak bisa dikenali lagi
     * seminggu kemudian.
     */
    public function test_the_download_is_named_after_the_document(): void
    {
        $document = $this->documentWithFile();

        $this->get("/media/documents/{$document->id}")
            ->assertDownload('regulasi-turnamen-v3.pdf');
    }

    /** Berkas dokumen TIDAK boleh mendarat di disk yang di-symlink. */
    public function test_an_uploaded_document_never_lands_on_the_public_disk(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $this->actingAs(User::factory()->superAdmin()->create())->post('/documents', [
            'title' => 'Laporan Rahasia',
            'category' => 'Annual Report',
            'posting' => 'now',
            'file' => UploadedFile::fake()->create('rahasia.pdf', 12, 'application/pdf'),
        ])->assertRedirect('/documents');

        $path = Document::query()->latest('id')->value('file_path');

        $this->assertNotNull($path);

        Storage::disk('local')->assertExists($path);
        Storage::disk('public')->assertMissing($path);
    }
}
