import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import {
    readFileSync,
    mkdtempSync,
    writeFileSync,
    chmodSync,
    rmSync,
} from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import test from 'node:test';
const read = (path) =>
    readFileSync(new URL('../../' + path, import.meta.url), 'utf8');
test('production images package Laravel and share internally built assets while local targets remain development', () => {
    const php = read('docker/php/Dockerfile');
    const nginx = read('docker/nginx/Dockerfile');
    const prod = read('docker-compose.production.yml');
    assert.match(php, /composer install --no-dev/);
    assert.match(php, /FROM dependencies AS frontend/);
    assert.match(php, /npm ci --include=dev && npm run build/);
    assert.match(php, /FROM runtime AS production/);
    assert.match(php, /ENTRYPOINT \["production-entrypoint"\]/);
    assert.match(
        read('docker/php/production-entrypoint'),
        /set -eu[\s\S]*php artisan config:cache --no-interaction\nphp artisan event:cache --no-interaction\nphp artisan route:cache --no-interaction\nphp artisan view:cache --no-interaction\nexec docker-php-entrypoint "\$@"/,
    );
    assert.match(php, /COPY --from=dependencies --chown=www-data:www-data/);
    assert.match(php, /COPY --from=frontend/);
    assert.match(php, /zip gd exif/);
    assert.match(nginx, /FROM app_assets AS assets/);
    assert.match(nginx, /COPY --from=assets \/var\/www\/html\/public/);
    assert.match(prod, /app_assets: service:app/);
    assert.match(prod, /APP_MAINTENANCE_DRIVER: cache/);
    assert.doesNotMatch(prod, /\.\/:\/var\/www\/html|--watch/);
    assert.match(read('docker-compose.local.yml'), /target: development/);
    const ignore = read('.dockerignore');
    assert.match(ignore, /^\.env$/m);
    assert.match(ignore, /^vendor$/m);
    assert.match(ignore, /^public\/build$/m);
});

for (const failure of [
    'build',
    'credentials',
    'migrate',
    'health',
    'success',
]) {
    test(`deployment ${failure} keeps build before maintenance and recovers maintenance on failure`, () => {
        const root = mkdtempSync(join(tmpdir(), 'soamco-deploy-mock-'));
        const log = join(root, 'calls');

        try {
            const docker = join(root, 'docker');
            writeFileSync(
                docker,
                `#!/usr/bin/env bash
echo "$*" >> "$MOCK_LOG"
if [[ "$*" == *"exec -T proxy test -s"* ]]; then exit 1; fi
if [[ "$*" == *"exec -T app application-healthcheck"* && "$MOCK_FAIL" == health ]]; then exit 1; fi
if [[ "$*" == *"firebase-credentials-check.php"* && "$MOCK_FAIL" == credentials ]]; then exit 10; fi
if [[ "$*" == *"build app web"* && "$MOCK_FAIL" == build ]]; then exit 7; fi
if [[ "$*" == *"migrate --force"* && "$MOCK_FAIL" == migrate ]]; then exit 8; fi
if [[ "$*" == *"force-recreate"* && "$MOCK_FAIL" == health ]]; then exit 9; fi
if [[ "$*" == *"ps --status running -q app"* ]]; then echo mock-app; fi
`,
            );
            chmodSync(docker, 0o755);
            const result = spawnSync(
                'bash',
                ['bin/deploy-production', '--skip-pull'],
                {
                    cwd: new URL('../..', import.meta.url),
                    env: {
                        ...process.env,
                        PATH: root + ':' + process.env.PATH,
                        SOAMCO_ENV_FILE: '/tmp/laravel-runtime.env',
                        SOAMCO_COMPOSE_ENV_FILE: '/tmp/compose-only.env',
                        MOCK_LOG: log,
                        MOCK_FAIL: failure,
                    },
                    encoding: 'utf8',
                },
            );
            const calls = readFileSync(log, 'utf8');
            assert.equal(
                result.status,
                failure === 'success'
                    ? 0
                    : failure === 'build'
                      ? 7
                      : failure === 'credentials'
                        ? 10
                        : failure === 'health'
                          ? 9
                          : 8,
                result.stderr,
            );
            assert.match(
                calls,
                /--env-file \/tmp\/laravel-runtime\.env --env-file \/tmp\/compose-only\.env/,
            );
            assert.doesNotMatch(calls, /down -v|volume rm|image prune/);

            if (['build', 'credentials'].includes(failure)) {
                assert.doesNotMatch(
                    calls,
                    /artisan down|force-recreate| stop /,
                );
            } else {
                assert.ok(
                    calls.indexOf('build app web') <
                        calls.indexOf('artisan down'),
                );

                if (failure === 'health') {
                    assert.doesNotMatch(
                        calls,
                        /artisan up|caddy reload --config \/config\/soamco-deploy-normal.json/,
                    );
                } else {
                    assert.match(calls, /artisan up/);
                    assert.ok(
                        calls.indexOf('artisan up') <
                            calls.indexOf(
                                'caddy reload --config /config/soamco-deploy-normal.json',
                            ),
                    );
                }

                if (failure === 'migrate') {
                    assert.doesNotMatch(calls, /force-recreate/);
                } else {
                    assert.match(
                        calls,
                        /--no-deps --no-build --force-recreate --wait/,
                    );
                }
            }
        } finally {
            rmSync(root, { recursive: true, force: true });
        }
    });
}

