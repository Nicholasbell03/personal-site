<?php

namespace App\Models;

use App\Enums\PublishStatus;
use App\Models\Concerns\ClearsApiCache;
use App\Models\Concerns\HasPublishStatus;
use App\Models\Concerns\HasSlug;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property string $title
 * @property string $slug
 * @property string|null $description
 * @property string|null $long_description
 * @property string|null $featured_image
 * @property string|null $project_url
 * @property string|null $github_url
 * @property bool $is_featured
 * @property PublishStatus $status
 * @property \Illuminate\Support\Carbon|null $published_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Technology> $technologies
 * @method static \Database\Factories\ProjectFactory factory($count = null, $state = [])
 * @method static Builder<static>|Project featured()
 * @method static Builder<static>|Project latestPublished()
 * @method static Builder<static>|Project newModelQuery()
 * @method static Builder<static>|Project newQuery()
 * @method static Builder<static>|Project published()
 * @method static Builder<static>|Project query()
 * @property-read int|null $technologies_count
 * @method static Builder<static>|Project whereCreatedAt($value)
 * @method static Builder<static>|Project whereDescription($value)
 * @method static Builder<static>|Project whereFeaturedImage($value)
 * @method static Builder<static>|Project whereGithubUrl($value)
 * @method static Builder<static>|Project whereId($value)
 * @method static Builder<static>|Project whereIsFeatured($value)
 * @method static Builder<static>|Project whereLongDescription($value)
 * @method static Builder<static>|Project whereProjectUrl($value)
 * @method static Builder<static>|Project wherePublishedAt($value)
 * @method static Builder<static>|Project whereSlug($value)
 * @method static Builder<static>|Project whereStatus($value)
 * @method static Builder<static>|Project whereTitle($value)
 * @method static Builder<static>|Project whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class Project extends Model
{
    /** @use HasFactory<\Database\Factories\ProjectFactory> */
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
        'description',
        'long_description',
        'featured_image',
        'project_url',
        'github_url',
        'is_featured',
        'status',
        'published_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PublishStatus::class,
            'published_at' => 'datetime',
            'is_featured' => 'boolean',
        ];
    }

    /**
     * @return BelongsToMany<Technology, $this>
     */
    public function technologies(): BelongsToMany
    {
        return $this->belongsToMany(Technology::class)->withTimestamps();
    }

    /**
     * @param  Builder<Project>  $query
     * @return Builder<Project>
     */
    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }

    /**
     * @param  Builder<Project>  $query
     * @return Builder<Project>
     */
    public function scopeLatestPublished(Builder $query): Builder
    {
        return $query->orderByDesc('published_at');
    }

    public static function getApiCacheKey(): string
    {
        return 'api.v1.projects';
    }
}
