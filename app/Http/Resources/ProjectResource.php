<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin \App\Models\Project
 */
class ProjectResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'description' => $this->description,
            'long_description' => $this->long_description,
            'featured_image' => $this->featured_image ? Storage::url($this->featured_image) : null,
            'project_url' => $this->project_url,
            'github_url' => $this->github_url,
            'technologies' => $this->technologies->pluck('name')->toArray(),
            'published_at' => $this->published_at,
        ];
    }
}
