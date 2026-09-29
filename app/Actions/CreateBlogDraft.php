<?php

namespace App\Actions;

use App\Enums\PublishStatus;
use App\Models\Blog;

class CreateBlogDraft
{
    /**
     * @param  array{title: string, content: string, slug?: string|null, excerpt?: string|null, meta_description?: string|null, featured_image?: string|null}  $attributes
     */
    public function execute(array $attributes): Blog
    {
        return Blog::create([
            ...$attributes,
            'status' => PublishStatus::Draft,
        ]);
    }
}
