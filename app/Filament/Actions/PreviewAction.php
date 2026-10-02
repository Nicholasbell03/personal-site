<?php

namespace App\Filament\Actions;

use App\Http\Middleware\ValidatePreviewToken;
use App\Models\Blog;
use App\Models\Project;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;

class PreviewAction extends Action
{
    protected string $previewPath = '';

    public static function getDefaultName(): string
    {
        return 'preview';
    }

    public function previewPath(string $path): static
    {
        $this->previewPath = $path;

        return $this;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->icon(Heroicon::OutlinedEye)
            ->url(fn (Blog|Project $record): string => sprintf(
                '%s/%s/%s?%s',
                config('app.frontend_url'),
                $this->previewPath,
                $record->slug,
                http_build_query(['token' => ValidatePreviewToken::issue($record->getTable(), $record->slug)])
            ))
            ->hidden(fn (): bool => ! config('app.preview_token'))
            ->openUrlInNewTab();
    }
}
