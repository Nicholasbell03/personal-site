<?php

namespace App\Filament\Resources\Shares\Pages;

use App\Filament\Resources\Shares\ShareResource;
use App\Services\OpenGraphService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;
class EditShare extends EditRecord
{
    protected static string $resource = ShareResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
            Action::make('fetchMetadata')
                ->icon(\Filament\Support\Icons\Heroicon::OutlinedArrowPath)
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
                        Log::error('Failed to fetch metadata: ' . $e->getMessage());
                    }
                }),
        ];
    }
}
