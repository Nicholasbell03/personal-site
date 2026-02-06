<?php

namespace App\Filament\Resources\Shares\Pages;

use App\Filament\Resources\Shares\ShareResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListShares extends ListRecords
{
    protected static string $resource = ShareResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
