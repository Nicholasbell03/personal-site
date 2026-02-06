<?php

namespace App\Filament\Resources\Shares\Schemas;

use App\Enums\SourceType;
use App\Services\OpenGraphService;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class ShareForm
{
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
                            ->columnSpanFull()
                            ->suffixAction(
                                \Filament\Actions\Action::make('fetchMetadata')
                                    ->icon(\Filament\Support\Icons\Heroicon::OutlinedArrowPath)
                                    ->action(function (Set $set, ?string $state, $record) {
                                        if (! $state) {
                                            return;
                                        }

                                        $service = app(OpenGraphService::class);
                                        $data = $service->fetch($state);

                                        if ($data['title']) {
                                            $set('title', $data['title']);
                                            if (! $record) {
                                                $set('slug', Str::slug($data['title']));
                                            }
                                        }
                                        if ($data['description']) {
                                            $set('description', $data['description']);
                                        }
                                        if ($data['image']) {
                                            $set('image_url', $data['image']);
                                        }
                                        if ($data['site_name']) {
                                            $set('site_name', $data['site_name']);
                                        }

                                        $set('source_type', $data['source_type']->value);
                                        $set('embed_data', $data['embed_data']);
                                        $set('og_raw', $data['og_raw']);
                                    })
                            ),
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
                        RichEditor::make('commentary')
                            ->nullable()
                            ->extraInputAttributes(['style' => 'min-height: 300px;'])
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->columnSpan(4),
            ]);
    }
}
