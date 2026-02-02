<?php

namespace App\Filament\Resources\Projects\Tables;

use App\Enums\PublishStatus;
use App\Filament\Actions\PreviewAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ProjectsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('featured_image')
                    ->circular(),
                TextColumn::make('title')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                IconColumn::make('is_featured')
                    ->boolean()
                    ->label('Featured'),
                TextColumn::make('technologies.name')
                    ->badge()
                    ->limitList(3)
                    ->separator(', '),
                TextColumn::make('published_at')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('Not published'),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options(PublishStatus::class),
                TernaryFilter::make('is_featured')
                    ->label('Featured'),
            ])
            ->recordActions([
                PreviewAction::make()->previewPath('projects'),
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
