<?php

namespace Database\Factories;

use App\Models\GalleryEvent;
use App\Models\GalleryItem;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<GalleryItem> */
class GalleryItemFactory extends Factory
{
    protected $model = GalleryItem::class;

    public function definition(): array
    {
        $alt = rtrim($this->faker->sentence(4), '.');

        return [
            'gallery_event_id' => GalleryEvent::factory(),
            'kind' => 'image',
            'path' => 'gallery/'.Str::random(20).'.webp',
            'slug' => Str::slug($alt).'-'.$this->faker->unique()->numberBetween(1, 99999),
            'alt' => $alt,
            'status' => GalleryItem::STATUS_PUBLISHED,
            'published_at' => now()->subDays($this->faker->numberBetween(0, 30)),
            'position' => $this->faker->unique()->numberBetween(1, 99999),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => ['status' => GalleryItem::STATUS_DRAFT, 'published_at' => null]);
    }
}
