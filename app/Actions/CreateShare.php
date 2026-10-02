<?php

namespace App\Actions;

use App\Models\Share;
use App\Services\OpenGraphService;

class CreateShare
{
    public function __construct(private OpenGraphService $openGraphService) {}

    /**
     * Create a share from a URL, filling anything not provided from the page's Open Graph data.
     * Non-fatal problems during creation are collected on $share->creationWarnings.
     *
     * @param  array{url: string, title?: string|null, description?: string|null, commentary?: string|null, post_to_x?: bool}  $attributes
     */
    public function execute(array $attributes): Share
    {
        $ogData = $this->openGraphService->fetch($attributes['url']);

        return Share::create([
            'url' => $attributes['url'],
            'source_type' => $ogData['source_type'],
            'title' => $attributes['title'] ?? $ogData['title'],
            'description' => $attributes['description'] ?? $ogData['description'],
            'image_url' => $ogData['image'],
            'site_name' => $ogData['site_name'],
            'author' => $ogData['author'],
            'commentary' => $attributes['commentary'] ?? null,
            'embed_data' => $ogData['embed_data'],
            'og_raw' => $ogData['og_raw'],
            // Opt-in: an API client (or a leaked token) shouldn't post to X unless asked to.
            'post_to_x' => $attributes['post_to_x'] ?? false,
        ]);
    }
}
