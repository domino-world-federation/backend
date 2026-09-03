<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EditorImageTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_editor_can_insert_an_image(): void
    {
        Storage::fake('public');

        $response = $this->actingAs(User::factory()->withRole('editor')->create())
            ->post('/editor/images', [
                'image' => UploadedFile::fake()->image('gambar.webp', 1200, 800),
            ]);

        $response->assertOk()->assertJsonStructure(['url']);

        $this->assertCount(1, Storage::disk('public')->files('editor'));
    }

    public function test_only_webp_is_accepted(): void
    {
        Storage::fake('public');

        $this->actingAs(User::factory()->withRole('editor')->create())
            ->postJson('/editor/images', [
                'image' => UploadedFile::fake()->image('gambar.png', 1200, 800),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('image');
    }

    public function test_a_tiny_image_is_refused(): void
    {
        Storage::fake('public');

        $this->actingAs(User::factory()->withRole('editor')->create())
            ->postJson('/editor/images', [
                'image' => UploadedFile::fake()->image('ikon.webp', 32, 32),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('image');
    }

    /**
     * Titik unggah tidak boleh terbuka untuk siapa pun yang sekadar punya akun.
     *
     * `viewer` bisa masuk dan hanya boleh membaca; memberinya endpoint ini
     * berarti memberi setiap pemegang akun sebuah tempat menaruh berkas di
     * server, tanpa satu pun layar yang menampilkannya kembali.
     */
    public function test_a_read_only_account_cannot_upload(): void
    {
        Storage::fake('public');

        $this->actingAs(User::factory()->withRole('viewer')->create())
            ->postJson('/editor/images', [
                'image' => UploadedFile::fake()->image('gambar.webp', 1200, 800),
            ])
            ->assertForbidden();

        $this->assertCount(0, Storage::disk('public')->files('editor'));
    }

    public function test_a_guest_cannot_upload(): void
    {
        $this->post('/editor/images', [
            'image' => UploadedFile::fake()->image('gambar.webp', 1200, 800),
        ])->assertRedirect('/login');
    }
}
