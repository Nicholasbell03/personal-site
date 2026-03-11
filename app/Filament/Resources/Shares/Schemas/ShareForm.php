<?php

namespace App\Filament\Resources\Shares\Schemas;

use App\Enums\SourceType;
use App\Filament\Schemas\DownstreamPostingFields;
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

class ShareForm
{
    private static function postToXToggle(): Toggle
    {
        return Toggle::make('post_to_x')
            ->label('Post to X/Twitter')
            ->default(true);
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(4)
            ->components([
                Section::make('Content')
                    ->schema([
                        TextInput::make('url')
                            ->required()
                            ->url()
                            ->maxLength(2048)
                            ->columnSpanFull(),
                        Select::make('source_type')
                            ->options(SourceType::class)
                            ->default(SourceType::Webpage)
                            ->required(),
                        TextInput::make('title')
                            ->nullable()
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (Set $set, ?string $state, $record) {
                                if (! $record && $state) {
                                    $set('slug', Str::slug($state));
                                }
                            }),
                        TextInput::make('slug')
                            ->required()
                            ->readOnly(fn ($record) => $record !== null)
                            ->unique(ignoreRecord: true),
                        Textarea::make('description')
                            ->nullable()
                            ->rows(3)
                            ->columnSpanFull(),
                        TextInput::make('image_url')
                            ->nullable()
                            ->url()
                            ->columnSpanFull(),
                        TextInput::make('site_name')
                            ->nullable(),
                        TextInput::make('author')
                            ->nullable(),
                        RichEditor::make('commentary')
                            ->nullable()
                            ->extraInputAttributes(['style' => 'min-height: 300px;'])
                            ->columnSpanFull(),
                        Textarea::make('summary')
                            ->nullable()
                            ->maxLength(280)
                            ->rows(3)
                            ->helperText('AI-generated summary for card display and tweets. Leave blank to auto-generate.')
                            ->columnSpanFull(),
                        self::postToXToggle()
                            ->visible(fn ($record) => $record === null),
                    ])
                    ->columns(2)
                    ->columnSpan(fn ($record) => $record !== null ? 3 : 4),
                Grid::make(1)
                    ->columnSpan(1)
                    ->visible(fn ($record) => $record !== null)
                    ->schema([
                        Section::make('Integration Status')
                            ->schema([
                                DownstreamPostingFields::embeddingStatusPlaceholder()
                                    ->label('Embedding'),
                                DownstreamPostingFields::xStatusPlaceholder()
                                    ->label('X/Twitter'),
                                self::postToXToggle(),
                            ]),
                    ]),
            ]);
    }
}
