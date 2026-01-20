<?php

namespace App\Models;

use App\Enums\BlogStatus;
use App\Models\Concerns\HasSlug;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Blog extends Model
{
    /** @use HasFactory<\Database\Factories\BlogFactory> */
    use HasFactory;
    use HasSlug;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'title',
        'slug',
        'excerpt',
        'content',
        'featured_image',
        'status',
        'published_at',
        'meta_description',
    ];

    /**
     * @var list<string>
     */
    protected $appends = [
        'read_time',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => BlogStatus::class,
            'published_at' => 'datetime',
        ];
    }

    /**
     * @return Attribute<int, never>
     */
    protected function readTime(): Attribute
    {
        return Attribute::make(
            get: fn (): int => (int) ceil(str_word_count(strip_tags($this->content ?? '')) / 200)
        );
    }

    /**
     * @param Builder<Blog> $query
     * @return Builder<Blog>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', BlogStatus::Published)
            ->whereNotNull('published_at');
    }

    /**
     * @param Builder<Blog> $query
     * @return Builder<Blog>
     */
    public function scopeLatestPublished(Builder $query): Builder
    {
        return $query->orderByDesc('published_at');
    }
}
