<?php

/**
 * Enforces "Architecture: Controllers Are HTTP-Only" in CLAUDE.md. Reads and caching belong in
 * app/Services, writes in app/Actions.
 */
arch('controllers do not touch the database, cache or storage directly')
    ->expect('App\Http\Controllers')
    ->not->toUse([
        'Illuminate\Support\Facades\Cache',
        'Illuminate\Support\Facades\DB',
        'Illuminate\Support\Facades\Storage',
    ]);

arch('actions expose an execute method')
    ->expect('App\Actions')
    ->toHaveMethod('execute');
