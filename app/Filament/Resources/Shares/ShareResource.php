<?php

namespace App\Filament\Resources\Shares;

use App\Filament\Resources\Shares\Pages\CreateShare;
use App\Filament\Resources\Shares\Pages\EditShare;
use App\Filament\Resources\Shares\Pages\ListShares;
use App\Filament\Resources\Shares\Pages\ViewShare;
use App\Filament\Resources\Shares\Schemas\ShareForm;
use App\Filament\Resources\Shares\Schemas\ShareInfolist;
use App\Filament\Resources\Shares\Tables\SharesTable;
use App\Models\Share;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ShareResource extends Resource
{
    protected static ?string $model = Share::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShare;

    public static function form(Schema $schema): Schema
    {
        return ShareForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ShareInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SharesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListShares::route('/'),
            'create' => CreateShare::route('/create'),
            'view' => ViewShare::route('/{record}'),
            'edit' => EditShare::route('/{record}/edit'),
        ];
    }
}
