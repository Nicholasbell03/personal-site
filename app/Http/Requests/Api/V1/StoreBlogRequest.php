<?php

namespace App\Http\Requests\Api\V1;

use App\Rules\StoredBlogImage;
use App\Support\BlogImages;
use Illuminate\Foundation\Http\FormRequest;

class StoreBlogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:blogs,slug'],
            'excerpt' => ['nullable', 'string', 'max:230'],
            'meta_description' => ['nullable', 'string', 'max:255'],
            /** A `path` or `url` returned by `POST /api/v1/media`. */
            'featured_image' => ['nullable', 'string', new StoredBlogImage],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('featured_image'))) {
            $this->merge(['featured_image' => BlogImages::pathFromUrl($this->input('featured_image'))]);
        }
    }
}
