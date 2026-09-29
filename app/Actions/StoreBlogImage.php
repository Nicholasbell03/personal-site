<?php

namespace App\Actions;

use App\Support\BlogImages;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class StoreBlogImage
{
    /**
     * Store an already-validated image under a ULID filename, matching the Filament upload.
     *
     * @return array{path: string, url: string}
     */
    public function execute(UploadedFile $file): array
    {
        $filename = Str::ulid().'.'.$file->guessExtension();

        $path = BlogImages::disk()->putFileAs(BlogImages::DIRECTORY, $file, $filename, ['visibility' => 'public']);

        if ($path === false) {
            Log::error('Failed to store blog image', [
                'disk' => config('filament.default_filesystem_disk'),
                'filename' => $filename,
                'original_name' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
            ]);

            throw new RuntimeException('Failed to store blog image.');
        }

        return [
            'path' => $path,
            'url' => BlogImages::url($path),
        ];
    }
}
