<?php

namespace App\Filament\Resources\Shares\Pages;

use App\Filament\Actions\RegenerateEmbeddingAction;
use App\Filament\Resources\Shares\ShareResource;
use App\Jobs\PostToXJob;
use App\Services\OpenGraphService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Log;

class EditShare extends EditRecord
{
    protected static string $resource = ShareResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
            RegenerateEmbeddingAction::make(),
            Action::make('postToX')
                ->icon(Heroicon::OutlinedShare)
                ->color('info')
                ->label('Post to X/Twitter')
                ->visible(fn ($record): bool => $record->x_post_id === null)
                ->requiresConfirmation()
                ->modalHeading('Post to X/Twitter')
                ->modalDescription('This will dispatch a job to post this share to X/Twitter. Continue?')
                ->action(function ($record): void {
                    try {
                        PostToXJob::dispatch($record);

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

                        Log::error('EditShare postToX: failed to dispatch job', [
                            'share_id' => $record->getKey(),
                            'exception' => $e->getMessage(),
                            'trace' => $e->getTraceAsString(),
                        ]);
                    }
                }),
            Action::make('fetchMetadata')
                ->icon(Heroicon::OutlinedArrowPath)
                ->action(function ($record, $livewire) {
                    try {
                        app(OpenGraphService::class)->refreshMetadata($record);

                        $livewire->refreshFormData(OpenGraphService::METADATA_FIELDS);
                        Notification::make()
                            ->title('Metadata fetched successfully')
                            ->success()
                            ->send();
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Failed to fetch metadata')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                        Log::error('Failed to fetch metadata: '.$e->getMessage());
                    }
                }),
        ];
    }
}
