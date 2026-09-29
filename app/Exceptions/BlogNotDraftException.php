<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * An expected refusal (409), logged as a warning by the caller. HTTP exceptions aren't reported as errors.
 */
class BlogNotDraftException extends ConflictHttpException
{
    public function __construct()
    {
        parent::__construct('Only draft blogs can be updated through the API.');
    }
}
