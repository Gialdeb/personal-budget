# Docker Compose

Soamco Budget uses a layered Docker Compose setup:

- `docker-compose.yml`: common service topology, healthchecks, networks, named volumes, and shared commands.
- `docker-compose.local.yml`: local development behavior. This preserves the previous local stack: source bind mounts, Node build watcher, local DB/Umami port publishing, local Caddy certificates, and the local Nginx config mount.
- `docker-compose.production.yml`: production-oriented override. It removes the local Node watcher and source bind mounts, keeps DB/Redis internal, and requires explicit production images.

## Local

Use the base file plus the local override:

```bash
bin/local-up
```

`bin/local-up` is a small wrapper around:

```bash
docker compose -f docker-compose.yml -f docker-compose.local.yml up -d
```

Use the wrapper for local development so the local override is always included. Running only `docker compose up -d` uses the base topology without the local bind mounts, local ports, and Node watcher.

Optional Umami profile:

```bash
docker compose -f docker-compose.yml -f docker-compose.local.yml --profile umami up -d umami-db umami
```

Local keeps using the project `.env` file by default. A separate `.env.local` is not required for the current workflow.

Local behavior intentionally preserved:

- `./:/var/www/html` bind mount on PHP, Nginx, Horizon, Scheduler, Reverb, and Node.
- `node` service runs `npm ci && npm run build -- --watch`.
- PostgreSQL data is stored in the named Docker volume `soamco_budget_postgres_data`, mounted at `/var/lib/postgresql/data`.
- PostgreSQL is published on `${DB_FORWARD_PORT:-5434}:5432`.
- Umami keeps the `umami` profile and publishes `${UMAMI_DB_PORT:-5433}:5432` and `${UMAMI_PORT:-3001}:3000` when enabled.
- Caddy publishes `${SOAMCO_HTTP_PORT:-80}:80` and `${SOAMCO_HTTPS_PORT:-443}:443` and mounts the local mkcert certificates from `docker/certs`. The defaults preserve the previous `80:80` and `443:443` behavior.
- Existing healthchecks, dependencies, network, service names, and worker commands are preserved.

## Production

Use the base file plus the production override:

```bash
SOAMCO_PHP_IMAGE=registry.example.com/soamco-budget/php:latest \
SOAMCO_WEB_IMAGE=registry.example.com/soamco-budget/web:latest \
SOAMCO_CADDYFILE=/absolute/path/to/Caddyfile \
POSTGRES_DB=soamco_budget \
POSTGRES_USER=soamco_budget \
POSTGRES_PASSWORD=change-me \
docker compose -f docker-compose.yml -f docker-compose.production.yml up -d
```

Production requires images that already contain what the current local bind mount provides:

- `SOAMCO_PHP_IMAGE`: application code, Composer dependencies, Laravel runtime files, and built assets required by PHP-side commands.
- `SOAMCO_WEB_IMAGE`: public assets and web server configuration required by Nginx.

This repository does not yet define a full immutable production image pipeline in the Dockerfiles, so the production override does not invent one. Build and publish those images in the deployment pipeline before running the production compose command.

If you prefer env files, keep `.env` for Laravel runtime configuration and pass Compose deployment variables explicitly with `--env-file`:

```bash
docker compose --env-file .env.production -f docker-compose.yml -f docker-compose.production.yml config
```

Do not duplicate all Laravel variables into `.env.production` unless the deployment target needs it.

## Validation

Check the merged local config:

```bash
docker compose -f docker-compose.yml -f docker-compose.local.yml config
docker compose -f docker-compose.yml -f docker-compose.local.yml --profile umami config
```

Check the production config without starting containers:

```bash
SOAMCO_PHP_IMAGE=registry.example.com/soamco-budget/php:latest \
SOAMCO_WEB_IMAGE=registry.example.com/soamco-budget/web:latest \
SOAMCO_CADDYFILE=./docker/caddy/Caddyfile \
POSTGRES_DB=soamco_budget \
POSTGRES_USER=soamco \
POSTGRES_PASSWORD=secret \
docker compose -f docker-compose.yml -f docker-compose.production.yml config
```

After starting local, verify the main services:

```bash
docker compose -f docker-compose.yml -f docker-compose.local.yml ps
```

`app`, `db`, `redis`, and `reverb` should report healthy. `horizon`, `scheduler`, `web`, `node`, and `proxy` should be running. If `proxy` cannot start because port `80` is already in use on the host, stop the host process occupying that port or temporarily run with `SOAMCO_HTTP_PORT=8080` while keeping the default mapping unchanged for normal local use.

## Local Database Safety

Do not run these commands against a local database that contains real development data unless you have a current backup and explicitly intend to destroy data:

```bash
php artisan migrate:fresh
php artisan migrate:refresh
php artisan migrate:reset
php artisan db:wipe
php artisan schema:dump --prune
docker compose down -v
docker-compose down -v
docker volume rm soamco_budget_postgres_data
docker system prune --volumes
docker compose up --renew-anon-volumes
```

For day-to-day local Artisan usage, prefer:

```bash
bin/safe-artisan migrate
```

`bin/safe-artisan` blocks the destructive Artisan commands listed above unless `ALLOW_DESTRUCTIVE_DB_COMMANDS=true` is set. It is a local guardrail; direct `php artisan ...` still bypasses it.

Create a local database backup before risky work:

```bash
bin/backup-local-db
```

Backups are written to:

```text
storage/backups/local/
```

The backup script fails if `soamco-budget-db` is not running and never overwrites an existing dump.

Tests are configured in `phpunit.xml` to use SQLite in memory:

```xml
<env name="DB_CONNECTION" value="sqlite"/>
<env name="DB_DATABASE" value=":memory:"/>
```

This keeps `RefreshDatabase` away from the local PostgreSQL development database.
