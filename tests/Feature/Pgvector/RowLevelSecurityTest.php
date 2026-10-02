<?php

use Illuminate\Support\Facades\DB;

it('enables row level security on every public table', function () {
    $unprotected = DB::table('pg_tables')
        ->where('schemaname', 'public')
        ->where('rowsecurity', false)
        ->pluck('tablename');

    expect($unprotected)->toBeEmpty();
});

it('still lets the application role read and write those tables', function () {
    DB::table('user_contexts')->insert(['key' => 'rls_probe', 'value' => 'ok', 'created_at' => now(), 'updated_at' => now()]);

    expect(DB::table('user_contexts')->where('key', 'rls_probe')->value('value'))->toBe('ok');
});
