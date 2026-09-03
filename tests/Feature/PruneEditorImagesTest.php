<?php

namespace Tests\Feature;

use App\Console\Commands\PruneEditorImages;
use App\Models\NewsArticle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Penyapu gambar editor.
 *
 * Gambar yang disisipkan ke editor hidup di dalam HTML, bukan di kolomnya
 * sendiri — jadi tidak ada relasi yang bisa dilepas, dan folder `editor` hanya
 * bertambah sampai perintah ini ada.
 *
 * Dua janjinya yang benar-benar penting, dan keduanya soal TIDAK menghapus.
 */
class PruneEditorImagesTest extends TestCase
{
    use RefreshDatabase;

    private function stash(string $name, int $ageInDays = 0): string
    {
        $path = "editor/{$name}";
        Storage::disk('public')->put($path, 'x');

        if ($ageInDays > 0) {
            touch(Storage::disk('public')->path($path), now()->subDays($ageInDays)->getTimestamp());
        }

        return $path;
    }

    public function test_an_unreferenced_old_image_is_deleted(): void
    {
        Storage::fake('public');
        $path = $this->stash('yatim.webp', ageInDays: 30);

        $this->artisan('editor:prune')->assertSuccessful();

        Storage::disk('public')->assertMissing($path);
    }

    /** Yang MASIH dirujuk tidak boleh hilang, seberapa pun tuanya. */
    public function test_a_referenced_image_survives(): void
    {
        Storage::fake('public');
        $path = $this->stash('dipakai.webp', ageInDays: 400);

        NewsArticle::factory()->create([
            'body' => '<p>Halo</p><img src="/storage/editor/dipakai.webp">',
        ]);

        $this->artisan('editor:prune')->assertSuccessful();

        Storage::disk('public')->assertExists($path);
    }

    /**
     * Berkas MUDA yang belum dirujuk juga bertahan.
     *
     * Gambar diunggah saat DISISIPKAN, bukan saat formulirnya disimpan — jadi
     * ada jendela nyata di mana sebuah berkas sudah ada di disk tapi belum
     * disebut baris mana pun: selama orangnya masih mengetik. Menyapu tanpa
     * ambang usia akan menghapus gambar dari bawah formulir yang sedang dibuka.
     */
    public function test_a_freshly_uploaded_image_is_left_alone(): void
    {
        Storage::fake('public');
        $path = $this->stash('baru-saja.webp');

        $this->artisan('editor:prune')->assertSuccessful();

        Storage::disk('public')->assertExists($path);
    }

    public function test_a_dry_run_deletes_nothing(): void
    {
        Storage::fake('public');
        $path = $this->stash('yatim.webp', ageInDays: 30);

        $this->artisan('editor:prune --dry-run')->assertSuccessful();

        Storage::disk('public')->assertExists($path);
    }

    /**
     * Daftar kolom HTML harus LENGKAP.
     *
     * Kolom editor yang lupa didaftarkan membuat gambarnya dihapus padahal
     * masih dipakai — kegagalan paling mahal dari perintah ini, dan satu-
     * satunya yang tidak bisa dibatalkan.
     *
     * Diperiksa lewat jumlah pemanggilan `Purifier::clean()` di controller:
     * tiap kolom yang dibersihkan ADALAH kolom editor, dan tiap kolom editor
     * harus bisa disapu. `overview` turnamen sempat luput dari keduanya
     * sekaligus.
     */
    public function test_every_purified_column_is_also_prunable(): void
    {
        $calls = collect(File::allFiles(app_path('Http/Controllers')))
            ->sum(fn ($file) => substr_count($file->getContents(), 'Purifier::clean('));

        $this->assertSame(
            $calls,
            count(PruneEditorImages::HTML_COLUMNS),
            'Ada kolom yang dibersihkan Purifier tapi tidak terdaftar di PruneEditorImages::HTML_COLUMNS '
            .'(atau sebaliknya). Gambar di kolom yang tidak terdaftar akan dihapus padahal masih dipakai.',
        );
    }
}
