<?php

namespace Database\Factories;

use App\Models\SubCommittee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SubCommittee>
 */
class SubCommitteeFactory extends Factory
{
    protected $model = SubCommittee::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'href' => null,
            'is_active' => true,
            'position' => 0,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
