# Docker Compose

Soamco Budget uses a layered Docker Compose setup:

- `docker-compose.yml`: common service topology, healthchecks, networks, named volumes, and shared commands.
- `docker-compose.local.yml`: local development behavior. This preserves the previous local stack: source bind mounts, Node build watcher, local DB/Umami port publishing, local Caddy certificates, and the local Nginx config mount.
- `docker-compose.production.yml`: production-oriented override. It removes the local Node watcher and source bind mounts, keeps DB/Redis internal, and builds self-contained production images.

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

`docker/php/Dockerfile` has explicit `development` and `production` targets. The production target packages Laravel, optimized Composer dependencies installed with `--no-dev`, and `public/build`. A separate intermediate Node stage builds the frontend with PHP available for Wayfinder; Node and node_modules are not copied into the PHP runtime. The final default target remains development for existing direct local builds.

`docker/nginx/Dockerfile` receives `public` from the PHP production build through the Compose `app_assets` build context. Both images therefore contain the same manifest and assets, regardless of any old `public/build` on the host. `.dockerignore` excludes host dependencies, assets, runtime caches, uploaded files, environment files, and secrets.

Set production Laravel variables in an environment file outside the images. The default is `.env`; select another with `SOAMCO_ENV_FILE=/absolute/path/to/environment`. Compose injects this file into all PHP services. Set `APP_KEY`, database and Redis settings explicitly. If Compose-only variables such as `POSTGRES_*` or proxy settings live in the server-only `.env.docker`, pass it separately with `SOAMCO_COMPOSE_ENV_FILE`; it is used for interpolation, while `SOAMCO_ENV_FILE` remains the Laravel runtime environment. Pass browser-public `VITE_*` variables through the same Compose environment file before building; never use build arguments for secrets.

Use Compose 2.24.4 or newer (additional build contexts and `!reset` support):

```bash
SOAMCO_ENV_FILE=/absolute/path/to/.env \
SOAMCO_COMPOSE_ENV_FILE=/absolute/path/to/.env.docker \
bin/deploy-production
```

The deploy command:

1. Requires clean tracked files and pulls the current branch with `git pull --ff-only` (`--skip-pull` is available for an already prepared checkout).
2. Builds PHP, frontend, and web images before maintenance, with release tags by default. Prints previous running image IDs for recovery.
3. Verifies Artisan, vendor autoload and the build manifest inside the new image, then initializes persistent volume permissions.
4. Enters maintenance on the old app and on the shared Redis store, and gracefully stops Horizon, scheduler and Reverb. It never recreates DB/Redis.
5. Runs migrations with the new image while the old web app remains in maintenance.
6. Recreates app/web, then the workers, waits for healthchecks and runs `artisan up`. Runtime entrypoints cache config, routes and views using the injected production environment.

A build failure leaves the current services untouched. An error after maintenance triggers best-effort `artisan up` on the running app and the new image, and attempts to restart stopped workers. The script exits nonzero. It does not undo migrations or silently roll back incompatible code: inspect the failure and use the printed old image IDs only after checking schema compatibility. Previous images are preserved; no automatic pruning or volume deletion occurs.

PHP and web have real `/up` healthchecks. Horizon checks `horizon:status`, scheduler checks its foreground process, and Reverb checks its listener. Production maintenance uses Redis consistently across containers and survives recreation. `stop_grace_period: 90s` allows the configured 60-second jobs to finish.

Persistent volumes cover public storage, private storage (including local backups), and logs. Web mounts public storage read-only. DB volumes are unchanged; Redis is kept running by the deploy script. Existing Redis persistence policy is unchanged.

For the first deployment, start DB/Redis through your existing provisioned stack before invoking the script, and configure the production Caddy file with `SOAMCO_CADDYFILE`. The repository's default Caddy file is for local certificates. An existing server-only `deploy.sh`, `.env.docker`, or `Caddyfile.production` is not replaced by this script.

### Removing the emergency bind mount

Before the first immutable deployment, back up and transfer any host-only uploads, Firebase credentials and backups into the appropriate persistent volumes or external storage. If the server has an untracked `docker/nginx/Dockerfile`, back it up outside the checkout and move it aside before pulling the new tracked file; Git will otherwise refuse the update. Preserve the production Compose project name so existing volumes are reused.

Run the new deployment using only `docker-compose.yml` and `docker-compose.production.yml`, without `docker-compose.emergency.yml` or an inherited `COMPOSE_FILE` list. `bin/deploy-production` selects those two files explicitly. After success, inspect app/Horizon/scheduler/Reverb mounts and health, verify uploads and `/up`, then remove the emergency override from the server's deployment invocation. Keep its file as a recovery reference until validation is complete; never delete DB volumes or run `down -v`.

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
