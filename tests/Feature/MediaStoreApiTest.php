<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * GD isn't installed in Docker or CI, so fixtures are built from real magic bytes. A real
 * UploadedFile is used (not UploadedFile::fake(), which reports a MIME type from the filename)
 * so validation sniffs the contents exactly as it does for a live request.
 */
function imageUpload(string $filename, string $format): UploadedFile
{
    $bytes = match ($format) {
        'png' => base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='),
        'jpg' => base64_decode('/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////wgALCAABAAEBAREA/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPxA='),
        'gif' => base64_decode('R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw=='),
        'webp' => base64_decode('UklGRhoAAABXRUJQVlA4TA0AAAAvAAAAEAcQERGIiP4HAA=='),
        'avif' => "\x00\x00\x00\x1cftypavif\x00\x00\x00\x00avifmif1miaf",
        'svg' => '<svg xmlns="http://www.w3.org/2000/svg" width="1" height="1"/>',
    };

    return uploadWithContent($filename, $bytes);
}

function uploadWithContent(string $filename, string $bytes): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'upload');
    file_put_contents($path, $bytes);

    return new UploadedFile($path, $filename, null, null, true);
}

beforeEach(function () {
    config([
        'filesystems.default' => 'r2',
        'filament.default_filesystem_disk' => 'r2',
    ]);
    Storage::fake('r2', ['url' => 'https://assets.nickbell.dev']);

    $this->token = User::factory()->create()->createToken('test')->plainTextToken;
});

it('stores an uploaded image and returns its path and url', function (string $filename, string $format, string $storedExtension) {
    $response = $this->withHeader('Authorization', "Bearer {$this->token}")
        ->postJson('/api/v1/media', ['file' => imageUpload($filename, $format)]);

    $response->assertCreated()
        ->assertJsonStructure(['data' => ['path', 'url']]);

    $path = $response->json('data.path');

    expect($path)->toMatch('#^blog-images/[0-9A-HJKMNP-TV-Z]{26}\.'.$storedExtension.'$#')
        ->and($response->json('data.url'))->toBe("https://assets.nickbell.dev/{$path}");

    Storage::disk('r2')->assertExists($path);
    expect(Storage::disk('r2')->getVisibility($path))->toBe('public');
})->with([
    'png' => ['screenshot.png', 'png', 'png'],
    'jpg' => ['photo.jpg', 'jpg', 'jpg'],
    'jpeg' => ['photo.jpeg', 'jpg', 'jpg'],
    'webp' => ['image.webp', 'webp', 'webp'],
    'avif' => ['image.avif', 'avif', 'avif'],
    'gif' => ['anim.gif', 'gif', 'gif'],
]);

it('names the stored file by its contents, not the client extension', function () {
    $response = $this->withHeader('Authorization', "Bearer {$this->token}")
        ->postJson('/api/v1/media', ['file' => imageUpload('mislabelled.gif', 'png')]);

    $response->assertCreated();
    expect($response->json('data.path'))->toEndWith('.png');
});

it('rejects svg uploads', function (string $filename) {
    $response = $this->withHeader('Authorization', "Bearer {$this->token}")
        ->postJson('/api/v1/media', ['file' => imageUpload($filename, 'svg')]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['file']);

    expect(Storage::disk('r2')->allFiles())->toBeEmpty();
})->with([
    'svg extension' => ['logo.svg'],
    'svg disguised as png' => ['logo.png'],
]);

it('rejects non-image files', function (UploadedFile $file) {
    $response = $this->withHeader('Authorization', "Bearer {$this->token}")
        ->postJson('/api/v1/media', ['file' => $file]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['file']);

    expect(Storage::disk('r2')->allFiles())->toBeEmpty();
})->with([
    'pdf' => fn () => uploadWithContent('doc.pdf', "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n"),
    'text named as png' => fn () => uploadWithContent('notes.png', 'just some text'),
    'php named as png' => fn () => uploadWithContent('shell.png', '<?php echo "hi";'),
]);

it('rejects files over 10 MB', function () {
    $file = UploadedFile::fake()->create('huge.png', 10 * 1024 + 1, 'image/png');

    $response = $this->withHeader('Authorization', "Bearer {$this->token}")
        ->postJson('/api/v1/media', ['file' => $file]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['file']);

    expect(Storage::disk('r2')->allFiles())->toBeEmpty();
});

it('accepts files of exactly 10 MB', function () {
    $file = UploadedFile::fake()->create('big.png', 10 * 1024, 'image/png');

    $this->withHeader('Authorization', "Bearer {$this->token}")
        ->postJson('/api/v1/media', ['file' => $file])
        ->assertCreated();
});

it('requires a file', function () {
    $this->withHeader('Authorization', "Bearer {$this->token}")
        ->postJson('/api/v1/media', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['file']);
});

it('rejects unauthenticated uploads', function () {
    $this->postJson('/api/v1/media', ['file' => imageUpload('screenshot.png', 'png')])
        ->assertUnauthorized();

    expect(Storage::disk('r2')->allFiles())->toBeEmpty();
});
