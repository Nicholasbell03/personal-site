<?php

namespace App\Filament\Resources\Shares\Tables;

use App\Enums\SourceType;
use App\Services\OpenGraphService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SharesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->limit(40),
                TextColumn::make('source_type')
                    ->badge()
                    ->sortable(),
                TextColumn::make('site_name')
                    ->placeholder('-'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('source_type')
                    ->options(SourceType::class),
            ])
            ->recordActions([
                Action::make('fetchMetadata')
                    ->icon(\Filament\Support\Icons\Heroicon::OutlinedArrowPath)
                    ->action(fn ($record) => app(OpenGraphService::class)->refreshMetadata($record)),
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([]);
    }
}
