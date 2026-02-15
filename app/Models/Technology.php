<?php

namespace App\Models;

use App\Models\Concerns\HasSlug;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property bool $is_featured
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Project> $projects
 * @property-read int|null $projects_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Technology newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Technology newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Technology query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Technology featured()
 * @method static \Database\Factories\TechnologyFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Technology whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Technology whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Technology whereIsFeatured($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Technology whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Technology whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Technology whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class Technology extends Model
{
    use HasFactory;
    use HasSlug;

    private const MAX_FEATURED = 12;

    /**
     * The attribute to generate the slug from.
     */
    protected string $slugSource = 'name';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'is_featured',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_featured' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Technology $technology) {
            if ($technology->is_featured && $technology->isDirty('is_featured')) {
                $count = static::query()
                    ->where('is_featured', true)
                    ->where('id', '!=', $technology->id ?? 0)
                    ->count();

                if ($count >= self::MAX_FEATURED) {
                    throw ValidationException::withMessages([
                        'is_featured' => 'Maximum of '.self::MAX_FEATURED.' featured technologies allowed.',
                    ]);
                }
            }
        });

        static::saved(function () {
            Cache::forget('api.v1.technologies');
        });

        static::deleted(function () {
            Cache::forget('api.v1.technologies');
        });
    }

    /**
     * @return BelongsToMany<Project, $this>
     */
    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class)->withTimestamps();
    }

    /**
     * @param  Builder<Technology>  $query
     * @return Builder<Technology>
     */
    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }
}
