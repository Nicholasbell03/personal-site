<?php

namespace Database\Factories;

use App\Enums\PublishStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Blog>
 */
class BlogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->unique()->sentence();

        $content = fake()->paragraphs(5, true);

        return [
            'title' => $title,
            'slug' => Str::slug($title),
            'excerpt' => fake()->paragraph(),
            'content' => $content,
            'featured_image' => null,
            'status' => PublishStatus::Draft,
            'published_at' => null,
            'meta_description' => fake()->sentence(),
            'read_time' => (int) ceil(str_word_count(strip_tags($content)) / 200),
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PublishStatus::Published,
            'published_at' => now(),
        ]);
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PublishStatus::Draft,
            'published_at' => null,
        ]);
    }
}
