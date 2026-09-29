<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\PublishStatus;
use App\Exceptions\BlogNotDraftException;
use App\Models\Blog;
use App\Rules\StoredBlogImage;
use App\Support\BlogImages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBlogRequest extends FormRequest
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
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'content' => ['sometimes', 'required', 'string'],
            'slug' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('blogs', 'slug')->ignore($this->route('blog'))],
            'excerpt' => ['sometimes', 'nullable', 'string', 'max:230'],
            'meta_description' => ['sometimes', 'nullable', 'string', 'max:255'],
            /** A `path` or `url` returned by `POST /api/v1/media`, or `null` to remove the image. */
            'featured_image' => ['sometimes', 'nullable', 'string', new StoredBlogImage],
        ];
    }

    /**
     * Refuse published blogs before validating, so they always get a 409 rather than a 422.
     * UpdateBlogDraft repeats the check under a row lock, in case the blog is published mid-request.
     *
     * @throws BlogNotDraftException
     */
    protected function prepareForValidation(): void
    {
        /** @var Blog $blog */
        $blog = $this->route('blog');

        if ($blog->status !== PublishStatus::Draft) {
            throw BlogNotDraftException::for($blog);
        }

        if (is_string($this->input('featured_image'))) {
            $this->merge(['featured_image' => BlogImages::pathFromUrl($this->input('featured_image'))]);
        }
    }
}
