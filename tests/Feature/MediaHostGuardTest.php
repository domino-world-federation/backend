<?php

namespace Tests\Feature;

use App\Providers\AppServiceProvider;
use RuntimeException;
use Tests\TestCase;

/**
 * Dua setelan yang tidak boleh menunjuk tempat yang sama.
 *
 * `MEDIA_URL` adalah asal berkas statis; `MEDIA_DOWNLOAD_URL` asal unduhan
 * dokumen, yang harus lewat PHP karena sakelar Visibility diperiksa tiap
 * permintaan. Menyamakannya berarti unduhan 404 — atau, kalau root nginx-nya
 * menaungi folder privat, seluruh dokumen bisa diunduh siapa pun tanpa login.
 *
 * Terjadi 2026-09-06, dan bocornya nyata: berkas privat terunduh dari internet
 * dengan `Cache-Control: immutable` setahun, dan Cloudflare menyimpannya.
 */
class MediaHostGuardTest extends TestCase
{
    private function guard(): void
    {
        $provider = new AppServiceProvider($this->app);

        $method = new \ReflectionMethod($provider, 'refuseDocumentDownloadsOnTheStaticMediaHost');
        $method->invoke($provider);
    }

    public function test_it_refuses_when_downloads_point_at_the_media_host(): void
    {
        config([
            'filesystems.disks.public.url' => 'https://fed-media.contoh.test',
            'dwf.document_download_url' => 'https://fed-media.contoh.test',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/fed-media\.contoh\.test/');

        $this->guard();
    }

    /** Path yang berbeda tidak menolong — yang menentukan HOST-nya. */
    public function test_a_different_path_on_the_same_host_is_still_refused(): void
    {
        config([
            'filesystems.disks.public.url' => 'https://fed-media.contoh.test/berkas',
            'dwf.document_download_url' => 'https://fed-media.contoh.test/',
        ]);

        $this->expectException(RuntimeException::class);

        $this->guard();
    }

    public function test_separate_hosts_are_allowed(): void
    {
        config([
            'filesystems.disks.public.url' => 'https://fed-pub-media.contoh.test',
            'dwf.document_download_url' => 'https://fed-doc.contoh.test',
        ]);

        $this->guard();

        $this->addToAssertionCount(1);
    }

    /**
     * Kosong berarti perilaku lokal — semuanya satu host, dan itu memang benar
     * di sana. Penjaganya tidak boleh menghalangi pengembangan.
     */
    public function test_an_unset_download_url_is_allowed(): void
    {
        config([
            'filesystems.disks.public.url' => 'https://fed-pub-media.contoh.test',
            'dwf.document_download_url' => null,
        ]);

        $this->guard();

        $this->addToAssertionCount(1);
    }
}
