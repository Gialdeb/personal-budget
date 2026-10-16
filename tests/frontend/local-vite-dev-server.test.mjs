import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { resolveConfig } from 'vite';

const read = (path) =>
    readFileSync(new URL('../../' + path, import.meta.url), 'utf8');

test('Docker development serves modules and secure HMR through the application proxy', async () => {
    const previous = process.env.SOAMCO_VITE_DOCKER;
    process.env.SOAMCO_VITE_DOCKER = '1';

    try {
        const config = await resolveConfig({}, 'serve');
        const appUrl = new URL(config.server.origin);
        assert.equal(config.base, '/vite/');
        assert.equal(config.server.host, '0.0.0.0');
        assert.equal(config.server.port, 5173);
        assert.equal(config.server.strictPort, true);
        assert.ok(config.server.allowedHosts.includes(appUrl.hostname));
        assert.notEqual(config.server.allowedHosts, true);
        assert.equal(config.server.hmr.host, appUrl.hostname);
        assert.equal(config.server.hmr.protocol, 'wss');
        assert.equal(config.server.hmr.clientPort, Number(appUrl.port) || 443);

        const production = await resolveConfig({}, 'build');
        assert.equal(production.base, '/build/');
        assert.equal(production.build.outDir, 'public/build');
        assert.equal(production.build.manifest, 'manifest.json');
        assert.equal(production.server.hmr, undefined);
    } finally {
        if (previous === undefined) {
            delete process.env.SOAMCO_VITE_DOCKER;
        } else {
            process.env.SOAMCO_VITE_DOCKER = previous;
        }
    }
});

test('local Docker shares the hot file and forwards Vite signals without a published Node port', () => {
    const local = read('docker-compose.local.yml');
    const node = local.split('  node:\n')[1].split('\n  db:')[0];
    assert.match(node, /init: true/);
    assert.match(node, /SOAMCO_VITE_DOCKER: '1'/);
    assert.match(node, /\.\/:\/var\/www\/html/);
    assert.match(
        node,
        /soamco_budget_node_modules:\/var\/www\/html\/node_modules/,
    );
    assert.match(
        node,
        /rm -f public\/hot && npm ci && exec node node_modules\/vite\/bin\/vite.js/,
    );
    assert.doesNotMatch(node, /ports:|--watch|max-old-space-size/);
    assert.match(read('docker/caddy/Caddyfile'), /@vite path \/vite\/\*/);
    assert.match(
        read('docker/caddy/Caddyfile'),
        /reverse_proxy @vite node:5173/,
    );
    assert.match(read('.dockerignore'), /^public\/hot$/m);
});
