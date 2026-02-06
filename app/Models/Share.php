<?php

namespace App\Models;

use App\Enums\SourceType;
use App\Models\Concerns\ClearsApiCache;
use App\Models\Concerns\HasSlug;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $url
 * @property SourceType $source_type
 * @property string|null $title
 * @property string $slug
 * @property string|null $description
 * @property string|null $image_url
 * @property string|null $site_name
 * @property string|null $commentary
 * @property array<string, mixed>|null $embed_data
 * @property array<string, mixed>|null $og_raw
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 * @method static \Database\Factories\ShareFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Share newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Share newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Share query()
 *
 * @mixin \Eloquent
 */
class Share extends Model
{
    /** @use HasFactory<\Database\Factories\ShareFactory> */
    use HasFactory;

    use ClearsApiCache;
    use HasSlug;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'url',
        'source_type',
        'title',
        'slug',
        'description',
        'image_url',
        'site_name',
        'commentary',
        'embed_data',
        'og_raw',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source_type' => SourceType::class,
            'embed_data' => 'array',
            'og_raw' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Share $share) {
            if (empty($share->slug) && empty($share->title)) {
                $host = parse_url($share->url, PHP_URL_HOST) ?? 'share';
                $host = preg_replace('/^www\./', '', $host);
                $share->slug = \Illuminate\Support\Str::slug($host.'-'.now()->timestamp);
            }
        });
    }

    public static function getApiCacheKey(): string
    {
        return 'api.v1.shares';
    }
}
