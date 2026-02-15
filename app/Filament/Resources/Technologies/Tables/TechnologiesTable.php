<?php

namespace App\Filament\Resources\Technologies\Tables;

use App\Models\Technology;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class TechnologiesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('slug')
                    ->searchable()
                    ->sortable(),
                ToggleColumn::make('is_featured')
                    ->label('Featured')
                    ->beforeStateUpdated(function ($record, $state) {
                        if ($state && ! $record->is_featured) {
                            $count = Technology::query()
                                ->where('is_featured', true)
                                ->where('id', '!=', $record->id)
                                ->count();

                            if ($count >= Technology::MAX_FEATURED) {
                                Notification::make()
                                    ->danger()
                                    ->title(Technology::maxFeaturedMessage())
                                    ->send();

                                return false;
                            }
                        }

                        return true;
                    }),
                TextColumn::make('projects_count')
                    ->counts('projects')
                    ->label('Projects')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name', 'asc')
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
