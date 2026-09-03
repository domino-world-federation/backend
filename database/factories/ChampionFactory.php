<?php

namespace Database\Factories;

use App\Models\Champion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Champion>
 */
class ChampionFactory extends Factory
{
    protected $model = Champion::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'event' => fake()->numberBetween(2018, 2026).' World Championship',
            'name' => fake()->name(),
            'portrait_path' => null,
            'portrait_alt' => null,
            'is_active' => true,
            'position' => 0,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
