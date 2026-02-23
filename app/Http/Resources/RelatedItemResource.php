<?php

namespace App\Http\Resources;

use App\Models\Blog;
use App\Models\Project;
use App\Models\Share;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * Polymorphic resource that normalises Blog/Project/Share into a unified shape.
 *
 * @mixin Blog|Project|Share
 */
class RelatedItemResource extends JsonResource
{
    private const TYPE_MAP = [
        Blog::class => 'blog',
        Project::class => 'project',
        Share::class => 'share',
    ];

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'type' => self::TYPE_MAP[$this->resource::class] ?? 'unknown',
            'title' => $this->resource->title,
            'slug' => $this->resource->slug,
            'description' => $this->getDescription(),
            'image' => $this->getImage(),
            'published_at' => $this->getPublishedAt(),
        ];
    }

    private function getDescription(): ?string
    {
        return match (true) {
            $this->resource instanceof Blog => $this->resource->excerpt,
            $this->resource instanceof Project => $this->resource->description,
            $this->resource instanceof Share => $this->resource->description,
            default => null,
        };
    }

    private function getImage(): ?string
    {
        return match (true) {
            $this->resource instanceof Blog => $this->resource->featured_image
                ? Storage::url($this->resource->featured_image)
                : null,
            $this->resource instanceof Project => $this->resource->featured_image
                ? Storage::url($this->resource->featured_image)
                : null,
            $this->resource instanceof Share => $this->resource->image_url,
            default => null,
        };
    }

    private function getPublishedAt(): ?string
    {
        return match (true) {
            $this->resource instanceof Share => $this->resource->created_at?->toISOString(),
            default => $this->resource->published_at?->toISOString(),
        };
    }
}
