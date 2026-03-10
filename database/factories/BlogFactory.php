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
            'excerpt' => fake()->text(230),
            'content' => $content,
            'featured_image' => null,
            'status' => PublishStatus::Draft,
            'published_at' => null,
            'meta_description' => fake()->sentence(),
            'read_time' => (int) ceil(str_word_count(strip_tags($content)) / 200),
            'post_to_x' => true,
            'post_to_linkedin' => true,
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

    public function postedToX(): static
    {
        return $this->state(fn (array $attributes) => [
            'x_post_id' => (string) fake()->unique()->numberBetween(1000000000, 9999999999),
        ]);
    }

    public function postedToLinkedIn(): static
    {
        return $this->state(fn (array $attributes) => [
            'linkedin_post_id' => 'urn:li:share:'.fake()->unique()->numberBetween(1000000000, 9999999999),
        ]);
    }
}
