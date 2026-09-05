<?php

namespace App\Support;

use RuntimeException;

/**
 * Kategori dokumen — satu tempat membaca `config('dwf.document_categories')`.
 *
 * Empat pemakai membutuhkan bentuk yang berbeda dari daftar yang sama: aturan
 * validasi butuh nama-namanya, factory butuh satu nama acak, dan dua layar
 * butuh nama BESERTA halaman tempat kategorinya muncul. Membaca config
 * langsung di empat tempat berarti empat tempat yang harus ikut berubah saat
 * bentuknya berubah — dan bentuknya baru saja berubah sekali.
 */
final class DocumentCategories
{
    /**
     * Nama kategorinya saja — ini yang tersimpan di kolom `category`.
     *
     * @return array<int, string>
     */
    public static function names(): array
    {
        return array_keys(config('dwf.document_categories'));
    }

    /**
     * Bentuk untuk layar: nilai, label, dan di mana ia muncul.
     *
     * `pages` dan `planned` dikirim terpisah karena layarnya mengucapkannya
     * dengan kalimat yang berbeda — yang satu "muncul di", yang lain "belum ada
     * rak-nya". Menggabungkannya di sini berarti layar itu berjanji sesuatu
     * yang belum benar.
     *
     * @return array<int, array{value: string, label: string, pages: array<int, string>, planned: array<int, string>}>
     */
    public static function options(): array
    {
        self::guardShape();

        return collect(config('dwf.document_categories'))
            ->map(fn (array $meta, string $name) => [
                'value' => $name,
                'label' => $name,
                'pages' => $meta['pages'] ?? [],
                'planned' => $meta['planned'] ?? [],
            ])
            ->values()
            ->all();
    }

    /**
     * Menolak dengan menyebut sebabnya, bukan dengan TypeError.
     *
     * Daftar ini pernah berbentuk larik datar berisi nama saja. Kalau yang
     * terbaca masih bentuk itu, penyebabnya hampir selalu satu: kode baru sudah
     * ter-deploy tapi `bootstrap/cache/config.php` masih versi lama, jadi
     * `config()` menjawab dari berkas cache alih-alih dari `config/dwf.php`.
     * Terjadi di produksi 2026-09-05 dan muncul sebagai "Argument #1 ($meta)
     * must be of type array, string given" di tengah sebuah closure — pesan yang
     * benar tapi tidak menunjuk ke mana pun.
     *
     * Ini jebakan yang sama dengan yang `dwf:mail-test` periksa lebih dulu:
     * yang dibaca aplikasi tidak selalu isi berkasnya, dan membaca berkasnya
     * dengan mata tidak akan pernah memperlihatkan bedanya.
     */
    private static function guardShape(): void
    {
        $configured = config('dwf.document_categories');

        if (! is_array($configured) || $configured === []) {
            throw new RuntimeException(
                'config(\'dwf.document_categories\') kosong atau bukan larik.',
            );
        }

        if (array_is_list($configured)) {
            throw new RuntimeException(
                'config(\'dwf.document_categories\') masih bentuk lama (larik datar berisi nama). '
                .'Yang dibaca aplikasi bukan isi config/dwf.php — hampir pasti bootstrap/cache/config.php yang basi. '
                .'Jalankan: php artisan config:cache && php artisan route:cache, lalu reload php-fpm (PRODUCTION.md §11).',
            );
        }
    }
}
