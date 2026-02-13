<?php

namespace App\Filament\Resources\UserContexts\Pages;

use App\Filament\Resources\UserContexts\UserContextResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListUserContexts extends ListRecords
{
    protected static string $resource = UserContextResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
