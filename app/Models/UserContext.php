<?php

namespace App\Models;

use App\Enums\UserContextKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * @property UserContextKey $key
 * @property string $value
 */
class UserContext extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'value',
    ];

    protected function casts(): array
    {
        return [
            'key' => UserContextKey::class,
        ];
    }

    /**
     * Retrieve a cached value by key.
     */
    public static function cached(UserContextKey $key): ?string
    {
        return Cache::rememberForever("user_context.{$key->value}", function () use ($key) {
            return static::where('key', $key)->value('value');
        });
    }

    /**
     * Bust the cache for the given key.
     */
    public static function bustCache(UserContextKey $key): void
    {
        Cache::forget("user_context.{$key->value}");
    }

    protected static function booted(): void
    {
        static::saved(function (UserContext $context) {
            static::bustCache($context->key);
        });

        static::deleted(function (UserContext $context) {
            static::bustCache($context->key);
        });
    }
}
