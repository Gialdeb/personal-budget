<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('support.mail.title') }}</title>
</head>
<body style="font-family: Arial, sans-serif; color: #0f172a; line-height: 1.6;">
    <h1 style="font-size: 20px; margin-bottom: 16px;">{{ __('support.mail.heading') }}</h1>

    <p><strong>{{ __('support.mail.category') }}</strong> {{ __('support.categories.'.$supportRequest->category.'.label') }}</p>
    <p><strong>{{ __('support.mail.subject') }}</strong> {{ $supportRequest->subject }}</p>
    <p><strong>{{ __('support.mail.user') }}</strong> {{ $supportRequest->user?->name ?? __('support.mail.unknown_user') }}</p>
    <p><strong>{{ __('support.mail.email') }}</strong> {{ $supportRequest->user?->email ?? __('support.mail.no_email') }}</p>
    <p><strong>{{ __('support.mail.locale') }}</strong> {{ $supportRequest->locale }}</p>
    <p><strong>{{ __('support.mail.source_route') }}</strong> {{ $supportRequest->source_route ?? 'n/a' }}</p>
    <p><strong>{{ __('support.mail.source_url') }}</strong> {{ $supportRequest->source_url ?? 'n/a' }}</p>

    <p><strong>{{ __('support.mail.app_version') }}</strong> {{ $supportRequest->app_version ?? __('support.mail.legacy_version') }}</p>
    <p><a href="{{ route('admin.support-requests.show', $supportRequest->uuid) }}">{{ __('support.mail.open_ticket') }}</a></p>
    <hr style="margin: 24px 0; border: 0; border-top: 1px solid #e2e8f0;">

    <h2 style="font-size: 16px; margin-bottom: 12px;">{{ __('support.mail.message') }}</h2>
    <div style="white-space: pre-wrap;">{{ $activityMessage ?? $supportRequest->message }}</div>
</body>
</html>
