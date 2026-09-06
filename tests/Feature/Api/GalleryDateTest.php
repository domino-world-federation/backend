<?php

namespace Tests\Feature\Api;

use App\Models\GalleryEvent;
use App\Models\GalleryItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Album yang belum punya tanggal acara.
 *
 * Kolomnya nullable dan layar Add Gallery tidak punya field untuk itu, jadi
 * setiap album yang dibuat dari sana lahir tanpa tanggal. Itu keadaan yang sah,
 * dan pada 2026-09-06 ia menjatuhkan `/gallery` di situs publik: `heldOn`
 * dihilangkan dari response (§5.4), situs menerima `undefined`, dan pemformat
 * tanggal melempar `Invalid time value` di tengah render server.
 *
 * Yang dikunci di sini sisi API-nya: album seperti itu tetap dikirim, dan tidak
 * memimpin daftar.
 */
class GalleryDateTest extends TestCase
{
    use RefreshDatabase;

    private function album(string $name, ?string $heldOn): GalleryEvent
    {
        $album = GalleryEvent::create([
            'name' => $name,
            'slug' => GalleryEvent::uniqueSlug($name),
            'type' => 'event',
            'held_on' => $heldOn,
        ]);

        GalleryItem::factory()->create([
            'gallery_event_id' => $album->id,
            'status' => 'published',
            'published_at' => now()->subDay(),
        ]);

        return $album;
    }

    public function test_an_album_without_a_date_is_still_returned(): void
    {
        $this->album('Tanpa Tanggal', null);

        $body = $this->getJson('/api/v1/gallery/albums')->assertOk()->json();

        $this->assertCount(1, $body);
        $this->assertArrayNotHasKey('heldOn', $body[0], 'Nilai kosong dihilangkan, bukan dikirim null (§5.4).');
    }

    /**
     * Postgres menaruh NULL paling atas pada `ORDER BY … DESC`.
     *
     * Tanpa `nulls last`, album yang belum bertanggal memimpin halaman galeri di
     * atas kejuaraan tahun ini — urutannya "yang terbaru dulu", dan yang tidak
     * bertanggal bukan yang terbaru, ia yang tidak diketahui.
     */
    public function test_an_album_without_a_date_does_not_lead_the_list(): void
    {
        $this->album('Tanpa Tanggal', null);
        $this->album('Kejuaraan 2026', '2026-05-01');
        $this->album('Kejuaraan 2024', '2024-05-01');

        $names = collect($this->getJson('/api/v1/gallery/albums')->assertOk()->json())
            ->pluck('title')
            ->all();

        $this->assertSame(['Kejuaraan 2026', 'Kejuaraan 2024', 'Tanpa Tanggal'], $names);
    }
}
