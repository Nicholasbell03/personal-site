<?php

namespace App\Filament\Pages;

use App\Enums\TokenAbility;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Laravel\Sanctum\PersonalAccessToken;
use Livewire\Attributes\Locked;

class ApiTokens extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static ?string $navigationLabel = 'API Tokens';

    protected static ?string $title = 'API Tokens';

    protected static ?string $slug = 'api-tokens';

    protected static ?int $navigationSort = 99;

    protected string $view = 'filament.pages.api-tokens';

    #[Locked]
    public ?string $plainTextToken = null;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generate')
                ->label('Generate Token')
                ->icon(Heroicon::OutlinedPlusCircle)
                ->form([
                    TextInput::make('name')
                        ->label('Token Name')
                        ->placeholder('e.g. shares-extension')
                        ->required()
                        ->maxLength(255),
                    CheckboxList::make('abilities')
                        ->label('Abilities')
                        ->helperText('Grant only what this client needs.')
                        ->options(TokenAbility::options())
                        ->required(),
                    Select::make('expires_in_days')
                        ->label('Expires after')
                        ->options([30 => '30 days', 90 => '90 days', 365 => '1 year'])
                        ->default(90)
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $token = auth()->user()->createToken(
                        $data['name'],
                        $data['abilities'],
                        now()->addDays((int) $data['expires_in_days']),
                    );

                    $this->plainTextToken = $token->plainTextToken;
                }),
        ];
    }

    public function dismissToken(): void
    {
        $this->plainTextToken = null;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => PersonalAccessToken::query()->where('tokenable_id', auth()->id()))
            ->columns([
                TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('abilities')
                    ->label('Abilities')
                    ->badge()
                    ->getStateUsing(fn (PersonalAccessToken $record): string => implode(', ', $record->abilities))
                    ->color('gray'),
                TextColumn::make('expires_at')
                    ->label('Expires')
                    ->dateTime()
                    ->placeholder('Never')
                    ->color(fn (PersonalAccessToken $record): ?string => $record->expires_at?->isPast() ? 'danger' : null)
                    ->sortable(),
                TextColumn::make('last_used_at')
                    ->label('Last Used')
                    ->dateTime()
                    ->placeholder('Never')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                DeleteAction::make()
                    ->label('Revoke')
                    ->modalHeading('Revoke Token')
                    ->modalDescription(fn (PersonalAccessToken $record): string => "Are you sure you want to revoke the \"{$record->name}\" token? Any clients using it will lose access immediately.")
                    ->successNotificationTitle('Token revoked'),
            ]);
    }
}
