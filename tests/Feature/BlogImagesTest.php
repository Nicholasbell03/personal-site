<?php

use App\Support\BlogImages;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    config(['filament.default_filesystem_disk' => 'r2']);
    Storage::fake('r2');
});

it('only treats an exact blog-images path as a stored image', function (string $path, bool $expected) {
    Storage::disk('r2')->put('blog-images/abc.png', 'x');

    expect(BlogImages::isStoredImage($path))->toBe($expected);
})->with([
    'stored image' => ['blog-images/abc.png', true],
    'trailing newline' => ["blog-images/abc.png\n", false],
    'not uploaded' => ['blog-images/missing.png', false],
    'traversal' => ['blog-images/../secret.png', false],
]);
