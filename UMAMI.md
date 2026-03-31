# Umami Setup

## Prerequisites

- Populate the `UMAMI_*` variables in `.env`.
- Set a dedicated `UMAMI_APP_SECRET` before using the stack outside local development.

## Start the stack

```bash
docker compose --profile umami up -d umami-db umami
```

The profile keeps Umami isolated from the Laravel app while still using the project compose file.

## First access

- Open `http://localhost:${UMAMI_PORT:-3001}`.
- Log in with the default local credentials from the Umami docs: `admin` / `umami`.
- Change the default password immediately after the first login.

## Dedicated PostgreSQL

- Umami uses the `umami-db` container only.
- Credentials come from `UMAMI_DB_NAME`, `UMAMI_DB_USER`, `UMAMI_DB_PASSWORD`, `UMAMI_DB_PORT`, and `UMAMI_DB_HOST`.
- The Laravel application database remains unchanged on the `db` service.

## Connect Soamco Budget

1. In Umami, create a website entry for the public Soamco Budget domain.
2. Copy the generated Website ID from the Umami website settings.
3. Set `UMAMI_WEBSITE_ID` in `.env`.
4. Set `UMAMI_ENABLED=true`.
5. Confirm `UMAMI_HOST_URL` points to your Umami instance and `UMAMI_DOMAINS` matches the tracked hostnames.

## App behavior

- The tracker script is rendered centrally from `resources/views/app.blade.php`.
- Pageviews are tracked manually on the allowed public Inertia routes only, so public SPA navigation does not generate duplicate pageviews.
- Custom CTA events are routed through the shared frontend helper in `resources/js/lib/analytics.ts`.
