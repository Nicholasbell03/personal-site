<?php

namespace App\Filament\Resources\Blogs\Tables;

use App\Enums\PublishStatus;
use App\Filament\Actions\PreviewAction;
use App\Filament\Actions\RegenerateEmbeddingAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class BlogsTable
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
                ToggleColumn::make('status')
                    ->label('Published')
                    ->getStateUsing(fn ($record): bool => $record->status === PublishStatus::Published)
                    ->updateStateUsing(function ($record, bool $state): void {
                        $record->update([
                            'status' => $state ? PublishStatus::Published : PublishStatus::Draft,
                        ]);
                    })
                    ->sortable(),
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
            ])
            ->recordActions([
                PreviewAction::make()->previewPath('blog'),
                RegenerateEmbeddingAction::make(),
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([]);
    }
}
