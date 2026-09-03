<?php

namespace Database\Factories;

use App\Models\StandingCommittee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StandingCommittee>
 */
class StandingCommitteeFactory extends Factory
{
    protected $model = StandingCommittee::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'remit' => fake()->words(3),
            'is_active' => true,
            'position' => 0,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
