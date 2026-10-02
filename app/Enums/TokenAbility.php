<?php

namespace App\Enums;

/**
 * Abilities a Sanctum API token can be scoped to. Each write route requires exactly one.
 */
enum TokenAbility: string
{
    case SharesCreate = 'shares:create';
    case BlogsWrite = 'blogs:write';
    case ProjectsWrite = 'projects:write';
    case MediaUpload = 'media:upload';

    public function label(): string
    {
        return match ($this) {
            self::SharesCreate => 'Create shares (extension / PWA)',
            self::BlogsWrite => 'Create & edit blog drafts',
            self::ProjectsWrite => 'Create projects',
            self::MediaUpload => 'Upload images',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $ability) => [$ability->value => $ability->label()])->all();
    }

    /**
     * Route middleware requiring this ability on the authenticated token.
     */
    public function middleware(): string
    {
        return 'abilities:'.$this->value;
    }
}
