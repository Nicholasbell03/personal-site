<?php

namespace App\Rules;

use App\Support\BlogImages;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * The value must be the path of an image previously uploaded through POST /api/v1/media.
 */
class StoredBlogImage implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! BlogImages::isStoredImage($value)) {
            $fail('The :attribute must be a path or URL returned by POST /api/v1/media.');
        }
    }
}
