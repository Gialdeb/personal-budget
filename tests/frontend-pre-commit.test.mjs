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
    const directory = mkdtempSync(join(tmpdir(), 'soamco-pre-commit-'));
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

    function run(command, args) {
        return spawnSync(command, args, {
            cwd: directory,
            env,
            encoding: 'utf8',
            timeout: 60000,
        });
    }

    function git(...args) {
        const result = run('git', args);
        assert.equal(result.status, 0, result.stdout + result.stderr);

        return result.stdout.trim();
    }

    git('clone', '--quiet', '--shared', project, directory);
    git('config', 'user.name', 'Hook Test');
    git('config', 'user.email', 'hook-test@example.invalid');
    git('config', 'commit.gpgsign', 'false');
    symlinkSync(join(project, 'node_modules'), join(directory, 'node_modules'));
    mkdirSync(join(directory, '.husky'), { recursive: true });

    for (const file of [
        'package.json',
        '.husky/pre-commit',
        '.husky/install.mjs',
    ]) {
        copyFileSync(join(project, file), join(directory, file));
    }

    const install = run('npm', ['run', 'prepare']);
    assert.equal(install.status, 0, install.stdout + install.stderr);
    assert.equal(git('config', '--get', 'core.hooksPath'), '.husky/_');

    // Always stop Git after pre-commit, even if Husky is disabled externally.
    writeFileSync(
        join(directory, '.husky/_/prepare-commit-msg'),
        '#!/bin/sh\necho reached > .hook-verified\nexit 1\n',
        { mode: 0o755 },
    );
    const head = git('rev-parse', 'HEAD');

    function write(file, content) {
        mkdirSync(dirname(join(directory, file)), { recursive: true });
        writeFileSync(join(directory, file), content);
    }

    function commit() {
        const result = run('git', ['commit', '-m', 'Hook verification']);
        assert.equal(result.status, 1, result.stdout + result.stderr);
        assert.equal(git('rev-parse', 'HEAD'), head);

        return result;
    }

    return { directory, run, git, write, commit };
}

test('git commit formats and stages only matching frontend files', (t) => {
    const { directory, run, git, write, commit } = fixture(t);
    const frontend = 'resources/js/hook test.ts';
    const unformatted = 'export const hook={value:"staged"}\n';
    const ignored = 'resources/js/components/ui/hook-ignored.ts';
    write(frontend, unformatted);
    write(ignored, unformatted);
    write('hook-outside.mjs', unformatted);
    write('resources/js/hook-unstaged.ts', unformatted);
    write('resources/hook-data.json', '{"value":"staged"}\n');
    git(
        'add',
        '--',
        frontend,
        ignored,
        'hook-outside.mjs',
        'resources/hook-data.json',
    );
    const result = commit();
    assert.match(result.stdout + result.stderr, /prettier --write/);
    assert.ok(existsSync(join(directory, '.hook-verified')));
    const formatted = "export const hook = { value: 'staged' };\n";
    assert.equal(git('show', `:${frontend}`), formatted.trim());
    assert.equal(readFileSync(join(directory, frontend), 'utf8'), formatted);

    for (const file of [ignored, 'resources/js/hook-unstaged.ts']) {
        assert.equal(readFileSync(join(directory, file), 'utf8'), unformatted);
    }

    assert.equal(git('show', ':hook-outside.mjs'), formatted.trim());
    assert.equal(
        readFileSync(join(directory, 'hook-outside.mjs'), 'utf8'),
        formatted,
    );
    assert.match(result.stdout + result.stderr, /eslint --fix/);
    assert.equal(
        git('show', ':resources/hook-data.json'),
        '{ "value": "staged" }',
    );

    rmSync(join(directory, 'resources/js/hook-unstaged.ts'));
    const check = run('npm', ['run', 'format:check']);
    assert.equal(check.status, 0, check.stdout + check.stderr);
});

test('partially staged frontend files retain their unstaged changes', (t) => {
    const { directory, git, write, commit } = fixture(t);
    const file = 'tests/frontend/session-isolation.test.mjs';
    const original = readFileSync(join(directory, file), 'utf8');
    write(file, `export const hook={value:"staged"}\n${original}`);
    git('add', '--', file);
    write(
        file,
        readFileSync(join(directory, file), 'utf8') +
            '\n// unstaged hook sentinel\n',
    );
    commit();
    assert.ok(existsSync(join(directory, '.hook-verified')));
    assert.match(
        git('show', `:${file}`),
        /export const hook = \{ value: 'staged' \};/,
    );
    assert.doesNotMatch(git('show', `:${file}`), /unstaged hook sentinel/);
    assert.match(
        readFileSync(join(directory, file), 'utf8'),
        /unstaged hook sentinel/,
    );
});

