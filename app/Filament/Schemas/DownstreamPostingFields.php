<?php

namespace App\Filament\Schemas;

use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Toggle;

class DownstreamPostingFields
{
    public static function embeddingStatusPlaceholder(): Placeholder
    {
        return Placeholder::make('embedding_status')
            ->label('Embedding Status')
            ->content(fn ($record) => $record->embedding_generated_at
                ? 'Generated at '.$record->embedding_generated_at->format('M j, Y g:i A')
                : 'Not generated');
    }

    public static function xStatusPlaceholder(): Placeholder
    {
        return Placeholder::make('x_status')
            ->label('X/Twitter Status')
            ->content(fn ($record) => $record->x_post_id
                ? 'Posted (ID: '.$record->x_post_id.')'
                : 'Not posted');
    }

    /**
     * @return list<Toggle|Placeholder>
     */
    public static function make(): array
    {
        return [
            Toggle::make('post_to_x')
                ->label('Post to X/Twitter')
                ->default(true)
                ->helperText('Only posts on first publish.'),
            Toggle::make('post_to_linkedin')
                ->label('Post to LinkedIn')
                ->default(true)
                ->helperText('Only posts on first publish.'),
            self::embeddingStatusPlaceholder()
                ->visible(fn ($record) => $record !== null),
            self::xStatusPlaceholder()
                ->visible(fn ($record) => $record !== null),
            Placeholder::make('linkedin_status')
                ->label('LinkedIn Status')
                ->content(fn ($record) => $record->linkedin_post_id
                    ? 'Posted (ID: '.$record->linkedin_post_id.')'
                    : 'Not posted')
                ->visible(fn ($record) => $record !== null),
        ];
    }
}
