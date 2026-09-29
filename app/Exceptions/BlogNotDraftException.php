<?php

namespace App\Exceptions;

use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\JsonResponse;

/**
 * An expected refusal, logged as a warning by the caller rather than reported as an error.
 */
class BlogNotDraftException extends \RuntimeException implements ShouldntReport
{
    public function __construct()
    {
        parent::__construct('Only draft blogs can be updated through the API.');
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], 409);
    }
}