test('syntax errors stop git commit and restore the original index', (t) => {
    const { directory, git, write, commit } = fixture(t);
    const file = 'resources/js/hook-invalid.ts';
    const invalid = 'export const broken = {\n';
    write(file, invalid);
    git('add', '--', file);
    const result = commit();
    assert.match(result.stdout + result.stderr, /eslint --fix/);
    assert.ok(!existsSync(join(directory, '.hook-verified')));
    assert.equal(git('show', `:${file}`), invalid.trim());
    assert.equal(readFileSync(join(directory, file), 'utf8'), invalid);
});

test('Prettier failures on non-script resources stop the commit', (t) => {
    const { directory, git, write, commit } = fixture(t);
    const file = 'resources/css/hook-invalid.css';
    const invalid = 'a {\n';
    write(file, invalid);
    git('add', '--', file);
    const result = commit();
    assert.match(result.stdout + result.stderr, /prettier --write/);
    assert.ok(!existsSync(join(directory, '.hook-verified')));
    assert.equal(git('show', `:${file}`), invalid.trim());
    assert.equal(readFileSync(join(directory, file), 'utf8'), invalid);
});

test('commits without matching frontend files do not run frontend tools', (t) => {
    const { directory, git, write, commit } = fixture(t);
    write('hook-outside.txt', 'untouched text\n');
    git('add', '--', 'hook-outside.txt');
    const result = commit();
    assert.ok(existsSync(join(directory, '.hook-verified')));
    assert.doesNotMatch(result.stdout + result.stderr, /prettier --write/);
    assert.doesNotMatch(result.stdout + result.stderr, /eslint/);
    assert.equal(git('show', ':hook-outside.txt'), 'untouched text');
});

test('staged frontend tests and root config scripts receive ESLint and Prettier fixes', (t) => {
    const { directory, run, git, write, commit } = fixture(t);
    const file = 'tests/frontend/hook-padding.test.mjs';
    const config = 'hook-probe.config.mjs';
    write(
        file,
        'export function hook(){const value=1;throw new Error(String(value));}\n',
    );
    write(
        config,
        'import {join} from "node:path";\nimport assert from "node:assert/strict";\nexport const hook={join,assert}\n',
    );
    git('add', '--', file, config);
    const result = commit();
    assert.ok(existsSync(join(directory, '.hook-verified')));
    assert.match(result.stdout + result.stderr, /eslint --fix/);
    assert.match(result.stdout + result.stderr, /prettier --write/);
    assert.match(git('show', `:${file}`), /const value = 1;\n\n {4}throw/);
    assert.match(
        git('show', `:${config}`),
        /^import assert from 'node:assert\/strict';/,
    );

    for (const [command, args] of [
        ['npm', ['exec', '--no', '--', 'eslint', file, config]],
        ['npm', ['exec', '--no', '--', 'prettier', '--check', file, config]],
    ]) {
        const check = run(command, args);
        assert.equal(check.status, 0, check.stdout + check.stderr);
    }
});

test('nonfixable ESLint errors stop the commit and roll back fixes to staged files', (t) => {
    const { directory, git, write, commit } = fixture(t);
    const invalid = 'tests/frontend/hook-unused.test.mjs';
    const valid = 'resources/js/hook-rollback.ts';
    const unused = 'const unusedHookValue=1\n';
    const unformatted = 'export const hook={value:"staged"}\n';
    write(invalid, unused);
    write(valid, unformatted);
    git('add', '--', invalid, valid);
    const result = commit();
    assert.match(result.stdout + result.stderr, /no-unused-vars/);
    assert.ok(!existsSync(join(directory, '.hook-verified')));

    for (const [file, content] of [
        [invalid, unused],
        [valid, unformatted],
    ]) {
        assert.equal(git('show', `:${file}`), content.trim());
        assert.equal(readFileSync(join(directory, file), 'utf8'), content);
    }
});

test('hook installation safely skips CI and production without dev dependencies', (t) => {
    const directory = mkdtempSync(join(tmpdir(), 'soamco-hook-install-'));
    t.after(() => rmSync(directory, { recursive: true, force: true }));
    const script = join(directory, 'install.mjs');
    copyFileSync(join(project, '.husky/install.mjs'), script);

    for (const overrides of [
        { CI: 'true' },
        { NODE_ENV: 'production' },
        { HUSKY: '0' },
    ]) {
        const env = { ...process.env };
        delete env.CI;
        delete env.NODE_ENV;
        delete env.HUSKY;
        const result = spawnSync(process.execPath, [script], {
            cwd: directory,
            env: { ...env, ...overrides },
            encoding: 'utf8',
        });
        assert.equal(result.status, 0, result.stdout + result.stderr);
        assert.ok(!existsSync(join(directory, '.husky')));
    }
});
