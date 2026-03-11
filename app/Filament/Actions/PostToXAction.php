<?php

namespace App\Filament\Actions;

use App\Jobs\PostContentToXJob;
use App\Models\Blog;
use App\Models\Project;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Log;

class PostToXAction extends Action
{
    public static function getDefaultName(): string
    {
        return 'postToX';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->icon(Heroicon::OutlinedShare)
            ->color('info')
            ->label('Post to X/Twitter')
            ->visible(fn ($record): bool => ($record instanceof Blog || $record instanceof Project)
                && $record->x_post_id === null)
            ->requiresConfirmation()
            ->modalHeading('Post to X/Twitter')
            ->modalDescription('This will dispatch a job to post this content to X/Twitter. Continue?')
            ->action(function ($record): void {
                try {
                    PostContentToXJob::dispatch($record);

                    Notification::make()
                        ->title('X/Twitter post job dispatched')
                        ->success()
                        ->send();
                } catch (\Throwable $e) {
                    Notification::make()
                        ->title('Failed to dispatch X/Twitter post job')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();

                    Log::error('PostToXAction: failed to dispatch job', [
                        'model' => $record::class,
                        'id' => $record->getKey(),
                        'exception' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                }
            });
    }
}
