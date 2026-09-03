<?php

namespace Database\Factories;

use App\Models\FederationStat;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FederationStat>
 */
class FederationStatFactory extends Factory
{
    protected $model = FederationStat::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'scope' => FederationStat::SCOPE_HOME,
            'label' => fake()->words(2, true),
            // String, bukan angka — "120+" harus muat di kolom yang sama.
            'value' => (string) fake()->numberBetween(10, 999),
            'is_active' => true,
            'position' => 0,
        ];
    }

    public function members(): static
    {
        return $this->state(fn () => ['scope' => FederationStat::SCOPE_MEMBERS]);
    }
}
