# Transactional Mail Readiness

This project is prepared to send transactional email in a provider-agnostic way.

Current intent:
- local/dev uses Mailtrap
- staging/production can use Amazon SES or Brevo
- Zoho remains for human email, while SES/Brevo are intended for transactional delivery

## Default behavior

Local defaults in `.env.example`:
- `MAIL_MAILER=mailtrap`
- `TRANSACTIONAL_MAIL_PROVIDER=mailtrap`

No application mail or notification class needs to change when switching provider. The provider is selected through environment variables only.

## Supported mailers

Configured mailers in `config/mail.php`:
- `mailtrap`
- `ses`
- `brevo`
- `smtp` as a generic fallback

The active mailer is selected through:

```env
MAIL_MAILER=mailtrap
TRANSACTIONAL_MAIL_PROVIDER=mailtrap
```

## Local / Mailtrap

Required env:

```env
MAIL_MAILER=mailtrap
TRANSACTIONAL_MAIL_PROVIDER=mailtrap
MAIL_FROM_ADDRESS="noreply@soamco.com"
MAIL_FROM_NAME="${APP_NAME}"
MAIL_SENDER_DOMAIN=soamco.com

MAILTRAP_SCHEME=tls
MAILTRAP_HOST=sandbox.smtp.mailtrap.io
MAILTRAP_PORT=2525
MAILTRAP_USERNAME=...
MAILTRAP_PASSWORD=...
MAILTRAP_TIMEOUT=null
MAILTRAP_EHLO_DOMAIN=soamco.lo
```

This keeps local development isolated and prevents accidental delivery to real users.

## Amazon SES

Required env:

```env
MAIL_MAILER=ses
TRANSACTIONAL_MAIL_PROVIDER=ses
MAIL_FROM_ADDRESS="noreply@soamco.com"
MAIL_FROM_NAME="${APP_NAME}"
MAIL_SENDER_DOMAIN=soamco.com

AWS_ACCESS_KEY_ID=...
AWS_SECRET_ACCESS_KEY=...
AWS_DEFAULT_REGION=eu-west-1
AWS_SESSION_TOKEN=
AWS_SES_ENDPOINT=
```

Notes:
- `AWS_SES_ENDPOINT` is optional and useful for custom endpoints or special AWS setups
- `MAIL_FROM_ADDRESS` must use a verified sender identity or domain aligned with the SES setup

## Brevo

Brevo is prepared through SMTP relay for a simple and stable integration.

Required env:

```env
MAIL_MAILER=brevo
TRANSACTIONAL_MAIL_PROVIDER=brevo
MAIL_FROM_ADDRESS="noreply@soamco.com"
MAIL_FROM_NAME="${APP_NAME}"
MAIL_SENDER_DOMAIN=soamco.com

BREVO_SMTP_SCHEME=tls
BREVO_SMTP_HOST=smtp-relay.brevo.com
BREVO_SMTP_PORT=587
BREVO_SMTP_USERNAME=...
BREVO_SMTP_PASSWORD=...
BREVO_SMTP_TIMEOUT=null
BREVO_SMTP_EHLO_DOMAIN=soamco.com
```

## Domain and DNS readiness

Production must not depend on Mailtrap.

The domain `soamco.com` already exists. Before production sending is enabled with SES or Brevo, the chosen provider must be authenticated correctly for `soamco.com`.

Expected DNS work for the selected provider:
- SPF alignment
- DKIM records
- provider-specific return-path / tracking / bounce configuration if required

This task does **not** perform DNS setup. It only prepares the application to switch provider through configuration.

## Operational note

- Zoho stays the channel for human email workflows
- SES or Brevo are the intended providers for transactional application email
- switching provider should require env/config changes only, not application code changes
