<?php

namespace App\Http\Requests\Api\V1;

use App\Support\BlogImages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class StoreMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The file type is checked against its contents, not the client filename. SVG is excluded.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /** A png, jpg/jpeg, webp, avif or gif image, up to 10 MB. The type is checked from the file contents, and SVG is rejected. */
            'file' => ['required', File::types(BlogImages::EXTENSIONS)->max(BlogImages::MAX_KILOBYTES)],
        ];
    }
}
