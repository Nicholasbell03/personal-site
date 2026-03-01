<?php

namespace Database\Factories;

use App\Enums\SourceType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Share>
 */
class ShareFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->unique()->sentence();

        return [
            'url' => fake()->url(),
            'source_type' => SourceType::Webpage,
            'title' => $title,
            'slug' => Str::slug($title),
            'description' => fake()->paragraph(),
            'image_url' => fake()->url().'/image.jpg',
            'site_name' => fake()->domainName(),
            'author' => fake()->name(),
            'commentary' => fake()->paragraphs(2, true),
            'summary' => fake()->sentence(),
            'embed_data' => null,
            'og_raw' => null,
            'post_to_x' => true,
            'x_post_id' => null,
        ];
    }

    public function youtube(): static
    {
        return $this->state(fn (array $attributes) => [
            'url' => 'https://www.youtube.com/watch?v='.fake()->regexify('[a-zA-Z0-9_-]{11}'),
            'source_type' => SourceType::Youtube,
            'author' => fake()->name(),
            'embed_data' => ['video_id' => fake()->regexify('[a-zA-Z0-9_-]{11}')],
        ]);
    }

    public function xPost(): static
    {
        $username = fake()->userName();

        return $this->state(fn (array $attributes) => [
            'url' => 'https://x.com/'.$username.'/status/'.fake()->numerify('####################'),
            'source_type' => SourceType::XPost,
            'author' => '@'.$username,
            'embed_data' => ['tweet_id' => fake()->numerify('####################')],
        ]);
    }

    public function withSummary(?string $summary = null): static
    {
        return $this->state(fn (array $attributes) => [
            'summary' => $summary ?? fake()->sentence(),
        ]);
    }

    public function withoutSummary(): static
    {
        return $this->state(fn (array $attributes) => [
            'summary' => null,
        ]);
    }

    public function withoutCommentary(): static
    {
        return $this->state(fn (array $attributes) => [
            'commentary' => null,
        ]);
    }

    public function postedToX(): static
    {
        return $this->state(fn (array $attributes) => [
            'post_to_x' => true,
            'x_post_id' => fake()->numerify('####################'),
        ]);
    }

    public function withoutXPosting(): static
    {
        return $this->state(fn (array $attributes) => [
            'post_to_x' => false,
            'x_post_id' => null,
        ]);
    }

    public function linkedin(): static
    {
        $slug = fake()->slug(3);

        return $this->state(fn (array $attributes) => [
            'url' => 'https://www.linkedin.com/posts/'.$slug,
            'source_type' => SourceType::LinkedIn,
            'author' => fake()->name(),
            'embed_data' => null,
        ]);
    }
}
