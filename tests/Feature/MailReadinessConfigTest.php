<?php

test('mail defaults to mailtrap for local readiness', function () {
    $envExample = file_get_contents(base_path('.env.example'));

    expect($envExample)->toContain('MAIL_MAILER=mailtrap')
        ->toContain('TRANSACTIONAL_MAIL_PROVIDER=mailtrap');
});

test('mail config exposes ses and brevo mailers', function () {
    expect(config('mail.mailers.mailtrap.transport'))->toBe('smtp')
        ->and(config('mail.mailers.ses.transport'))->toBe('ses')
        ->and(config('mail.mailers.brevo.transport'))->toBe('smtp')
        ->and(config('mail.mailers.brevo.host'))->toBe('smtp-relay.brevo.com');
});

test('ses service config exposes readiness fields', function () {
    expect(config('services.ses.region'))->toBeString()
        ->and(config('services.ses'))->toHaveKeys([
            'key',
            'secret',
            'region',
            'token',
            'endpoint',
        ]);
});

test('transactional provider can be resolved by configuration only', function () {
    config()->set('mail.default', 'brevo');
    config()->set('mail.transactional_provider', 'brevo');

    expect(config('mail.default'))->toBe('brevo')
        ->and(config('mail.transactional_provider'))->toBe('brevo');
});
