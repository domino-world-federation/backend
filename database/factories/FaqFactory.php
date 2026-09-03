<?php

namespace Database\Factories;

use App\Models\Faq;
use App\Models\FaqCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Faq> */
class FaqFactory extends Factory
{
    protected $model = Faq::class;

    public function definition(): array
    {
        return [
            'faq_category_id' => FaqCategory::factory(),
            'question' => rtrim($this->faker->sentence(8), '.').'?',
            'answer' => '<p>'.$this->faker->paragraph(4).'</p>',
            'is_active' => true,
            'position' => $this->faker->unique()->numberBetween(1, 99999),
        ];
    }

    /**
     * Menempelkan FAQ ke sebuah halaman pada peringkat tertentu.
     *
     * Factory TIDAK menempelkan apa pun secara bawaan — `Faq::MAX_PER_PAGE`
     * membatasi berapa yang boleh menempel di satu halaman, dan factory yang
     * memilih sendiri akan membuat tes gagal berdasarkan urutan pembuatan.
     */
    public function on(string $page, int $position = 1): static
    {
        return $this->afterCreating(fn (Faq $faq) => $faq->placements()->create([
            'page' => $page,
            'position' => $position,
        ]));
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
