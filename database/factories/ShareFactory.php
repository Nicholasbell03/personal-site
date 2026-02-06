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
            'image_url' => fake()->imageUrl(),
            'site_name' => fake()->domainName(),
            'commentary' => fake()->paragraphs(2, true),
            'embed_data' => null,
            'og_raw' => null,
        ];
    }

    public function youtube(): static
    {
        return $this->state(fn (array $attributes) => [
            'url' => 'https://www.youtube.com/watch?v='.fake()->regexify('[a-zA-Z0-9_-]{11}'),
            'source_type' => SourceType::Youtube,
            'embed_data' => ['video_id' => fake()->regexify('[a-zA-Z0-9_-]{11}')],
        ]);
    }

    public function xPost(): static
    {
        return $this->state(fn (array $attributes) => [
            'url' => 'https://x.com/'.fake()->userName().'/status/'.fake()->numerify('####################'),
            'source_type' => SourceType::XPost,
            'embed_data' => ['tweet_id' => fake()->numerify('####################')],
        ]);
    }
}
