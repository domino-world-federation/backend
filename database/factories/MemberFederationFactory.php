<?php

namespace Database\Factories;

use App\Models\MemberFederation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MemberFederation>
 */
class MemberFederationFactory extends Factory
{
    protected $model = MemberFederation::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $country = fake()->unique()->country();

        return [
            'name' => "{$country} Domino Federation",
            'country' => $country,
            'tier' => fake()->randomElement(array_keys(config('dwf.membership_tiers'))),
            'joined_year' => fake()->numberBetween(1974, 2025),
            'president' => fake()->name(),
            'headquarters' => fake()->city().', '.$country,
            'email' => fake()->unique()->safeEmail(),
            'phone' => '+'.fake()->numberBetween(1, 99).' '.fake()->numerify('### ### ###'),
            'website_url' => 'https://'.fake()->unique()->domainName(),
            'is_active' => true,
            'position' => 0,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
