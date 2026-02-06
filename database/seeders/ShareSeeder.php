<?php

namespace Database\Seeders;

use App\Models\Share;
use Illuminate\Database\Seeder;

class ShareSeeder extends Seeder
{
    public function run(): void
    {
        Share::factory()->create([
            'url' => 'https://laravel.com/docs/12.x/eloquent',
            'title' => 'Eloquent ORM - Laravel Documentation',
            'description' => 'The Eloquent ORM included with Laravel provides a beautiful, simple ActiveRecord implementation for working with your database.',
            'site_name' => 'laravel.com',
            'image_url' => null,
            'commentary' => '<p>The Eloquent docs are always worth revisiting. Every time I go back I find something I missed.</p>',
        ]);

        Share::factory()->youtube()->create([
            'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'title' => 'An Interesting Tech Talk',
            'description' => 'A fascinating deep dive into modern web development patterns.',
            'site_name' => 'YouTube',
            'embed_data' => ['video_id' => 'dQw4w9WgXcQ'],
            'commentary' => '<p>Great talk covering some patterns I use daily.</p>',
        ]);

        Share::factory()->xPost()->create([
            'url' => 'https://x.com/taylorotwell/status/1234567890',
            'title' => 'Taylor Otwell on Twitter',
            'description' => 'Exciting announcement about the future of Laravel.',
            'site_name' => 'X',
            'embed_data' => ['tweet_id' => '1234567890'],
            'commentary' => '<p>Big news from the Laravel ecosystem.</p>',
        ]);
    }
}
