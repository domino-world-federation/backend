<?php

namespace Database\Factories;

use App\Models\GalleryEvent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<GalleryEvent> */
class GalleryEventFactory extends Factory
{
    protected $model = GalleryEvent::class;

    public function definition(): array
    {
        $name = rtrim($this->faker->words(3, true), '.');

        return [
            'name' => Str::title($name),
            'slug' => Str::slug($name).'-'.$this->faker->unique()->numberBetween(1, 99999),
            'type' => 'event',
        ];
    }

    public function tournament(): static
    {
        return $this->state(fn () => ['type' => 'tournament']);
    }
}
