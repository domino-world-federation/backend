<?php

namespace Tests\Feature\Cms;

use App\Http\Controllers\Cms\LegalPageController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;
use Tests\TestCase;

/**
 * Nama halaman hukum punya SATU sumber, dan tes ini yang menjaganya tetap satu.
 *
 * Layarnya tidak memakai judul dari server (backoffice dua bahasa), melainkan
 * `backoffice.legal.names.<kunci>`. Jadi ada dua daftar yang harus cocok:
 * kunci di `LegalPageController::TITLES` dan kunci di kedua berkas bahasa.
 *
 * Kalau salah satunya ketinggalan, tidak ada yang gagal saat runtime —
 * halamannya tetap terbuka dan tetap bisa disimpan. Yang keliru cuma NAMANYA,
 * dan itu persis yang terjadi sebelum tes ini ada: Cookie Policy menyebut
 * dirinya "Kebijakan Privasi" di judul, breadcrumb, judul kartu, dan label
 * setiap bloknya, karena layarnya memilih nama dengan `key === 'terms' ? … : …`
 * dan halaman ketiga jatuh ke cabang `else`.
 */
class LegalPageNamesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{string}>
     */
    public static function locales(): array
    {
        return ['en' => ['en'], 'id' => ['id']];
    }

    #[DataProvider('locales')]
    public function test_every_legal_page_has_a_name_in_every_language(string $locale): void
    {
        $names = trans('backoffice.legal.names', locale: $locale);

        $this->assertIsArray($names, "backoffice.legal.names hilang di lang/{$locale}.");

        $this->assertSame(
            array_keys($this->titles()),
            array_keys($names),
            "Kunci halaman hukum dan terjemahannya di lang/{$locale} tidak sama."
        );

        foreach ($names as $key => $name) {
            $this->assertIsString($name);
            $this->assertNotSame('', trim($name), "Nama halaman '{$key}' kosong di lang/{$locale}.");
        }
    }

    public function test_the_names_differ_from_each_other(): void
    {
        // Cabang `else` yang lama lolos setiap pemeriksaan "ada dan tidak
        // kosong" — cacatnya adalah dua halaman memakai nama yang SAMA.
        foreach (['en', 'id'] as $locale) {
            $names = array_values(trans('backoffice.legal.names', locale: $locale));

            $this->assertSame(
                count($names),
                count(array_unique($names)),
                "Ada dua halaman hukum dengan nama identik di lang/{$locale}."
            );
        }
    }

    /**
     * Kamusnya benar-benar SAMPAI ke layarnya.
     *
     * Dua tes di atas memeriksa berkas bahasanya. Yang ini memeriksa jalur
     * lengkapnya — `Lang::get('backoffice')` di `HandleInertiaRequests` sampai
     * ke props yang dibaca `t()`. Bedanya penting: layar yang mencetak
     * `legal.names.cookie-policy` apa adanya berarti KAMUSNYA yang tidak
     * datang, bukan kuncinya yang salah, dan keduanya diperbaiki di tempat yang
     * sama sekali berbeda.
     */
    public function test_the_names_reach_the_screen(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create())
            ->get('/legal-pages')
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->component('Legal/Index')
                ->has('translations.legal.names', count($this->titles()))
                ->where('translations.legal.names.cookie-policy', trans('backoffice.legal.names.cookie-policy'))
            );
    }

    /**
     * @return array<string, string>
     */
    private function titles(): array
    {
        return (new ReflectionClass(LegalPageController::class))->getConstant('TITLES');
    }
}
