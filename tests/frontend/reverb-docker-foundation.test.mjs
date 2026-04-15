import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const composeSource = readFileSync(
    new URL('../../docker-compose.yml', import.meta.url),
    'utf8',
);

const caddySource = readFileSync(
    new URL('../../docker/caddy/Caddyfile', import.meta.url),
    'utf8',
);

const envExampleSource = readFileSync(
    new URL('../../.env.example', import.meta.url),
    'utf8',
);

const docsSource = readFileSync(
    new URL('../../docs/REVERB_FOUNDATION.md', import.meta.url),
    'utf8',
);

test('docker compose defines a dedicated reverb service on the app runtime image', () => {
    assert.match(composeSource, /^ {2}reverb:\n/m);
    assert.match(
        composeSource,
        /image: \$\{SOAMCO_PHP_IMAGE:-soamco-budget-php\}/,
    );
    assert.match(composeSource, /container_name: soamco-budget-reverb/);
    assert.match(composeSource, /command: php artisan reverb:start/);
    assert.match(composeSource, /restart: unless-stopped/);
    assert.match(composeSource, /expose:\n\s+- ['"]8080['"]/);
});

test('caddy proxies websocket traffic to the docker reverb service', () => {
    assert.match(caddySource, /@reverb path \/app\* \/apps\*/);
    assert.match(caddySource, /reverse_proxy @reverb reverb:8080/);
    assert.match(caddySource, /reverse_proxy web:80/);
});

test('env example separates internal docker reverb networking from browser websocket settings', () => {
    assert.match(envExampleSource, /REVERB_SERVER_HOST=0\.0\.0\.0/);
    assert.match(envExampleSource, /REVERB_SERVER_PORT=8080/);
    assert.match(envExampleSource, /REVERB_HOST=reverb/);
    assert.match(envExampleSource, /REVERB_PORT=8080/);
    assert.match(envExampleSource, /REVERB_SCHEME=http/);
    assert.match(envExampleSource, /VITE_REVERB_HOST=soamco\.lo/);
    assert.match(envExampleSource, /VITE_REVERB_PORT=443/);
    assert.match(envExampleSource, /VITE_REVERB_SCHEME=https/);
    assert.doesNotMatch(envExampleSource, /REVERB_ALLOWED_ORIGINS=\*/);
});

test('reverb operational docs explain docker startup and host or port distinction', () => {
    assert.match(docsSource, /docker compose up -d reverb/);
    assert.match(docsSource, /REVERB_SERVER_HOST/);
    assert.match(docsSource, /REVERB_HOST/);
    assert.match(docsSource, /VITE_REVERB_HOST/);
    assert.match(docsSource, /docker compose logs -f reverb/);
});
