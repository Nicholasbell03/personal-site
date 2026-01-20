<?php

namespace App\Observers;

use App\Enums\BlogStatus;
use App\Models\Blog;

class BlogObserver
{
    /**
     * Handle the Blog "saving" event.
     */
    public function saving(Blog $blog): void
    {
        if ($blog->isDirty('status')) {
            if ($blog->status === BlogStatus::Published && $blog->published_at === null) {
                $blog->published_at = now();
            }

            if ($blog->status === BlogStatus::Draft) {
                $blog->published_at = null;
            }
        }
    }
}
