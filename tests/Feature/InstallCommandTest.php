<?php

namespace Tests\Feature;

use App\Models\PageMeta;
use App\Models\User;
use App\Support\Access;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * `dwf:install` — jalur memulai database produksi.
 *
 * Yang dikunci di sini bukan "ia membuat akun", melainkan yang TIDAK
 * dilakukannya: tidak menanam data contoh, dan tidak menimpa sandi admin yang
 * sudah ada. Keduanya kesalahan yang cuma ketahuan setelah terjadi.
 */
class InstallCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('dwf.admin.email', 'admin@dwf-domino.org');
        config()->set('dwf.admin.password', 'rahasia-sekali');
        config()->set('dwf.admin.name', 'DWF Admin');
    }

    public function test_it_creates_the_first_super_admin(): void
    {
        $this->artisan('dwf:install')->assertSuccessful();

        $user = User::query()->sole();

        $this->assertSame('admin@dwf-domino.org', $user->email);
        $this->assertTrue($user->hasRole(Access::SUPER_ADMIN));
    }

    /** Cadangan seluruh situs — layar SEO menolak menghapusnya, jadi ia harus ada. */
    public function test_it_creates_the_seo_fallback_row(): void
    {
        $this->artisan('dwf:install')->assertSuccessful();

        $this->assertDatabaseHas('page_meta', ['route' => PageMeta::DEFAULT_ROUTE]);
    }

    /**
     * TIDAK menanam satu pun data contoh.
     *
     * Ini alasan perintah ini ada. `db:seed` membuat akun admin juga — dan
     * sekaligus berita contoh, turnamen contoh, dokumen, pesan kontak, dan
     * aturan daftar IP. Di produksi itu bukan awal yang bersih melainkan
     * pekerjaan menghapus.
     */
    public function test_it_plants_no_sample_content(): void
    {
        $this->artisan('dwf:install')->assertSuccessful();

        foreach (['news_articles', 'tournaments', 'documents', 'contact_messages', 'faqs', 'ip_whitelist_rules'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
    }

    /**
     * Menjalankan ulang TIDAK mengembalikan sandi ke nilai `.env`.
     *
     * Sandi di `.env` server bisa jauh lebih tua daripada sandi yang benar-benar
     * dipakai orangnya. Perintah pemasangan yang diam-diam memutarnya kembali
     * adalah cara kehilangan akses yang tidak akan dicurigai siapa pun.
     */
    public function test_running_it_again_never_resets_the_password(): void
    {
        $this->artisan('dwf:install')->assertSuccessful();

        $user = User::query()->sole();
        $user->forceFill(['password' => Hash::make('sandi-yang-sudah-diganti')])->save();

        $this->artisan('dwf:install')->assertSuccessful();

        $this->assertTrue(Hash::check('sandi-yang-sudah-diganti', $user->fresh()->password));
        $this->assertTrue($user->fresh()->hasRole(Access::SUPER_ADMIN));
    }

    public function test_it_refuses_without_admin_credentials(): void
    {
        config()->set('dwf.admin.email', null);

        $this->artisan('dwf:install')->assertFailed();

        $this->assertDatabaseCount('users', 0);
    }
}
