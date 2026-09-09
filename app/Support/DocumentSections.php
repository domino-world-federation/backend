<?php

namespace App\Support;

use App\Models\Document;
use App\Models\DocumentPlacement;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Rak dokumen di situs publik — satu tempat membaca
 * `config('dwf.document_sections')`.
 *
 * Kembaran `DocumentCategories`, dan alasannya sama: beberapa pemakai
 * membutuhkan bentuk yang berbeda dari daftar yang sama — aturan validasi butuh
 * kuncinya, layar CMS butuh label beserta batas dan kategorinya, dan API publik
 * butuh dokumen yang sudah jadi. Membaca config langsung di tiga tempat berarti
 * tiga tempat yang harus ikut berubah saat bentuknya berubah.
 */
final class DocumentSections
{
    /**
     * Kunci rak — ini yang tersimpan di `document_placements.section` dan yang
     * dikirim situs publik sebagai `?section=`.
     *
     * @return array<int, string>
     */
    public static function keys(): array
    {
        return array_keys(self::configured());
    }

    /**
     * Definisi satu rak, atau `null` kalau kuncinya asing.
     *
     * @return array{key: string, page: string, label: string, category: ?string, max: int}|null
     */
    public static function find(string $key): ?array
    {
        $section = self::configured()[$key] ?? null;

        return $section === null ? null : self::normalise($key, $section);
    }

    /**
     * Seluruh rak, sudah dinormalkan.
     *
     * @return array<int, array{key: string, page: string, label: string, category: ?string, max: int}>
     */
    public static function all(): array
    {
        return collect(self::configured())
            ->map(fn (array $section, string $key) => self::normalise($key, $section))
            ->values()
            ->all();
    }

    /**
     * Isi sebuah rak, siap dikirim ke situs publik.
     *
     * ── Kurasi menang; kalau belum ada kurasi, rak tidak kosong ──
     *
     * Rak yang punya SATU BARIS SAJA di `document_placements` dianggap sudah
     * diurus admin, dan yang tampil persis pilihannya — dalam urutannya.
     * Rak yang belum punya satu pun baris jatuh kembali ke perilaku lama, "N
     * terbaru dari kategori ini".
     *
     * Bedanya penting justru saat rak yang sudah dikurasi jadi kosong karena
     * dokumennya ditarik dari peredaran: yang benar adalah rak itu kosong, BUKAN
     * terisi ulang otomatis oleh dokumen yang tidak pernah dipilih siapa pun.
     * Karena itu yang diperiksa adalah adanya baris penempatan, bukan adanya
     * dokumen yang lolos `live()`.
     *
     * @return Collection<int, Document>
     */
    public static function documents(string $key): Collection
    {
        $section = self::find($key);

        if ($section === null) {
            return collect();
        }

        if (DocumentPlacement::query()->where('section', $key)->exists()) {
            return Document::query()
                ->live()
                ->join('document_placements as placement', 'placement.document_id', '=', 'documents.id')
                ->where('placement.section', $key)
                ->orderBy('placement.position')
                ->limit($section['max'])
                // `select` eksplisit: tanpa ini kolom `id` milik tabel
                // penempatan menimpa `documents.id`, dan tiap dokumen mengaku
                // ber-id penempatannya.
                ->select('documents.*')
                ->get();
        }

        return Document::query()
            ->live()
            ->when($section['category'], fn ($q, string $category) => $q->where('category', $category))
            ->latest('published_at')
            ->limit($section['max'])
            ->get();
    }

    /**
     * Dokumen yang BOLEH dipilih untuk sebuah rak.
     *
     * Disaring ke kategori rak itu — picker yang menawarkan seluruh
     * perpustakaan akan membiarkan orang menaruh rulebook di rak publikasi, dan
     * tidak ada satu pun layar yang akan memberi tahu bahwa itu keliru.
     *
     * Draft ikut ditawarkan, dan itu disengaja: dokumen yang dijadwalkan terbit
     * pekan depan memang perlu bisa dipasang lebih dulu. Yang menahannya tampil
     * adalah `live()` di `documents()`, bukan hilangnya ia dari picker.
     *
     * @return Collection<int, Document>
     */
    public static function library(string $key): Collection
    {
        $section = self::find($key);

        if ($section === null) {
            return collect();
        }

        return Document::query()
            ->when($section['category'], fn ($q, string $category) => $q->where('category', $category))
            ->latest('published_at')
            ->get(['id', 'title', 'category', 'status', 'published_at']);
    }

    /**
     * @param  array<string, mixed>  $section
     * @return array{key: string, page: string, label: string, category: ?string, max: int}
     */
    private static function normalise(string $key, array $section): array
    {
        return [
            'key' => $key,
            'page' => $section['page'],
            'label' => $section['label'],
            'category' => $section['category'] ?? null,
            'max' => (int) $section['max'],
        ];
    }

    /**
     * Menolak dengan menyebut sebabnya, bukan dengan TypeError.
     *
     * Jebakan yang sama dengan `DocumentCategories::guardShape()`, dan ditulis
     * ulang di sini karena penyebabnya sama-sama `bootstrap/cache/config.php`
     * yang basi: yang dibaca aplikasi tidak selalu isi berkasnya, dan membaca
     * berkasnya dengan mata tidak akan pernah memperlihatkan bedanya.
     *
     * @return array<string, array<string, mixed>>
     */
    private static function configured(): array
    {
        $configured = config('dwf.document_sections');

        if (! is_array($configured) || $configured === []) {
            throw new RuntimeException(
                'config(\'dwf.document_sections\') kosong atau bukan larik. '
                .'Kalau kode barunya sudah ter-deploy, hampir pasti bootstrap/cache/config.php yang basi. '
                .'Jalankan: php artisan config:cache && php artisan route:cache, lalu reload php-fpm (PRODUCTION.md §11).',
            );
        }

        return $configured;
    }
}
