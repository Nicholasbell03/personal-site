<?php

namespace App\Filament\Actions;

use App\Jobs\PostToLinkedInJob;
use App\Models\Blog;
use App\Models\Project;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Log;

class PostToLinkedInAction extends Action
{
    public static function getDefaultName(): string
    {
        return 'postToLinkedIn';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->icon(Heroicon::OutlinedShare)
            ->color('info')
            ->label('Post to LinkedIn')
            ->visible(fn ($record): bool => ($record instanceof Blog || $record instanceof Project)
                && $record->linkedin_post_id === null)
            ->requiresConfirmation()
            ->modalHeading('Post to LinkedIn')
            ->modalDescription('This will dispatch a job to post this content to LinkedIn. Continue?')
            ->action(function ($record): void {
                try {
                    PostToLinkedInJob::dispatch($record);

                    Notification::make()
                        ->title('LinkedIn post job dispatched')
                        ->success()
                        ->send();
                } catch (\Throwable $e) {
                    Notification::make()
                        ->title('Failed to dispatch LinkedIn post job')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();

                    Log::error('PostToLinkedInAction: failed to dispatch job', [
                        'model' => $record::class,
                        'id' => $record->getKey(),
                        'exception' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                }
            });
    }
}
