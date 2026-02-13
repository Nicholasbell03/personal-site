<?php

namespace App\Filament\Resources\UserContexts\Pages;

use App\Filament\Resources\UserContexts\UserContextResource;
use Filament\Resources\Pages\EditRecord;

class EditUserContext extends EditRecord
{
    protected static string $resource = UserContextResource::class;
}
