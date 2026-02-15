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
            'embed_data' => null,
            'og_raw' => null,
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
}
