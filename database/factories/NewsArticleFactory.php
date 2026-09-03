<?php

namespace Database\Factories;

use App\Models\NewsArticle;
use App\Models\NewsCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<NewsArticle> */
class NewsArticleFactory extends Factory
{
    protected $model = NewsArticle::class;

    public function definition(): array
    {
        $title = rtrim($this->faker->sentence(6), '.');

        return [
            'news_category_id' => NewsCategory::factory(),
            'author_id' => User::factory(),
            'title' => $title,
            'slug' => Str::slug($title).'-'.$this->faker->unique()->numberBetween(1, 99999),
            'excerpt' => $this->faker->sentence(14),
            'body' => '<p>'.$this->faker->paragraph(6).'</p>',
            'is_highlighted' => false,
            'status' => NewsArticle::STATUS_PUBLISHED,
            'published_at' => now()->subDays($this->faker->numberBetween(0, 60)),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => ['status' => NewsArticle::STATUS_DRAFT, 'published_at' => null]);
    }

    public function scheduled(): static
    {
        return $this->state(fn () => [
            'status' => NewsArticle::STATUS_SCHEDULED,
            'published_at' => now()->addDays(3),
        ]);
    }
}
