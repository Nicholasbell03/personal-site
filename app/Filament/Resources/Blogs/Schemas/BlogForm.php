<?php

namespace App\Filament\Resources\Blogs\Schemas;

use App\Enums\PublishStatus;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class BlogForm
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
                            ->readOnly(fn ($record) => $record !== null)
                            ->unique(ignoreRecord: true),
                        FileUpload::make('featured_image')
                            ->image()
                            ->directory('blog-images')
                            ->visibility('public')
                            ->columnSpanFull(),
                        RichEditor::make('content')
                            ->required()
                            ->extraInputAttributes(['style' => 'min-height: 400px;'])
                            ->columnSpanFull(),
                        Textarea::make('excerpt')
                            ->rows(3)
                            ->helperText('Leave blank to generate from content')
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
                            ]),
                        Section::make('SEO')
                            ->schema([
                                Textarea::make('meta_description')
                                    ->rows(3)
                                    ->maxLength(160)
                                    ->helperText('Recommended: 150-160 characters. Leave blank to generate from content.'),
                            ]),
                    ])
                    ->columnSpan(1),
            ]);
    }
}
