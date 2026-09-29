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
            'file' => ['required', File::types(BlogImages::EXTENSIONS)->max(BlogImages::MAX_KILOBYTES)],
        ];
    }
}
