<?php

it('keeps guest redirects on https behind the trusted proxy', function () {
    $response = $this->get('/settings/accounts', [
        'X-Forwarded-For' => '127.0.0.1',
        'X-Forwarded-Host' => 'soamco.lo',
        'X-Forwarded-Port' => '443',
        'X-Forwarded-Proto' => 'https',
    ]);

    $response->assertRedirect('https://soamco.lo/login');
});
