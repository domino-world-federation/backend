<?php

namespace Tests\Feature\Cms;

use App\Models\GalleryEvent;
use App\Models\GalleryItem;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class GalleryTest extends TestCase
{
    use RefreshDatabase;

    public function test_choosing_new_creates_the_event_once(): void
    {
        Storage::fake('public');
        $user = User::factory()->superAdmin()->create();

        $payload = [
            'type' => 'event',
            'kind' => 'image',
            'event_mode' => 'new',
            'event_name' => 'DWF Community Day 2026',
            'posting' => 'now',
            'asset' => UploadedFile::fake()->image('a.webp', 800, 600),
        ];

        $this->actingAs($user)->post('/gallery', $payload)->assertRedirect('/gallery');

        $this->assertSame(1, GalleryEvent::query()->count());
        $this->assertSame('event', GalleryEvent::first()->type);
    }

    // ------------------------------------------ album turnamen (2026-09-04)

    /**
     * Aset turnamen menempel pada TURNAMENnya, bukan pada nama yang diketik.
     */
    public function test_a_tournament_asset_attaches_to_the_tournament(): void
    {
        Storage::fake('public');
        $tournament = Tournament::factory()->create(['name' => 'London International Domino Open']);

        $this->actingAs(User::factory()->superAdmin()->create())->post('/gallery', [
            'type' => 'tournament',
            'kind' => 'image',
            'tournament_id' => $tournament->id,
            'posting' => 'now',
            'asset' => UploadedFile::fake()->image('a.webp', 800, 600),
        ])->assertRedirect('/gallery');

        $album = GalleryEvent::query()->sole();

        $this->assertSame($tournament->id, $album->tournament_id);
        $this->assertSame('tournament', $album->type);
        $this->assertSame($tournament->name, $album->name, 'Nama album disalin dari turnamennya.');
        $this->assertSame($tournament->id, GalleryItem::query()->sole()->event->tournament_id);
    }

    /**
     * Nama turnamen TIDAK bisa diketik dari layar galeri.
     *
     * Ini yang diperbaiki: mengetiknya melahirkan turnamen kedua yang cuma ada
     * di galeri — tanpa tanggal, tanpa venue, dan tidak ikut berubah saat yang
     * asli diganti nama. Ditolak di server, bukan cuma disembunyikan di layar,
     * jadi permintaan yang dirakit tangan pun tidak bisa menembusnya.
     */
    public function test_a_tournament_cannot_be_typed_in_by_name(): void
    {
        Storage::fake('public');

        $this->actingAs(User::factory()->superAdmin()->create())
            ->post('/gallery', [
                'type' => 'tournament',
                'kind' => 'image',
                'event_mode' => 'new',
                'event_name' => 'Turnamen Karangan Sendiri',
                'posting' => 'now',
                'asset' => UploadedFile::fake()->image('a.webp', 800, 600),
            ])
            ->assertSessionHasErrors('tournament_id');

        $this->assertSame(0, GalleryEvent::query()->count());
    }

    /**
     * PERSIS yang dikirim formulirnya, `event_mode` dan semua.
     *
     * Tes di atas mengirim payload yang bersih, dan itu membuatnya buta: layar
     * Add Gallery memulai dengan `event_mode: 'new'` (bawaan untuk Event) dan
     * TIDAK membuangnya saat orang memilih Tournament — field-nya cuma tidak
     * digambar. Aturan `required_if:event_mode,new` karena itu menuntut
     * `event_name` yang tidak ada kotaknya di layar: yang dilihat orangnya
     * tombol Publish yang tidak melakukan apa-apa, dengan pesan galat yang
     * menempel pada field yang tak terlihat.
     *
     * Karena itu kedua field acara diikat ke `type`, bukan cuma ke
     * `event_mode`.
     */
    public function test_the_form_payload_for_a_tournament_is_accepted_as_is(): void
    {
        Storage::fake('public');
        $tournament = Tournament::factory()->create();

        $this->actingAs(User::factory()->superAdmin()->create())
            ->post('/gallery', [
                'type' => 'tournament',
                'kind' => 'image',
                'tournament_id' => $tournament->id,

                // Sisa keadaan formulir yang ikut terkirim.
                'event_mode' => 'new',
                'event_name' => '',
                'gallery_event_id' => null,

                'posting' => 'now',
                'asset' => UploadedFile::fake()->image('a.webp', 800, 600),
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/gallery');

        $this->assertSame($tournament->id, GalleryEvent::query()->sole()->tournament_id);
    }

    /** Satu turnamen, satu album — berapa kali pun diunggahi. */
    public function test_two_uploads_share_one_tournament_album(): void
    {
        Storage::fake('public');
        $tournament = Tournament::factory()->create();
        $user = User::factory()->superAdmin()->create();

        foreach (['a.webp', 'b.webp'] as $name) {
            $this->actingAs($user)->post('/gallery', [
                'type' => 'tournament',
                'kind' => 'image',
                'tournament_id' => $tournament->id,
                'posting' => 'now',
                'asset' => UploadedFile::fake()->image($name, 800, 600),
            ])->assertSessionHasNoErrors();
        }

        $this->assertSame(1, GalleryEvent::query()->count());
        $this->assertSame(2, GalleryItem::query()->count());
    }

    /**
     * Judul album mengikuti turnamennya, alamatnya tidak.
     *
     * Dua janji sekaligus, dan keduanya disengaja: nama punya satu sumber, tapi
     * slug adalah alamat publik album itu — alamat yang bergeser tiap judul
     * disunting adalah tautan yang mati di tempat orang menyimpannya.
     */
    public function test_the_album_follows_the_tournament_name_but_keeps_its_slug(): void
    {
        Storage::fake('public');
        $tournament = Tournament::factory()->create(['name' => 'Nama Lama']);
        $user = User::factory()->superAdmin()->create();

        $upload = fn (string $file) => $this->actingAs($user)->post('/gallery', [
            'type' => 'tournament',
            'kind' => 'image',
            'tournament_id' => $tournament->id,
            'posting' => 'now',
            'asset' => UploadedFile::fake()->image($file, 800, 600),
        ])->assertSessionHasNoErrors();

        $upload('a.webp');
        $slug = GalleryEvent::query()->sole()->slug;

        $tournament->update(['name' => 'Nama Baru']);
        $upload('b.webp');

        $album = GalleryEvent::query()->sole();
        $this->assertSame('Nama Baru', $album->name);
        $this->assertSame($slug, $album->slug);
    }

    /**
     * SEMUA turnamen bisa dipilih, apa pun statusnya.
     *
     * Galeri justru disiapkan sebelum turnamennya tayang; daftar yang cuma
     * memuat yang sudah tayang berarti fotonya baru bisa diunggah setelah
     * halamannya dibuka umum, dan urutan kerjanya justru kebalikannya.
     */
    public function test_a_draft_tournament_can_still_be_chosen(): void
    {
        Tournament::factory()->create(['name' => 'Masih Draft', 'status' => 'draft']);

        $this->actingAs(User::factory()->superAdmin()->create())
            ->get('/gallery/create')
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->component('Gallery/Form')
                ->where('tournaments.0.label', 'Masih Draft')
            );
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
