<?php

namespace App\Actions;

use App\Enums\PublishStatus;
use App\Exceptions\BlogNotDraftException;
use App\Models\Blog;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

class UpdateBlogDraft
{
    /**
     * The only fields an agent may change. Status and publishing fields are deliberately excluded.
     *
     * @var list<string>
     */
    public const EDITABLE_FIELDS = ['title', 'slug', 'excerpt', 'content', 'meta_description', 'featured_image'];

    /**
     * Partially update a draft. The row is locked while checking its status so a post
     * published in the admin panel mid-request can't be edited.
     *
     * @param  array{title?: string, slug?: string, excerpt?: string|null, content?: string, meta_description?: string|null, featured_image?: string|null}  $attributes
     *
     * @throws BlogNotDraftException
     */
    public function execute(Blog $blog, array $attributes): Blog
    {
        return $blog->getConnection()->transaction(function () use ($blog, $attributes) {
            $blog = Blog::query()->lockForUpdate()->findOrFail($blog->id);

            if ($blog->status !== PublishStatus::Draft) {
                Log::warning('Refused API update of a non-draft blog', [
                    'blog_id' => $blog->id,
                    'status' => $blog->status->value,
                ]);

                throw new BlogNotDraftException;
            }

            $blog->update(Arr::only($attributes, self::EDITABLE_FIELDS));

            return $blog;
        });
    }
}
