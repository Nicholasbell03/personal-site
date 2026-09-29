<?php

namespace App\Exceptions;

use App\Models\Blog;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * An expected refusal (409), logged as a warning when created. HTTP exceptions aren't reported as errors.
 */
class BlogNotDraftException extends ConflictHttpException
{
    public function __construct()
    {
        parent::__construct('Only draft blogs can be updated through the API.');
    }

    public static function for(Blog $blog): self
    {
        Log::warning('Refused API update of a non-draft blog', [
            'blog_id' => $blog->id,
            'status' => $blog->status->value,
        ]);

        return new self;
    }
}
