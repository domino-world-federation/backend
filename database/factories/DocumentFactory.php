<?php

namespace Database\Factories;

use App\Models\Document;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Document> */
class DocumentFactory extends Factory
{
    protected $model = Document::class;

    public function definition(): array
    {
        $title = rtrim($this->faker->sentence(5), '.');

        return [
            'title' => $title,
            'slug' => Str::slug($title).'-'.$this->faker->unique()->numberBetween(1, 99999),
            'file_path' => 'documents/'.Str::random(20).'.pdf',
            'file_size' => $this->faker->numberBetween(50_000, 4_000_000),
            'category' => $this->faker->randomElement(config('dwf.document_categories')),
            'status' => Document::STATUS_PUBLISHED,
            'published_at' => now(),
        ];
    }
}
