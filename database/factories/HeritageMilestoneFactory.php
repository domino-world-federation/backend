<?php

namespace Database\Factories;

use App\Models\HeritageMilestone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HeritageMilestone>
 */
class HeritageMilestoneFactory extends Factory
{
    protected $model = HeritageMilestone::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'year' => (string) fake()->numberBetween(1974, 2026),
            'title' => fake()->sentence(3),
            'summary' => fake()->paragraph(),
            'is_active' => true,
            'position' => 0,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
