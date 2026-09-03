<?php

namespace Database\Factories;

use App\Models\BoardMember;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BoardMember>
 */
class BoardMemberFactory extends Factory
{
    protected $model = BoardMember::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'role' => fake()->randomElement(['President', 'Vice President', 'Secretary General', 'Treasurer']),
            'portrait_path' => null,
            'is_active' => true,
            'position' => 0,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
