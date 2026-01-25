<?php

namespace App\Filament\Resources\Projects\Schemas;

use App\Enums\PublishStatus;
use App\Models\Technology;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class ProjectForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(4)
            ->components([
                Section::make('Content')
                    ->schema([
                        TextInput::make('title')
                            ->required()
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (Set $set, ?string $state, $record) {
                                if (! $record) {
                                    $set('slug', Str::slug($state ?? ''));
                                }
                            }),
                        TextInput::make('slug')
                            ->required()
                            ->readOnly(fn ($record) => ! $record)
                            ->unique(ignoreRecord: true),
                        FileUpload::make('featured_image')
                            ->image()
                            ->directory('project-images')
                            ->visibility('public')
                            ->columnSpanFull(),
                        Textarea::make('description')
                            ->rows(3)
                            ->helperText('Short description for listings')
                            ->columnSpanFull(),
                        RichEditor::make('long_description')
                            ->extraInputAttributes(['style' => 'min-height: 400px;'])
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->columnSpan(3),
                Grid::make(1)
                    ->schema([
                        Section::make('Publishing')
                            ->schema([
                                Select::make('status')
                                    ->options(PublishStatus::class)
                                    ->default(PublishStatus::Draft)
                                    ->required(),
                                Toggle::make('is_featured')
                                    ->label('Featured Project')
                                    ->helperText('Show on homepage'),
                            ]),
                        Section::make('Links')
                            ->schema([
                                TextInput::make('project_url')
                                    ->label('Project URL')
                                    ->url()
                                    ->placeholder('https://example.com'),
                                TextInput::make('github_url')
                                    ->label('GitHub URL')
                                    ->url()
                                    ->placeholder('https://github.com/...'),
                            ]),
                        Section::make('Technologies')
                            ->schema([
                                Select::make('technologies')
                                    ->relationship('technologies', 'name')
                                    ->multiple()
                                    ->preload()
                                    ->createOptionForm([
                                        TextInput::make('name')
                                            ->required()
                                            ->unique(Technology::class, 'name'),
                                    ]),
                            ]),
                    ])
                    ->columnSpan(1),
            ]);
    }
}
