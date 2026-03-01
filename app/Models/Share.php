<?php

namespace App\Models;

use App\Enums\SourceType;
use App\Models\Concerns\ClearsApiCache;
use App\Models\Concerns\HasEmbedding;
use App\Models\Concerns\HasSlug;
use App\Models\Concerns\HasSummary;
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
 * @property string|null $author
 * @property string|null $commentary
 * @property string|null $summary
 * @property array<string, mixed>|null $embed_data
 * @property array<string, mixed>|null $og_raw
 * @property bool $post_to_x
 * @property string|null $x_post_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property array<int, float>|null $embedding
 * @property \Illuminate\Support\Carbon|null $embedding_generated_at
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
    use HasEmbedding;
    use HasSlug;
    use HasSummary;

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
        'author',
        'commentary',
        'summary',
        'embed_data',
        'og_raw',
        'post_to_x',
        'x_post_id',
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
            'post_to_x' => 'boolean',
            'embedding' => 'array',
            'embedding_generated_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Share $share) {
            if (empty($share->slug) && empty($share->title)) {
                $host = parse_url($share->url, PHP_URL_HOST) ?? 'share';
                $host = preg_replace('/^www\./', '', $host);
                $share->slug = static::ensureUniqueSlug(
                    \Illuminate\Support\Str::slug($host.'-'.now()->timestamp)
                );
            }
        });
    }

    public static function getApiCacheKey(): string
    {
        return 'api.v1.shares';
    }

    public function getEmbeddableText(): string
    {
        return implode("\n", array_filter([
            $this->title,
            $this->description,
            $this->commentary ? 'My thoughts: '.$this->commentary : null,
        ]));
    }

    /**
     * @return list<string>
     */
    public function getEmbeddableFields(): array
    {
        return ['title', 'description', 'commentary'];
    }
}
