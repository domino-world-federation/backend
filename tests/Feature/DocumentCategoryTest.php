<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Support\DocumentCategories;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Kosakata kategori dokumen.
 *
 * **Ini kontrak antar-repo, bukan sekadar isi dropdown.** Situs publik menyaring
 * dengan string yang sama persis — `getResources("Rules & Regulations")` — jadi
 * satu huruf yang berbeda membuat section di sana berhenti menampilkan apa pun,
 * tanpa error di mana pun. Itu persis yang sudah terjadi sekali: sampai
 * 2026-09-05 daftar di backend dan daftar yang diminta frontend TIDAK BERIRISAN
 * SAMA SEKALI, dan kedelapan rak dokumen di situs publik kosong tanpa ada yang
 * memperhatikan, karena semuanya menyembunyikan diri saat kosong.
 *
 * Karena itu nama-namanya dieja lengkap di tes ini. Ia memang menuntut dua
 * tempat diubah bersamaan — dan itu tujuannya: yang mengubahnya dipaksa berhenti
 * dan memutuskan apakah barisnya ikut dipindah dan apakah situs publik ikut
 * diperbarui.
 */
class DocumentCategoryTest extends TestCase
{
    use RefreshDatabase;

    /** Nama yang dibaca situs publik. Mengubahnya menuntut migrasi + perubahan di landing-page. */
    private const EXPECTED = [
        'Rules & Regulations',
        'Governance Documents',
        'Integrity & Ethics',
        'Membership Documents',
        'Development Resources',
        'Reports & Publications',
        'Tournament Documents',
        'Media & Press Releases',
    ];

    public function test_the_vocabulary_is_exactly_what_the_public_site_asks_for(): void
    {
        $this->assertSame(self::EXPECTED, DocumentCategories::names());
    }

    /** Tiap kategori menyebut setidaknya satu halaman — kategori yang tidak tayang di mana pun adalah pilihan yang menyesatkan. */
    public function test_every_category_appears_somewhere(): void
    {
        foreach (DocumentCategories::options() as $option) {
            $this->assertNotEmpty(
                $option['pages'],
                "Kategori `{$option['value']}` tidak tayang di halaman mana pun.",
            );
        }
    }

    /**
     * Halaman yang belum punya rak dokumen disebut TERPISAH.
     *
     * Kalau Integrity ikut masuk `pages`, layar Documents akan memberi tahu
     * pengunggah bahwa berkasnya muncul di sana — dan ia baru tahu sebaliknya
     * setelah membuka halamannya sendiri.
     */
    public function test_pages_without_a_shelf_are_not_promised(): void
    {
        $promised = collect(DocumentCategories::options())->flatMap(fn (array $o) => $o['pages']);

        foreach (['Integrity', 'Members', 'About Us'] as $page) {
            $this->assertFalse(
                $promised->contains($page),
                "`{$page}` belum punya rak dokumen, jadi ia tidak boleh dijanjikan.",
            );
        }
    }

    /** Tidak ada dokumen yang memegang kategori di luar daftar — itu berkas yang berhenti terlihat. */
    public function test_no_document_is_left_holding_a_name_that_no_longer_exists(): void
    {
        Document::factory()->count(5)->create();

        $this->assertSame(
            0,
            Document::query()
                ->whereNotNull('category')
                ->whereNotIn('category', DocumentCategories::names())
                ->count(),
        );
    }
}
