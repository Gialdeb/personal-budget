import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import {
    copyFileSync,
    existsSync,
    mkdirSync,
    mkdtempSync,
    readFileSync,
    rmSync,
    symlinkSync,
    writeFileSync,
} from 'node:fs';
import { tmpdir } from 'node:os';
import { dirname, join, resolve } from 'node:path';
import test from 'node:test';
import { fileURLToPath } from 'node:url';

const project = resolve(dirname(fileURLToPath(import.meta.url)), '..');
function fixture(t) {
    const directory = mkdtempSync(join(tmpdir(), 'soamco-pre-push-'));
    t.after(() => rmSync(directory, { recursive: true, force: true }));
    const env = { ...process.env, GIT_CONFIG_NOSYSTEM: '1' };

    for (const key of Object.keys(env)) {
        if (key.startsWith('GIT_') && key !== 'GIT_CONFIG_NOSYSTEM') {
            delete env[key];
        }
    }

    delete env.CI;
    delete env.HUSKY;
    delete env.NODE_ENV;
    env.XDG_CONFIG_HOME = join(directory, 'config');
    env.HOOK_CALLS_FILE = join(directory, 'hook-calls');
    function run(command, args, overrides = {}) {
        return spawnSync(command, args, {
            cwd: directory,
            env: { ...env, ...overrides },
            input: '',
            encoding: 'utf8',
            timeout: 60000,
        });
    }
    function git(...args) {
        const result = run('git', args);
        assert.equal(result.status, 0, result.stdout + result.stderr);

        return result.stdout;
    }
    git('init', '--quiet');
    mkdirSync(join(directory, '.husky'));

    for (const file of [
        'package.json',
        '.husky/pre-push',
        '.husky/install.mjs',
    ]) {
        copyFileSync(join(project, file), join(directory, file));
    }

    symlinkSync(join(project, 'node_modules'), join(directory, 'node_modules'));
    const installed = run('npm', ['run', 'prepare']);
    assert.equal(installed.status, 0, installed.stdout + installed.stderr);
    assert.equal(git('config', '--get', 'core.hooksPath').trim(), '.husky/_');
    mkdirSync(join(directory, 'bin'));

    for (const command of ['composer', 'npm', 'php', 'npx']) {
        writeFileSync(
            join(directory, 'bin', command),
            '#!/bin/sh\nprintf "%s\\n" "$0 $*" >> "$HOOK_CALLS_FILE"\nexit "${HOOK_COMPOSER_EXIT:-99}"\n',
            { mode: 0o755 },
        );
    }

    env.PATH = `${join(directory, 'bin')}:${env.PATH}`;

    return { directory, git, run };
}

test('installed pre-push succeeds without running quality checks or tests', (t) => {
    const { directory, git, run } = fixture(t);
    const indexBefore = git('diff', '--cached', '--binary');
    const result = run('git', [
        'hook',
        'run',
        'pre-push',
        '--',
        'origin',
        'unused-local-test',
    ]);
    assert.equal(result.status, 0, result.stdout + result.stderr);
    assert.ok(!existsSync(join(directory, 'hook-calls')));
    assert.equal(git('diff', '--cached', '--binary'), indexBefore);
    assert.equal(git('rev-list', '--all', '--count').trim(), '0');
    assert.match(
        readFileSync(join(project, '.github/workflows/tests.yml'), 'utf8'),
        /run: composer ci:check/,
    );
});

for (const status of [1, 2, 127]) {
    test(`pre-push does not invoke the removed quality gate (exit ${status})`, (t) => {
        const { directory, run } = fixture(t);
        const result = run('git', ['hook', 'run', 'pre-push'], {
            HOOK_COMPOSER_EXIT: String(status),
        });
        assert.equal(result.status, 0, result.stdout + result.stderr);
        assert.ok(!existsSync(join(directory, 'hook-calls')));
    });
}

test('pre-push leaves unformatted files and the index unchanged', (t) => {
    const { directory, git, run } = fixture(t);
    const file = 'Dashboard.vue';
    const original =
        '<template><div class="grid"> unformatted </div></template>\n';
    writeFileSync(join(directory, file), original);
    git('add', '--', file);
    const indexBefore = git('diff', '--cached', '--binary');
    const result = run('git', ['hook', 'run', 'pre-push']);
    assert.equal(result.status, 0, result.stdout + result.stderr);
    assert.ok(!existsSync(join(directory, 'hook-calls')));
    assert.equal(readFileSync(join(directory, file), 'utf8'), original);
    assert.equal(git('diff', '--cached', '--binary'), indexBefore);
    assert.equal(git('rev-list', '--all', '--count').trim(), '0');
});
