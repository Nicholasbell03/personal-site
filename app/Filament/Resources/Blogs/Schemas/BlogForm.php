<?php

namespace App\Filament\Resources\Blogs\Schemas;

use App\Enums\BlogStatus;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
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
                            ->unique(ignoreRecord: true),
                        FileUpload::make('featured_image')
                            ->image()
                            ->directory('blog-images')
                            ->visibility('public')
                            ->columnSpanFull(),
                        RichEditor::make('content')
                            ->required()
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
                                    ->options(BlogStatus::class)
                                    ->default(BlogStatus::Draft)
                                    ->required(),
                            ]),
                        Section::make('SEO')
                            ->schema([
                                TextInput::make('meta_description')
                                    ->maxLength(160)
                                    ->helperText('Recommended: 150-160 characters'),
                            ]),
                    ])
                    ->columnSpan(1),
            ]);
    }
}
