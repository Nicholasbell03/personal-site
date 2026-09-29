<?php

namespace App\Support;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Where blog images live: the same disk and directory as the Filament featured_image upload.
 */
class BlogImages
{
    public const DIRECTORY = 'blog-images';

    public const MAX_KILOBYTES = 10 * 1024;

    /**
     * @var list<string>
     */
    public const EXTENSIONS = ['png', 'jpg', 'jpeg', 'webp', 'avif', 'gif'];

    public static function disk(): Filesystem
    {
        return Storage::disk(config('filament.default_filesystem_disk'));
    }

    public static function url(string $path): string
    {
        return self::disk()->url($path);
    }

    /**
     * Whether the path points at an existing image inside the blog images directory.
     */
    public static function isStoredImage(string $path): bool
    {
        $extensions = implode('|', self::EXTENSIONS);

        return preg_match('#^'.self::DIRECTORY.'/[A-Za-z0-9_-]+\.('.$extensions.')$#D', $path) === 1
            && self::disk()->exists($path);
    }

    /**
     * Turn a public URL returned by POST /api/v1/media back into its storage path by removing the
     * disk's base URL, which may itself include a path (e.g. the local public disk's `/storage`).
     * Paths, and URLs that don't belong to the disk, are returned unchanged.
     */
    public static function pathFromUrl(string $value): string
    {
        $baseUrl = rtrim(self::url(''), '/').'/';

        return Str::chopStart($value, $baseUrl);
    }
}
