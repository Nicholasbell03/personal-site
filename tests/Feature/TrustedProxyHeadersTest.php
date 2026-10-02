<?php

it('ignores a spoofed X-Forwarded-Host when building redirects', function () {
    $response = $this->get('/docs/api', ['X-Forwarded-Host' => 'evil.example']);

    $response->assertRedirect();
    expect($response->headers->get('Location'))->not->toContain('evil.example');
});

it('still honours X-Forwarded-Proto from the proxy', function () {
    $response = $this->get('/docs/api', ['X-Forwarded-Proto' => 'https']);

    expect($response->headers->get('Location'))->toStartWith('https://');
});
