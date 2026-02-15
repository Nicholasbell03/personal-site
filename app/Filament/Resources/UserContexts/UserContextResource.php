<?php

namespace App\Filament\Resources\UserContexts;

use App\Enums\UserContextKey;
use App\Filament\Resources\UserContexts\Pages\CreateUserContext;
use App\Filament\Resources\UserContexts\Pages\EditUserContext;
use App\Filament\Resources\UserContexts\Pages\ListUserContexts;
use App\Models\UserContext;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UserContextResource extends Resource
{
    protected static ?string $model = UserContext::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleBottomCenterText;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('key')
                    ->options(UserContextKey::class)
                    ->required()
                    ->unique(ignoreRecord: true),
                Textarea::make('value')
                    ->required()
                    ->rows(15)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('key')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('value')
                    ->limit(80),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUserContexts::route('/'),
            'create' => CreateUserContext::route('/create'),
            'edit' => EditUserContext::route('/{record}/edit'),
        ];
    }
}
