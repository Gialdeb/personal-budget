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
4. Starts a temporary maintenance responder from the PHP production image before downtime. Caddy atomically switches only its `web:80` handlers to this responder, which renders the unchanged `resources/views/errors/503.blade.php` (including language selection and Inertia reloads). Proxy TLS, host routing and Reverb routes are preserved; the server-only Caddyfile is never rewritten. Enters maintenance on the old app and on the shared Redis store, and gracefully stops Horizon, scheduler and Reverb. It never recreates DB/Redis.
5. Runs migrations with the new image while the old web app remains in maintenance.
6. Recreates app/web, then the workers, waits for healthchecks and runs `artisan up`. Runtime entrypoints cache config, events, routes and views using the injected production environment. These caches are regenerated in each new PHP container before its main process starts; a cache failure stops startup. After deployment, verify all four report `CACHED` with `php artisan about --only=cache` inside the running app container.

After healthchecks and `artisan up`, the live Caddy configuration is restored and the temporary responder is stopped. Caddy is not restarted when web is recreated. The responder remains independent of app/web throughout recreate and healthchecks; no additional image or Node runtime is required. The proxy admin API must be available at its internal default `127.0.0.1:2019`; deployment checks this before downtime.

A build failure leaves the current services untouched. An error after maintenance reopens routing only if app/web pass their healthchecks and `artisan up` succeeds; otherwise Caddy continues serving the Soamco view. A subsequent deploy reuses the running responder and the saved normal proxy configuration, rather than recreating the maintenance responder during the failed deployment window. The script exits nonzero. It does not undo migrations or silently roll back incompatible code: inspect the failure and use the printed old image IDs only after checking schema compatibility. Previous images are preserved; no automatic pruning or volume deletion occurs.

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

## Explicit production deployment modes

`bin/deploy-production` keeps `local` as its default. Select a mode explicitly with
`bin/deploy-production local`, `bin/deploy-production ghcr`, or
`bin/deploy-production ssh` (`--mode MODE` is equivalent). No mode falls back to
another. `--skip-pull` remains available for an already prepared checkout.

- `local`: the existing VPS build/resource preflight and deployment sequence.
  Both builds receive the full checkout commit as an image revision label.
- `ghcr`: pulls only `ghcr.io/OWNER/REPOSITORY/{php,web}:sha-FULL_COMMIT` for the
  actual checkout HEAD. First authenticate Docker on the VPS using private-package
  read access; the script never logs in, requests a write token or prints credentials.
- `ssh`: uses only `soamco-budget-{php,web}:sha-FULL_COMMIT`, already loaded by the
  manual SSH workflow. It never pulls or loads missing images during deployment.

For both prepared modes, set `SOAMCO_IMAGE_REPOSITORY=owner/repository` in the
wrapper environment. Use the manual workflow on **main** if deploying main: a
merge commit has a different SHA from its source release branch and needs its own
image preparation. Workflow files must also be available on the default branch
before manual dispatch. Neither workflow starts a deployment.

Configure public `VITE_*` repository variables to match the production Compose
build arguments. Prepared images carry a hash of these public inputs: mismatches
stop deployment before maintenance, including mismatches caused by changed
repository variables. Never place application secrets in `VITE_*` variables.

Prepared modes require Python 3, a clean tracked checkout, linux/amd64 Docker and
at least 8 GiB/50000 free inodes in Docker storage. They verify the commit,
repository, architecture, frontend configuration and image IDs, then pin all PHP
services and web to those IDs. A temporary Compose override removes build
instructions and sets `pull_policy: never`; no automatic rebuild/pull is possible.
Production environment and existing Firebase credentials are checked before
maintenance. DB/Redis volumes and the migration/health-check/recovery sequence
remain unchanged. Existing recovery preserves previous images and reopens only
healthy services; it does **not** automatically undo database migrations.

### External VPS wrapper — proposed manual update

Review `docker/deploy.sh.example` and apply it manually to the external VPS
`deploy.sh` **only after the integrated script is available on main**. Do not
execute the example during review. Keep a copy of the existing external wrapper.
The proposed wrapper retains `/var/www/soamco-budget`, `.env.docker` and the
`soamco` Git identity, but delegates the main pull to the script inside its existing
deployment/backup lock. It forwards the explicit mode, for example `deploy.sh ghcr`
or `deploy.sh ssh`; no arguments still selects the existing local-build workflow.
Supply `SOAMCO_IMAGE_REPOSITORY` through the external wrapper environment before
selecting a prepared mode. The script refuses a checkout on a different branch
instead of merging main into it accidentally. No VPS file is updated by the
repository changes or by the image-preparation workflows.