test('production maintenance stays outside app/web recreation and preserves the live proxy configuration', () => {
    const script = read('bin/deploy-production');
    assert.ok(
        script.indexOf('compose build app web') <
            script.indexOf('wait-timeout 60 maintenance'),
    );
    assert.ok(
        script.indexOf(
            'caddy reload --config /config/soamco-deploy-maintenance.json',
        ) < script.indexOf('artisan down'),
    );
    assert.ok(
        script.lastIndexOf('artisan up') < script.lastIndexOf('restore_proxy'),
    );
    assert.match(script, /application-healthcheck && compose exec -T web wget/);
    assert.doesNotMatch(
        script,
        /force-recreate[^\n]*proxy|force-recreate[^\n]*maintenance/,
    );
    assert.match(
        read('docker-compose.production.yml'),
        /command: \['php', '-S', '0.0.0.0:8000', 'docker\/php\/maintenance-router.php'\]/,
    );
    assert.match(
        read('docker-compose.production.yml'),
        /proxy:[\s\S]*restart: false/,
    );
    assert.match(
        read('docker/php/maintenance-router.php'),
        /view\('errors.503'\)->render\(\)/,
    );
});

test('frontend build receives all seven public Firebase values without private credentials', () => {
    const dockerfile = read('docker/php/Dockerfile');
    const compose = read('docker-compose.production.yml');
    const frontend = dockerfile
        .split('FROM dependencies AS frontend')[1]
        .split('FROM runtime AS production')[0];
    const args = compose.split('      args:')[1].split('    depends_on:')[0];

    for (const suffix of [
        'API_KEY',
        'AUTH_DOMAIN',
        'PROJECT_ID',
        'STORAGE_BUCKET',
        'MESSAGING_SENDER_ID',
        'APP_ID',
        'VAPID_PUBLIC_KEY',
    ]) {
        const key = `VITE_FIREBASE_${suffix}`;
        assert.ok(frontend.includes(`ARG ${key}\n`));
        assert.ok(args.includes(`${key}: \${${key}:-}`));
    }

    assert.doesNotMatch(
        frontend + args,
        /FIREBASE_CREDENTIALS|GOOGLE_APPLICATION_CREDENTIALS|PRIVATE_KEY/,
    );
    assert.match(read('.dockerignore'), /^\.env$/m);
});

test('Firebase credentials remain runtime-only and are checked as www-data before maintenance', () => {
    const compose = read('docker-compose.production.yml');
    assert.match(
        compose,
        /soamco_budget_private_storage:\/var\/www\/html\/storage\/app\/private/,
    );
    assert.match(compose, /volumes:[\s\S]*\n  soamco_budget_private_storage:/);
    const deploy = read('bin/deploy-production');
    const preflight =
        'compose run --rm --no-deps --user www-data --entrypoint php app docker/php/firebase-credentials-check.php';
    assert.ok(deploy.includes(preflight));
    assert.ok(
        deploy.indexOf('compose run --rm --no-deps logs-init') <
            deploy.indexOf(preflight),
    );
    assert.ok(
        deploy.indexOf(preflight) <
            deploy.indexOf('wait-timeout 60 maintenance'),
    );
    assert.ok(
        deploy.indexOf(preflight) < deploy.indexOf('php artisan migrate'),
    );
    assert.doesNotMatch(deploy, /(?:cp|install|mv) [^\n]*\.json[^\n]*private/);
    const dockerfile = read('docker/php/Dockerfile');
    assert.match(
        dockerfile,
        /test -f docker\/php\/firebase-credentials-check.php/,
    );
    assert.doesNotMatch(
        dockerfile,
        /(?:COPY|ADD|ARG|ENV) [^\n]*(?:FIREBASE_CREDENTIALS|GOOGLE_APPLICATION_CREDENTIALS|private_key|adminsdk)/,
    );
    const ignore = read('.dockerignore');

    for (const entry of [
        'storage',
        '**/*firebase-adminsdk*.json',
        '**/*service-account*.json',
        '**/*service_account*.json',
        '**/firebase-credentials.json',
    ]) {
        assert.ok(ignore.split('\n').includes(entry));
    }
});
