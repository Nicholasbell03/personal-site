<?php

namespace App\Filament\Schemas;

use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Toggle;

class DownstreamPostingFields
{
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
            Placeholder::make('x_status')
                ->label('X/Twitter Status')
                ->content(fn ($record) => $record->x_post_id
                    ? 'Posted (ID: '.$record->x_post_id.')'
                    : 'Not posted')
                ->visible(fn ($record) => $record !== null),
            Placeholder::make('linkedin_status')
                ->label('LinkedIn Status')
                ->content(fn ($record) => $record->linkedin_post_id
                    ? 'Posted'
                    : 'Not posted')
                ->visible(fn ($record) => $record !== null),
        ];
    }
}