## GitHub selection with no VPS command arguments (opt-in)

Until activation, `./deploy.sh` continues to use the existing local-build mode.
The new `Select deployment mode for main` workflow is manual only: select `main`
and choose `ghcr`, `ssh` or explicit `local`. It neither prepares images nor starts
a deployment. For GHCR/SSH, first run the existing preparation workflow on the
same exact main commit. The selector requires a completed successful preparation
run **and successful final verification steps**, not a skipped job or the most
recent run on another commit/branch. Images are checked again by the existing VPS
preflight; missing/changed images still stop before maintenance.

The selector creates an annotated Git tag `soamco-deploy-FULL_SHA`, pointing
exactly to that main commit, with the mode and preparation run/attempt recorded in
its JSON message. This is Git metadata, not a new source commit. Creation is
atomic; the workflow never updates or overwrites an existing choice. One commit
has one choice. A failed prerequisite creates no choice; after preparation, retry
the selector. Changing an already registered choice requires an explicit reviewed
administrative decision outside this workflow; no automatic replacement exists.

### Manual activation — do not apply during implementation

After review, make the integrated scripts and workflow available on main/default
branch, update the external wrapper from `docker/deploy.sh.example`, then enable
these two exports in its server-only environment:

```bash
export SOAMCO_DEPLOY_SELECTION=github
export SOAMCO_IMAGE_REPOSITORY=owner/repository
```

Keep them disabled until ready. No VPS file is edited by this change. With
activation, the operator still executes only `./deploy.sh`: it pulls main under
the existing lock, fetches **only the selection tag for the resulting HEAD**, and
validates tag type, target commit, repository, schema, mode and preparation proof.
It uses the existing Git read access; no Actions API token is required on the VPS.
An unavailable origin, absent tag, malformed/foreign choice, wrong branch or
missing preparation stops the deployment **before maintenance**. Cached tags are
not a fallback. Explicit CLI modes are rejected while GitHub control is enabled;
choose `local` explicitly in the workflow instead. An invalid activation value
also stops rather than silently disabling the control.

A new main commit always needs its own choice; an earlier commit's choice or a
later workflow run never applies to it. Activation does not grant permission for
an automatic deployment. The actual deployment remains a separate manual VPS
command. Disabling the flag restores the legacy behavior by explicit operator
configuration; it is never done automatically on failure.


## Deployment preflight corrections

The pull phase runs in an immutable in-memory Bash bootstrap, not in the script
Git is replacing. After a successful pull it executes the updated
`bin/deploy-production --inherit-lock --skip-pull`, preserving the original
arguments and the same locked descriptor 9. The updated script verifies its
identity before reusing it; it never closes/reopens the lock or unlinks it. An
unsupported lock-protocol version stops before deployment. When first updating
the external VPS wrapper, verify that the local checkout already contains this
protocol before applying the proposed wrapper; availability on remote main alone
is not sufficient for that initial installation. No VPS wrapper is modified here.

All modes, including legacy local with `--skip-pull`, now require committed
tracked inputs and no untracked source files, both before preparation and again
before maintenance. Existing server-only files explicitly excluded by
`.dockerignore` (`.env.*` except `.env.example`, `deploy.sh`, the emergency override
and production Caddyfile) remain allowed. Nothing is stashed, committed or deleted
automatically. A clean checkout keeps the existing mode and deployment behavior.

Before maintenance, the selected app image runs
`docker/php/postgres-connection-check.php` as `www-data`. It loads Laravel database
configuration without booting application providers, resolves DB_URL and the
writer configuration, creates a fresh nonpersistent connection, and executes
`SELECT 1`. Connection and session statement timeouts are limited to five seconds.
Only the probe session's timeout is changed; no data, migration, role or database
configuration is written. Failures expose a generic diagnostic without PDO/DSN
or password details and stop every mode before maintenance. Tests mock PDO and
Docker; no real production/local database connection is made during verification.
