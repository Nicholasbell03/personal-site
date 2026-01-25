<?php

namespace App\Filament\Resources\Projects\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ProjectInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Content')
                    ->schema([
                        TextEntry::make('title'),
                        TextEntry::make('slug'),
                        TextEntry::make('description')
                            ->placeholder('-')
                            ->columnSpanFull(),
                        TextEntry::make('long_description')
                            ->html()
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
                Section::make('Media')
                    ->schema([
                        ImageEntry::make('featured_image')
                            ->placeholder('No image'),
                    ])
                    ->collapsible(),
                Section::make('Links')
                    ->schema([
                        TextEntry::make('project_url')
                            ->label('Project URL')
                            ->url(fn ($state) => $state)
                            ->placeholder('-'),
                        TextEntry::make('github_url')
                            ->label('GitHub URL')
                            ->url(fn ($state) => $state)
                            ->placeholder('-'),
                    ])
                    ->columns(2)
                    ->collapsible(),
                Section::make('Technologies')
                    ->schema([
                        TextEntry::make('technologies.name')
                            ->badge()
                            ->placeholder('No technologies'),
                    ])
                    ->collapsible(),
                Section::make('Publishing')
                    ->schema([
                        TextEntry::make('status')
                            ->badge(),
                        IconEntry::make('is_featured')
                            ->boolean()
                            ->label('Featured'),
                        TextEntry::make('published_at')
                            ->dateTime()
                            ->placeholder('Not published'),
                        TextEntry::make('created_at')
                            ->dateTime(),
                        TextEntry::make('updated_at')
                            ->dateTime(),
                    ])
                    ->columns(2),
            ]);
    }
}
