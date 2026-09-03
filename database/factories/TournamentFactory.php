<?php

namespace Database\Factories;

use App\Models\Tournament;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tournament>
 */
class TournamentFactory extends Factory
{
    protected $model = Tournament::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $name = fake()->unique()->city().' Domino Open';
        $start = fake()->dateTimeBetween('+1 month', '+6 months');
        $end = (clone $start)->modify('+3 days');

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 99999),
            'coverage' => fake()->randomElement(config('dwf.tournaments.coverage')),
            'starts_on' => $start,
            'ends_on' => $end,
            'city' => fake()->city(),
            'country' => fake()->country(),
            'rules_format' => fake()->randomElement(config('dwf.tournaments.rules_formats')),
            'hero_image_path' => 'tournaments/hero.webp',
            'overview' => fake()->paragraph(8),

            'venue_name' => fake()->company().' Arena',
            'venue_address' => fake()->address(),
            'venue_lat' => fake()->latitude(),
            'venue_lng' => fake()->longitude(),

            'eligibility' => fake()->randomElement(config('dwf.tournaments.eligibility')),
            'registration_method' => fake()->randomElement(config('dwf.tournaments.registration_methods')),

            'game_format' => 'Double-101',
            'participant_type' => fake()->randomElement(config('dwf.tournaments.participant_types')),
            'competition_system' => '16 groups of four; top two advance to knockout',
            'scoring' => 'First team to reach 101 points wins the match',

            'status' => Tournament::STATUS_PUBLISHED,
            'published_at' => now(),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => [
            'status' => Tournament::STATUS_DRAFT,
            'published_at' => null,
        ]);
    }

    /** Sudah selesai — `stage` menurunkannya dari tanggal, bukan kolom. */
    public function completed(): static
    {
        return $this->state(fn () => [
            'starts_on' => now()->subMonth(),
            'ends_on' => now()->subMonth()->addDays(3),
        ]);
    }

    /** Sedang berlangsung hari ini. */
    public function live(): static
    {
        return $this->state(fn () => [
            'starts_on' => now()->subDay(),
            'ends_on' => now()->addDay(),
        ]);
    }
}
