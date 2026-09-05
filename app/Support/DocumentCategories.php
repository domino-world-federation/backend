<?php

namespace App\Support;

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
}
