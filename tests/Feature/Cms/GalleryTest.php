<?php

namespace Tests\Feature\Cms;

use App\Models\GalleryEvent;
use App\Models\GalleryItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GalleryTest extends TestCase
{
    use RefreshDatabase;

    public function test_choosing_new_creates_the_event_once(): void
    {
        Storage::fake('public');
        $user = User::factory()->superAdmin()->create();

        $payload = [
            'type' => 'tournament',
            'kind' => 'image',
            'event_mode' => 'new',
            'event_name' => 'Madrid Qualifier 2026',
            'posting' => 'now',
            'asset' => UploadedFile::fake()->image('a.webp', 800, 600),
        ];

        $this->actingAs($user)->post('/gallery', $payload)->assertRedirect('/gallery');

        $this->assertSame(1, GalleryEvent::query()->count());
        $this->assertSame('tournament', GalleryEvent::first()->type);
    }

    public function test_choosing_existing_reuses_the_event(): void
    {
        Storage::fake('public');
        $event = GalleryEvent::query()->create([
            'name' => 'DWF Community Day', 'slug' => 'dwf-community-day', 'type' => 'event',
        ]);

        $this->actingAs(User::factory()->superAdmin()->create())->post('/gallery', [
            'type' => 'event',
            'kind' => 'image',
            'event_mode' => 'existing',
            'gallery_event_id' => $event->id,
            'posting' => 'now',
            'asset' => UploadedFile::fake()->image('b.webp', 800, 600),
        ])->assertRedirect('/gallery');

        $this->assertSame(1, GalleryEvent::query()->count());
        $this->assertSame($event->id, GalleryItem::first()->gallery_event_id);
    }

    public function test_a_video_accepts_video_mimes_and_rejects_images(): void
    {
        Storage::fake('public');
        $event = GalleryEvent::query()->create(['name' => 'E', 'slug' => 'e', 'type' => 'event']);

        $base = [
            'type' => 'event',
            'kind' => 'video',
            'event_mode' => 'existing',
            'gallery_event_id' => $event->id,
            'posting' => 'now',
        ];

        $this->actingAs(User::factory()->superAdmin()->create())
            ->post('/gallery', [...$base, 'asset' => UploadedFile::fake()->create('v.mp4', 100, 'video/mp4')])
            ->assertRedirect('/gallery');

        $this->actingAs(User::factory()->superAdmin()->create())
            ->post('/gallery', [...$base, 'asset' => UploadedFile::fake()->image('gambar.webp')])
            ->assertSessionHasErrors('asset');
    }

    public function test_a_new_event_needs_a_name(): void
    {
        Storage::fake('public');

        $this->actingAs(User::factory()->superAdmin()->create())
            ->post('/gallery', [
                'type' => 'event',
                'kind' => 'image',
                'event_mode' => 'new',
                'posting' => 'now',
                'asset' => UploadedFile::fake()->image('a.webp', 800, 600),
            ])
            ->assertSessionHasErrors('event_name');
    }

    public function test_deleting_an_item_removes_its_file(): void
    {
        Storage::fake('public');
        $event = GalleryEvent::query()->create(['name' => 'E', 'slug' => 'e2', 'type' => 'event']);
        $path = UploadedFile::fake()->image('c.webp')->store('gallery', 'public');

        $item = GalleryItem::query()->create([
            'gallery_event_id' => $event->id, 'kind' => 'image', 'path' => $path,
            'slug' => 'c', 'status' => 'published', 'position' => 1,
        ]);

        $this->actingAs(User::factory()->superAdmin()->create())->delete("/gallery/{$item->id}");

        Storage::disk('public')->assertMissing($path);
        $this->assertDatabaseMissing('gallery_items', ['id' => $item->id]);
    }

    // ---------------------------------------------- daftar (`478:5884`)

    /**
     * Tiga kolom pelaku diisi event model, BUKAN controller.
     *
     * Yang mengunggah dan yang menayangkan sengaja dipisah — itu justru alur
     * yang dijanjikan tombol "Save Draft": satu orang menyiapkan, orang lain
     * menekan Publish.
     */
    public function test_the_uploader_and_the_publisher_are_recorded_separately(): void
    {
        Storage::fake('public');

        $rina = User::factory()->superAdmin()->create(['name' => 'Rina']);
        $budi = User::factory()->superAdmin()->create(['name' => 'Budi']);

        $this->actingAs($rina)->post('/gallery', [
            'kind' => 'image',
            'event_mode' => 'new',
            'event_name' => 'World Championship',
            'type' => 'event',
            'posting' => 'draft',
            'asset' => UploadedFile::fake()->image('a.webp', 800, 600),
        ])->assertSessionHasNoErrors();

        $item = GalleryItem::query()->latest('id')->sole();

        $this->assertSame($rina->id, $item->created_by_id);
        $this->assertNull($item->published_by_id, 'Draft belum pernah tayang.');

        $this->actingAs($budi)->patch("/gallery/{$item->id}/visibility", ['status' => 'published']);

        $item->refresh();
        $this->assertSame($rina->id, $item->created_by_id);
        $this->assertSame($budi->id, $item->published_by_id);
    }

    /**
     * Penayang dicatat SEKALI. Kalau tidak, kolom Published akan menyebut
     * orang yang cuma menyunting alt text tiga bulan sesudahnya.
     */
    public function test_the_publisher_is_not_overwritten_by_a_later_edit(): void
    {
        $budi = User::factory()->superAdmin()->create();
        $siti = User::factory()->superAdmin()->create();

        $item = GalleryItem::factory()->create(['published_by_id' => null]);

        $this->actingAs($budi)->patch("/gallery/{$item->id}/visibility", ['status' => 'published']);
        $this->assertSame($budi->id, $item->fresh()->published_by_id);

        $this->actingAs($siti)->patch("/gallery/{$item->id}/visibility", ['status' => 'unpublished']);
        $this->actingAs($siti)->patch("/gallery/{$item->id}/visibility", ['status' => 'published']);

        $this->assertSame($budi->id, $item->fresh()->published_by_id);
    }

    /** Filter "Category" desain adalah jenis event-nya, bukan event tertentu. */
    public function test_the_category_filter_narrows_by_event_type(): void
    {
        $event = GalleryEvent::query()->create(['name' => 'Gala', 'slug' => 'gala', 'type' => 'event']);
        $tour = GalleryEvent::query()->create(['name' => 'Open', 'slug' => 'open', 'type' => 'tournament']);

        GalleryItem::factory()->create(['gallery_event_id' => $event->id, 'alt' => 'foto-gala']);
        GalleryItem::factory()->create(['gallery_event_id' => $tour->id, 'alt' => 'foto-open']);

        $csv = $this->actingAs(User::factory()->superAdmin()->create())
            ->get('/gallery/export?category=tournament')
            ->streamedContent();

        $this->assertStringContainsString('foto-open', $csv);
        $this->assertStringNotContainsString('foto-gala', $csv);
    }

    /**
     * Pencarian menjangkau nama event, bukan cuma alt text.
     *
     * Desain hanya menggambar dua dropdown, jadi penyaring "event tertentu"
     * yang dulu ada tidak punya tempat lagi — kemampuannya pindah ke sini,
     * bukan hilang.
     */
    public function test_search_also_matches_the_event_name(): void
    {
        $event = GalleryEvent::query()->create([
            'name' => 'World Championship London', 'slug' => 'wcl', 'type' => 'event',
        ]);
        $other = GalleryEvent::query()->create(['name' => 'Regional Cup', 'slug' => 'rc', 'type' => 'event']);

        GalleryItem::factory()->create(['gallery_event_id' => $event->id, 'alt' => 'tanpa-kata-kunci']);
        GalleryItem::factory()->create(['gallery_event_id' => $other->id, 'alt' => 'lain']);

        $csv = $this->actingAs(User::factory()->superAdmin()->create())
            ->get('/gallery/export?q=London')
            ->streamedContent();

        $this->assertStringContainsString('tanpa-kata-kunci', $csv);
        $this->assertStringNotContainsString('lain', $csv);
    }
}
