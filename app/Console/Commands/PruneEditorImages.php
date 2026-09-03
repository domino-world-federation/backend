<?php

namespace App\Console\Commands;

use App\Models\Faq;
use App\Models\LegalPageBlock;
use App\Models\NewsArticle;
use App\Models\Tournament;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Membuang gambar editor yang tidak disebut satu baris pun.
 *
 * Gambar yang disisipkan ke dalam editor hidup di dalam HTML, bukan di kolom
 * miliknya sendiri — jadi tidak ada relasi yang bisa dilepas, dan menghapus
 * artikelnya TIDAK boleh ikut menghapus berkasnya: potongan HTML yang sama
 * bisa disalin ke artikel lain. Sampai perintah ini ada, folder `editor` hanya
 * bertambah: tiap gambar yang diunggah lalu diurungkan penulisnya tetap
 * tinggal, selamanya.
 *
 * **Ambang usia bukan kehati-hatian berlebihan, ia yang membuat perintah ini
 * aman.** Gambar diunggah SAAT disisipkan, bukan saat formulirnya disimpan —
 * jadi ada jendela nyata di mana sebuah berkas sudah ada di disk tapi belum
 * disebut baris mana pun: selama orangnya masih mengetik. Menyapu tanpa ambang
 * akan menghapus gambar dari bawah formulir yang sedang dibuka.
 */
class PruneEditorImages extends Command
{
    protected $signature = 'editor:prune
        {--days=7 : Usia minimum berkas, dalam hari, sebelum ia boleh dibuang}
        {--dry-run : Sebutkan yang akan dibuang, jangan buang}';

    protected $description = 'Membuang gambar editor yang tidak dirujuk HTML mana pun';

    /**
     * Tiap kolom HTML di seluruh repo, dan model pemiliknya.
     *
     * Daftar ini harus tumbuh bersama modul: kolom editor yang lupa
     * didaftarkan di sini akan membuat gambarnya dihapus padahal masih dipakai.
     * `EditorImageTest` menjaga daftarnya tetap lengkap.
     *
     * @var array<class-string, string>
     */
    public const HTML_COLUMNS = [
        NewsArticle::class => 'body',
        Faq::class => 'answer',
        LegalPageBlock::class => 'description',
        Tournament::class => 'overview',
    ];

    public function handle(): int
    {
        $disk = Storage::disk('public');
        $cutoff = now()->subDays((int) $this->option('days'))->getTimestamp();

        $files = collect($disk->files('editor'));

        if ($files->isEmpty()) {
            $this->info('Folder editor kosong.');

            return self::SUCCESS;
        }

        $referenced = $this->referencedPaths();

        $stale = $files
            ->reject(fn (string $path) => $referenced->contains($path))
            // Yang masih muda DIBIARKAN walau belum dirujuk: ia mungkin sedang
            // menunggu formulir yang belum ditekan Simpan.
            ->filter(fn (string $path) => $disk->lastModified($path) < $cutoff)
            ->values();

        if ($stale->isEmpty()) {
            $this->info("Tidak ada yang perlu dibuang dari {$files->count()} berkas.");

            return self::SUCCESS;
        }

        $bytes = $stale->sum(fn (string $path) => $disk->size($path));

        foreach ($stale as $path) {
            $this->line(($this->option('dry-run') ? '[uji] ' : '').$path);

            if (! $this->option('dry-run')) {
                $disk->delete($path);
            }
        }

        $this->info(sprintf(
            '%s %d berkas (%s KB) dari %d.',
            $this->option('dry-run') ? 'Akan membuang' : 'Membuang',
            $stale->count(),
            number_format($bytes / 1024, 1),
            $files->count(),
        ));

        return self::SUCCESS;
    }

    /**
     * Seluruh path gambar yang disebut HTML mana pun.
     *
     * Dibaca dengan `lazy()`, bukan `get()`: kolom HTML adalah kolom terbesar
     * di tiap tabel, dan memuat seluruh isi artikel ke memori demi mencari
     * substring adalah cara termahal melakukannya.
     *
     * @return Collection<int, string>
     */
    private function referencedPaths(): Collection
    {
        $paths = collect();

        foreach (self::HTML_COLUMNS as $model => $column) {
            $model::query()
                ->whereNotNull($column)
                ->where($column, 'like', '%/storage/editor/%')
                ->select($column)
                ->lazy()
                ->each(function ($row) use ($column, $paths) {
                    preg_match_all('#/storage/(editor/[A-Za-z0-9._-]+)#', (string) $row->{$column}, $matches);

                    foreach ($matches[1] as $match) {
                        $paths->push($match);
                    }
                });
        }

        return $paths->unique();
    }
}
