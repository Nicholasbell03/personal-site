<?php

namespace App\Filament\Resources\Shares\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ShareInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Content')
                    ->schema([
                        TextEntry::make('url')
                            ->url(fn ($record): string => $record->url)
                            ->openUrlInNewTab()
                            ->columnSpanFull(),
                        TextEntry::make('title')
                            ->placeholder('-'),
                        TextEntry::make('slug'),
                        TextEntry::make('source_type')
                            ->badge(),
                        TextEntry::make('site_name')
                            ->placeholder('-'),
                        TextEntry::make('author')
                            ->placeholder('-'),
                        TextEntry::make('description')
                            ->placeholder('-')
                            ->columnSpanFull(),
                        TextEntry::make('commentary')
                            ->html()
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
                Section::make('AI Summary & X')
                    ->schema([
                        TextEntry::make('summary')
                            ->placeholder('-')
                            ->columnSpanFull(),
                        TextEntry::make('x_post_id')
                            ->label('X Post')
                            ->placeholder('Not posted')
                            ->url(fn ($record): ?string => $record->x_post_id
                                ? "https://x.com/i/status/{$record->x_post_id}"
                                : null)
                            ->openUrlInNewTab(),
                    ])
                    ->columns(2),
                Section::make('Metadata')
                    ->schema([
                        TextEntry::make('created_at')
                            ->dateTime(),
                        TextEntry::make('updated_at')
                            ->dateTime(),
                    ])
                    ->columns(2),
            ]);
    }
}
