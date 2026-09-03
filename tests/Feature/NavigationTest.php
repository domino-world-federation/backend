<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Navigation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class NavigationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Alasan tes ini ada: sidebar dan daftar route lahir dari array yang sama,
     * tapi tidak ada yang memaksa keduanya tetap sejalan. Kalau seseorang
     * menambah menu tanpa route-nya, item sidebar itu jadi 404 — dan itu baru
     * ketahuan saat ada yang mengkliknya.
     *
     * Dulu perulangan ini MELEWATI tujuan yang sudah dibangun dan cuma memeriksa
     * placeholder-nya. Sejak placeholder dihapus, yang dilewatinya adalah
     * semuanya — tes yang lolos tanpa satu pun assertion. Sekarang terbalik:
     * yang diketuk justru layar sungguhannya.
     */
    public function test_every_sidebar_destination_answers_200(): void
    {
        $user = User::factory()->superAdmin()->create();
        $destinations = Navigation::destinations();

        $this->assertNotEmpty($destinations);

        foreach ($destinations as $key => $destination) {
            $path = '/'.str_replace('.', '/', $key);

            $this->actingAs($user)
                ->get($path)
                ->assertOk("Tujuan sidebar {$path} tidak punya route.");
        }
    }

    /**
     * Tidak boleh ada menu yang berujung halaman kosong.
     *
     * Membalik penjagaan yang lama. Dulu tujuan yang belum dibangun mendapat
     * placeholder, dan tes memastikan placeholder-nya ada — jadi menambah menu
     * kosong LOLOS. Tiga kelompok yang memakainya (Header & Navigation, Footer,
     * Landing Page) semuanya berakhir dibuang atau diganti layar sungguhan,
     * dan alasannya selalu sama: menu yang berujung halaman kosong mengajari
     * orang bahwa sebagian sidebar memang tidak berfungsi.
     */
    public function test_every_sidebar_destination_has_a_real_screen(): void
    {
        $pending = collect(Navigation::destinations())
            ->reject(fn (array $d) => $d['built'])
            ->keys()
            ->all();

        $this->assertSame([], $pending, 'Menu sidebar tanpa layar: '.implode(', ', $pending));
    }

    public function test_navigation_is_shared_with_signed_in_pages(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create())
            ->get('/dashboard')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('navigation.0.label', 'Dashboard')
                ->where('navigation.1.label', 'Content Management')
                ->where('navigation.2.label', 'Home Page')
            );
    }

    public function test_an_unknown_destination_is_a_404_not_a_blank_placeholder(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create())
            ->get('/tidak-ada-menunya')
            ->assertNotFound();
    }
}
