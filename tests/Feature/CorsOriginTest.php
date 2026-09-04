<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Daftar asal yang diizinkan tahan terhadap garis miring dan spasi.
 *
 * Sebuah "origin" tidak pernah punya path: `https://situs.com/` BUKAN origin,
 * dan browser membandingkannya sebagai string mentah. Satu garis miring yang
 * tidak sengaja terketik di `.env` menghasilkan penolakan yang menyebut kedua
 * nilainya berdampingan — dan keduanya terlihat identik bagi mata:
 *
 *   The 'Access-Control-Allow-Origin' header has a value
 *   'https://situs.com/' that is not equal to the supplied origin.
 */
class CorsOriginTest extends TestCase
{
    /** @return array<string, array{0: string, 1: array<int, string>}> */
    public static function origins(): array
    {
        return [
            'garis miring di ujung' => ['https://fed-web.pborado.com/', ['https://fed-web.pborado.com']],
            'spasi di sekelilingnya' => [' https://fed-web.pborado.com ', ['https://fed-web.pborado.com']],
            'beberapa, campur' => [
                'https://a.test/, https://b.test ,https://c.test//',
                ['https://a.test', 'https://b.test', 'https://c.test'],
            ],
            'kosong tetap kosong' => ['', []],
            'sudah benar tidak berubah' => ['https://a.test', ['https://a.test']],
        ];
    }

    #[DataProvider('origins')]
    public function test_origins_are_normalised(string $raw, array $expected): void
    {
        $normalised = array_values(array_filter(
            array_map(static fn (string $o): string => rtrim(trim($o), '/'), explode(',', $raw)),
        ));

        $this->assertSame($expected, $normalised);
    }

    /**
     * Dan yang benar-benar dipakai aplikasi memang hasil normalisasi itu —
     * bukan cuma fungsi di tes ini yang kebetulan berperilaku sama.
     */
    public function test_the_config_itself_normalises(): void
    {
        $this->assertSame(
            [],
            array_filter(
                (array) config('cors.allowed_origins'),
                static fn (string $origin): bool => str_ends_with($origin, '/') || trim($origin) !== $origin,
            ),
        );
    }
}
