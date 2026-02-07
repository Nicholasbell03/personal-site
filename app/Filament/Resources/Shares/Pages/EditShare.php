<?php

namespace App\Filament\Resources\Shares\Pages;

use App\Filament\Resources\Shares\ShareResource;
use App\Services\OpenGraphService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

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
                    app(OpenGraphService::class)->refreshMetadata($record);

                    $livewire->refreshFormData([
                        'title',
                        'description',
                        'image_url',
                        'site_name',
                        'source_type',
                        'embed_data',
                        'og_raw',
                    ]);
                }),
        ];
    }
}
