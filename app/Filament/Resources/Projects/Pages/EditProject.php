<?php

namespace App\Filament\Resources\Projects\Pages;

use App\Filament\Actions\PostToLinkedInAction;
use App\Filament\Actions\PostToXAction;
use App\Filament\Actions\PreviewAction;
use App\Filament\Actions\RegenerateEmbeddingAction;
use App\Filament\Resources\Projects\ProjectResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditProject extends EditRecord
{
    protected static string $resource = ProjectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            PreviewAction::make()->previewPath('projects'),
            ViewAction::make(),
            DeleteAction::make(),
            RegenerateEmbeddingAction::make(),
            PostToXAction::make(),
            PostToLinkedInAction::make(),
        ];
    }
}
