<?php

namespace Database\Factories;

use App\Models\IntegrityReport;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<IntegrityReport> */
class IntegrityReportFactory extends Factory
{
    protected $model = IntegrityReport::class;

    public function definition(): array
    {
        return [
            'type' => $this->faker->randomElement(IntegrityReport::TYPES),
            'description' => $this->faker->paragraph(4),
            'read_at' => null,
        ];
    }

    public function read(): static
    {
        return $this->state(fn () => ['read_at' => now()->subDay()]);
    }
}
