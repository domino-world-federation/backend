<?php

namespace App\Console\Commands;

use App\Models\NewsArticle;
use App\Support\Media\StoredFile;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Mengisi gambar yang kosong dengan WebP buatan sendiri.
 *
 * Ada karena seeder SENGAJA tidak menanam berkas biner: repo ini tidak memuat
 * satu pun foto contoh, dan menaruhnya di git berarti megabyte yang ikut
 * ter-clone selamanya demi data yang dibuang orang pertama yang memakainya.
 *
 * Tapi baris tanpa gambar juga bukan data contoh yang berguna — halaman berita
 * di situs publik menggambar hero dan thumbnail tanpa penjagaan, jadi tanpa
 * berkas yang mengisi slotnya, yang terlihat orang adalah gambar rusak dan
 * bukan halaman yang bisa dinilai. Perintah ini jembatannya: dijalankan sekali
 * di lingkungan contoh, hasilnya bisa dibuang dan dibuat ulang kapan saja.
 *
 * **Bukan untuk produksi.** Gambarnya gradien bergaris dengan judul beritanya
 * dicetak di atasnya — jelas-jelas placeholder, supaya tidak ada yang keliru
 * menganggapnya foto sungguhan dan membiarkannya tayang.
 */
class GenerateDemoImages extends Command
{
    protected $signature = 'dwf:demo-images
        {--force : Timpa gambar yang sudah ada, bukan cuma yang kosong}';

    protected $description = 'Membuat gambar WebP contoh untuk baris yang slotnya masih kosong';

    /**
     * Ukurannya mengikuti `dwf.uploads.image_specs` — kalau spesifikasinya
     * berubah, gambar yang dibuat di sini ikut, dan tidak ada angka kedua yang
     * perlu diingat.
     */
    public function handle(): int
    {
        if (app()->isProduction()) {
            $this->error('Perintah ini membuat gambar placeholder. Tidak untuk produksi.');

            return self::FAILURE;
        }

        $specs = config('dwf.uploads.image_specs');
        $filled = 0;

        foreach (NewsArticle::query()->orderBy('id')->get() as $article) {
            foreach (['hero' => 'hero_image_path', 'landscape' => 'landscape_image_path'] as $slot => $column) {
                if (! array_key_exists($column, $article->getAttributes())) {
                    continue;
                }

                if ($article->{$column} !== null && ! $this->option('force')) {
                    continue;
                }

                $old = $article->{$column};

                $article->forceFill([
                    $column => $this->write($article->title, $specs[$slot]['min_width'], $specs[$slot]['min_height'], 'news'),
                ])->saveQuietly();

                StoredFile::forget($old);
                $filled++;
            }
        }

        $this->info("{$filled} gambar dibuat.");

        return self::SUCCESS;
    }

    /**
     * Menulis satu WebP dan mengembalikan pathnya.
     *
     * `imagewebp` dengan mutu 80: cukup untuk placeholder, dan jauh di bawah
     * batas `dwf.uploads.image_max_kb` sehingga gambar yang dibuat perintah ini
     * tidak pernah ditolak oleh validasi yang sama saat disunting nanti.
     */
    private function write(string $caption, int $width, int $height, string $folder): string
    {
        $image = imagecreatetruecolor($width, $height);

        // Gradien diagonal — cukup untuk membedakan satu kartu dari yang lain
        // sekali dipindai, tanpa berpura-pura jadi foto.
        for ($y = 0; $y < $height; $y++) {
            $t = $y / max(1, $height - 1);
            $colour = imagecolorallocate(
                $image,
                (int) (18 + 40 * $t),
                (int) (26 + 52 * $t),
                (int) (54 + 78 * $t),
            );
            imagefilledrectangle($image, 0, $y, $width, $y, $colour);
        }

        $gold = imagecolorallocate($image, 225, 183, 98);
        imagefilledrectangle($image, 0, $height - 12, $width, $height, $gold);

        $white = imagecolorallocate($image, 245, 245, 245);
        imagestring($image, 5, 48, (int) ($height / 2) - 20, 'CONTOH — '.mb_substr($caption, 0, 60), $white);
        imagestring($image, 3, 48, (int) ($height / 2) + 6, $width.' x '.$height.'  placeholder, bukan foto sungguhan', $gold);

        $path = "{$folder}/demo-".bin2hex(random_bytes(16)).'.webp';

        ob_start();
        imagewebp($image, null, 80);
        Storage::disk('public')->put($path, (string) ob_get_clean());
        imagedestroy($image);

        return $path;
    }
}
