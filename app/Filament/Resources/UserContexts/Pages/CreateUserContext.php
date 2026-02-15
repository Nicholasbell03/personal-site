<?php

namespace App\Filament\Resources\UserContexts\Pages;

use App\Filament\Resources\UserContexts\UserContextResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUserContext extends CreateRecord
{
    protected static string $resource = UserContextResource::class;
}
