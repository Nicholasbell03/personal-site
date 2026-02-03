<?php

namespace App\Models;

use App\Models\Concerns\HasSlug;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Project> $projects
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Technology newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Technology newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Technology query()
 * @property-read int|null $projects_count
 * @method static \Database\Factories\TechnologyFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Technology whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Technology whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Technology whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Technology whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Technology whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class Technology extends Model
{
    use HasFactory;
    use HasSlug;

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
    ];

    /**
     * @return BelongsToMany<Project, $this>
     */
    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class)->withTimestamps();
    }
}
