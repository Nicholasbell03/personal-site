<?php

namespace Database\Factories;

use App\Enums\PublishStatus;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Project>
 */
class ProjectFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = Project::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->unique()->sentence(3);

        return [
            'title' => $title,
            'slug' => Str::slug($title),
            'description' => fake()->paragraph(),
            'long_description' => fake()->paragraphs(3, true),
            'featured_image' => null,
            'project_url' => fake()->optional()->url(),
            'github_url' => fake()->optional()->url(),
            'is_featured' => false,
            'status' => PublishStatus::Draft,
            'published_at' => null,
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

    public function featured(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_featured' => true,
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
