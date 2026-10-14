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

for (const failure of ['build', 'migrate', 'health', 'success']) {
    test(`deployment ${failure} keeps build before maintenance and recovers maintenance on failure`, () => {
        const root = mkdtempSync(join(tmpdir(), 'soamco-deploy-mock-'));
        const log = join(root, 'calls');

        try {
            const docker = join(root, 'docker');
            writeFileSync(
                docker,
                `#!/usr/bin/env bash
echo "$*" >> "$MOCK_LOG"
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

            if (failure === 'build') {
                assert.doesNotMatch(
                    calls,
                    /artisan down|force-recreate| stop /,
                );
            } else {
                assert.ok(
                    calls.indexOf('build app web') <
                        calls.indexOf('artisan down'),
                );
                assert.match(calls, /artisan up/);

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
