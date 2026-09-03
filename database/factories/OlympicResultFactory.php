<?php

namespace Database\Factories;

use App\Models\OlympicResult;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OlympicResult>
 */
class OlympicResultFactory extends Factory
{
    protected $model = OlympicResult::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'year' => (string) fake()->numberBetween(2018, 2026),
            'event' => fake()->sentence(3),
            'category' => fake()->randomElement(['Singles', 'Doubles', 'Teams']),
            'winners' => fake()->name(),
            'federation' => fake()->country(),
            'is_active' => true,
            'position' => 0,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
