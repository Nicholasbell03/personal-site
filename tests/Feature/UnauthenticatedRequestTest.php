<?php

/**
 * There is no public login page (Filament handles its own login), so an unauthenticated request to a
 * protected API route must get a 401, whether or not it asks for JSON, never a redirect or a 500.
 */
it('returns 401 for unauthenticated api requests', function (string $method, string $uri, array $headers) {
    $this->call($method, $uri, server: $this->transformHeadersToServerVars($headers))
        ->assertUnauthorized();
})->with([
    'json write' => ['POST', '/api/v1/blogs', ['Accept' => 'application/json']],
    'write without accept header' => ['POST', '/api/v1/blogs', []],
    'browser-style read' => ['GET', '/api/user', ['Accept' => 'text/html']],
]);

it('still sends guests to the filament login for the admin panel', function () {
    $this->get('/admin')->assertRedirect(route('filament.admin.auth.login'));
});
