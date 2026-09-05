<?php

namespace Tests\Feature;

use App\Models\Document;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * `dwf:document-check` — kenapa sebuah unduhan dokumen 404.
 *
 * `MediaController` menolak dengan 404 untuk tiga sebab dan sengaja tidak
 * membedakannya di layar; perintah ini yang membedakannya, dari dalam aplikasi.
 * Yang dikunci di sini adalah ketiga cabang jawabannya — sebuah alat diagnosa
 * yang salah menunjuk lebih buruk daripada tidak ada, karena ia mengirim orang
 * membetulkan hal yang tidak rusak.
 */
class DocumentCheckCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');
    }

    private function document(): Document
    {
        return Document::factory()->create(['file_path' => 'documents/uji.pdf']);
    }

    public function test_a_document_with_its_file_in_place_passes(): void
    {
        $document = $this->document();
        Storage::disk('local')->put($document->file_path, 'isi');

        $this->artisan('dwf:document-check')
            ->expectsOutputToContain('Semua dokumen punya berkasnya.')
            ->assertSuccessful();
    }

    /**
     * Berkas yang tertinggal di disk public punya jawaban yang PASTI.
     *
     * Itu bentuk kegagalan yang sangat khas: baris dibuat oleh kode sebelum
     * `move_documents_to_the_private_disk`, dan migrasinya belum jalan di mesin
     * itu. Menyebut migrasinya adalah bedanya antara satu perintah dan satu jam
     * menebak.
     */
    public function test_a_file_left_on_the_public_disk_names_the_migration(): void
    {
        $document = $this->document();
        Storage::disk('public')->put($document->file_path, 'isi');

        $this->artisan('dwf:document-check')
            ->expectsOutputToContain('tertinggal di disk public')
            ->expectsOutputToContain('php artisan migrate --force')
            ->assertFailed();
    }

    /** Hilang dari kedua disk: sebabnya tidak pasti, jadi yang disebut kemungkinannya berikut path tujuannya. */
    public function test_a_file_missing_from_both_disks_says_where_it_should_go(): void
    {
        $document = $this->document();

        $this->artisan('dwf:document-check')
            ->expectsOutputToContain('tidak ada di kedua disk')
            ->expectsOutputToContain('documents/uji.pdf')
            ->assertFailed();
    }

    /**
     * Direktori yang tidak bisa ditelusuri TIDAK dilaporkan sebagai berkas hilang.
     *
     * Ini kegagalan alat ini sendiri, 2026-09-05. Dokumen diunggah oleh php-fpm,
     * yang membuat direktorinya 0700 miliknya sendiri; perintah dijalankan dari
     * shell sebagai user lain, dan `file_exists()` menjawab false — bukan karena
     * berkasnya tidak ada, melainkan karena yang bertanya tidak boleh melihat.
     * Laporannya mengirim orang mencari berkas yang mungkin ada di depan mata.
     *
     * Yang benar adalah menolak menyimpulkan, menyebut user yang bertanya, dan
     * tetap keluar bukan-nol: tidak tahu bukan kabar baik.
     */
    public function test_a_directory_it_cannot_enter_is_not_reported_as_missing(): void
    {
        // Disk sungguhan, bukan `Storage::fake` — yang diuji justru izin
        // filesystem, dan disk palsu tidak punya izin untuk dilanggar.
        $root = storage_path('app/uji-izin-'.uniqid());
        config(['filesystems.disks.local.root' => $root]);
        config(['filesystems.disks.public.root' => $root.'-public']);

        @mkdir($root.'/documents', 0o700, true);
        file_put_contents($root.'/documents/uji.pdf', 'isi');
        @chmod($root.'/documents', 0o000);

        // Root menolak izin filesystem, jadi tesnya tidak berarti apa-apa di sana.
        if (is_executable($root.'/documents')) {
            @chmod($root.'/documents', 0o700);
            @unlink($root.'/documents/uji.pdf');
            @rmdir($root.'/documents');
            @rmdir($root);

            $this->markTestSkipped('Proses ini menembus izin direktori — dijalankan sebagai root?');
        }

        Document::factory()->create(['file_path' => 'documents/uji.pdf']);

        try {
            $this->artisan('dwf:document-check')
                ->expectsOutputToContain('TIDAK BISA DIPASTIKAN')
                ->expectsOutputToContain('sudo -u www-data')
                ->doesntExpectOutputToContain('Semua dokumen punya berkasnya.')
                ->assertFailed();
        } finally {
            @chmod($root.'/documents', 0o700);
            @unlink($root.'/documents/uji.pdf');
            @rmdir($root.'/documents');
            @rmdir($root);
        }
    }

    /** Gagal, supaya ia bisa dipakai di skrip deploy tanpa membaca keluarannya. */
    public function test_it_exits_non_zero_so_a_script_can_use_it(): void
    {
        $this->document();

        $this->artisan('dwf:document-check')->assertFailed();
    }

    public function test_it_can_be_pointed_at_one_document(): void
    {
        $kept = $this->document();
        Storage::disk('local')->put($kept->file_path, 'isi');

        $broken = Document::factory()->create(['file_path' => 'documents/hilang.pdf']);

        $this->artisan("dwf:document-check {$kept->id}")->assertSuccessful();
        $this->artisan("dwf:document-check {$broken->id}")->assertFailed();
    }

    /** Yang dicetak adalah root disk yang BENAR-BENAR dibaca aplikasi — itu inti alatnya. */
    public function test_it_prints_the_disk_roots_the_app_actually_reads(): void
    {
        $this->document();

        $this->artisan('dwf:document-check')
            ->expectsOutputToContain('benar-benar dibaca aplikasi')
            ->assertFailed();
    }

    public function test_it_survives_an_empty_library(): void
    {
        $this->artisan('dwf:document-check')
            ->expectsOutputToContain('Tidak ada dokumen.')
            ->assertSuccessful();
    }

    /** Berkas asli yang diunggah lewat layar CMS ikut terbaca oleh alat ini. */
    public function test_a_real_upload_is_found(): void
    {
        $document = $this->document();
        Storage::disk('local')->putFileAs(
            'documents',
            UploadedFile::fake()->create('uji.pdf', 12),
            'uji.pdf',
        );

        $this->artisan('dwf:document-check')->assertSuccessful();
    }
}
