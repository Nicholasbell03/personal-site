<?php

namespace App\Models;

use App\Enums\PublishStatus;
use App\Models\Concerns\ClearsApiCache;
use App\Models\Concerns\HasPublishStatus;
use App\Models\Concerns\HasSlug;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $title
 * @property string $slug
 * @property string|null $excerpt
 * @property string $content
 * @property string|null $featured_image
 * @property PublishStatus $status
 * @property \Illuminate\Support\Carbon|null $published_at
 * @property string|null $meta_description
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int $read_time
 * @method static \Database\Factories\BlogFactory factory($count = null, $state = [])
 * @method static Builder<static>|Blog latestPublished()
 * @method static Builder<static>|Blog newModelQuery()
 * @method static Builder<static>|Blog newQuery()
 * @method static Builder<static>|Blog published()
 * @method static Builder<static>|Blog query()
 * @method static Builder<static>|Blog whereContent($value)
 * @method static Builder<static>|Blog whereCreatedAt($value)
 * @method static Builder<static>|Blog whereExcerpt($value)
 * @method static Builder<static>|Blog whereFeaturedImage($value)
 * @method static Builder<static>|Blog whereId($value)
 * @method static Builder<static>|Blog whereMetaDescription($value)
 * @method static Builder<static>|Blog wherePublishedAt($value)
 * @method static Builder<static>|Blog whereSlug($value)
 * @method static Builder<static>|Blog whereStatus($value)
 * @method static Builder<static>|Blog whereTitle($value)
 * @method static Builder<static>|Blog whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class Blog extends Model
{
    /** @use HasFactory<\Database\Factories\BlogFactory> */
    use HasFactory;

    use ClearsApiCache;
    use HasPublishStatus;
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
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PublishStatus::class,
            'published_at' => 'datetime',
            'read_time' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Blog $blog) {
            if ($blog->isDirty('content')) {
                $blog->read_time = (int) ceil(str_word_count(strip_tags($blog->content ?? '')) / 200);
            }
        });
    }

    /**
     * @param  Builder<Blog>  $query
     * @return Builder<Blog>
     */
    public function scopeLatestPublished(Builder $query): Builder
    {
        return $query->orderByDesc('published_at');
    }

    public static function getApiCacheKey(): string
    {
        return 'api.v1.blogs';
    }
}
