import assert from 'node:assert/strict';
import { execFileSync, spawnSync } from 'node:child_process';
import {
    chmodSync,
    copyFileSync,
    mkdirSync,
    mkdtempSync,
    readFileSync,
    rmSync,
    writeFileSync,
} from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import test from 'node:test';
import { imagePlan } from '../../bin/production-images-ci.mjs';

const rootUrl = new URL('../..', import.meta.url);
const read = (path) => readFileSync(new URL(path, rootUrl), 'utf8');
const sha = 'a'.repeat(40);
const phpId = 'sha256:' + 'b'.repeat(64);
const webId = 'sha256:' + 'c'.repeat(64);
const plan = imagePlan({
    eventName: 'workflow_dispatch',
    ref: 'refs/heads/main',
    sha,
    repo: { owner: 'example', repo: 'soamco-budget' },
    payload: { repository: { fork: false } },
});
const python = execFileSync('which', ['python3'], { encoding: 'utf8' }).trim();

function fixture(callback) {
    const root = mkdtempSync(join(tmpdir(), 'soamco-deploy-modes-'));
    mkdirSync(join(root, 'bin'));
    mkdirSync(join(root, 'docker-config'));
    writeFileSync(join(root, '.dockerignore'), read('.dockerignore'));
    const source = read('bin/deploy-production')
        .replaceAll(
            '/tmp/soamco-production-deploy.lock',
            join(root, 'deploy.lock'),
        )
        .replace(
            '    check_resources\n',
            '    : # no Linux resource probe on the test host\n',
        );
    writeFileSync(join(root, 'bin/deploy-production'), source);
    copyFileSync(
        new URL('bin/deployment-images.py', rootUrl),
        join(root, 'bin/deployment-images.py'),
    );
    copyFileSync(
        new URL('bin/deployment-choice.py', rootUrl),
        join(root, 'bin/deployment-choice.py'),
    );
    writeFileSync(
        join(root, 'docker-config/config.json'),
        JSON.stringify({
            auths: { 'ghcr.io': { auth: 'SYNTHETIC-AUTH-DO-NOT-LOG' } },
        }),
    );
    writeFileSync(
        join(root, 'args.json'),
        JSON.stringify(plan.bake.target.php.args),
    );
    writeFileSync(
        join(root, 'mock'),
        `#!${python}
import json, os, pathlib, sys
name=pathlib.Path(sys.argv[0]).name
args=sys.argv[1:]
root=pathlib.Path(os.environ['MOCK_ROOT'])
failure=os.environ.get('MOCK_FAILURE','')
with (root/'calls').open('a') as output: output.write(name+' '+' '.join(args)+'\\n')
if name=='flock':
    import fcntl
    if failure=='lock': sys.exit(75)
    try: fcntl.flock(9, fcntl.LOCK_EX | fcntl.LOCK_NB)
    except (OSError, BlockingIOError): sys.exit(75)
    sys.exit(0)
if name=='stat':
    try:
        info=os.fstat(9) if args[-1].startswith('/proc/') else os.stat(args[-1])
        print(str(info.st_dev)+':'+str(info.st_ino))
    except OSError: sys.exit(1)
    sys.exit(0)
if name=='sudo': os.execv(str(root/'git'), ['git', *args[3:]])
if name=='git':
    if args[0]=='pull':
        import fcntl
        with (root/'deploy.lock').open('a') as competitor:
            try: fcntl.flock(competitor, fcntl.LOCK_EX | fcntl.LOCK_NB)
            except BlockingIOError: pass
            else: sys.exit(83)
        if failure in ('update-script','unsupported-update'):
            updated=(root/'bin/deploy-production').read_text().replace('Deployed PHP=', 'UPDATED_DEPLOY PHP=')
            if failure=='unsupported-update': updated=updated.replace('# SOAMCO_DEPLOY_LOCK_PROTOCOL=1', '# OLD_LOCK_PROTOCOL')
            (root/'next-deploy').write_text(updated)
            os.replace(root/'next-deploy',root/'bin/deploy-production')
        sys.exit(0)
    if args[0]=='ls-files':
        if failure=='untracked': sys.stdout.write('app/uncommitted.php'+chr(0))
        if failure=='untracked-example': sys.stdout.write('.env.example'+chr(0))
        if failure=='server-files': sys.stdout.write('.env.docker'+chr(0)+'deploy.sh'+chr(0))
        sys.exit(0)
    if args[0]=='fetch': sys.exit(1 if failure=='choice-unavailable' else 0)
    if args[0]=='cat-file': print((root/'selection.txt').read_text()); sys.exit(0)
    if args[0]=='rev-parse':
        if 'FETCH_HEAD^{tag}' in args and failure=='choice-lightweight': sys.exit(1)
        print('b'*40 if 'FETCH_HEAD^{tag}' in args else 'a'*40)
    elif args[0]=='symbolic-ref': print('release/3.5.4-beta' if failure=='branch' else 'main')
    elif args[0]=='diff' and (failure=='dirty' or (failure=='staged' and '--cached' in args) or (root/'changed').exists()): sys.exit(1)
    sys.exit(0)
if name=='python3':
    import runpy, shutil
    target=args[1] if args[0]=='-B' else args[0]
    rest=args[2:] if args[0]=='-B' else args[1:]
    scope=runpy.run_path(target)['main'].__globals__
    if 'shutil' in scope: scope['shutil'].disk_usage=lambda path: type('Space',(),{'free':10**12})()
    if 'os' in scope: scope['os'].statvfs=lambda path: type('Inodes',(),{'f_favail':1000000})()
    sys.argv=[target,*rest]
    sys.exit(scope['main']())
command=' '.join(args)
if args[0]=='info':
    print(json.dumps({'DockerRootDir':str(root),'OSType':'linux','Architecture':'x86_64'})); sys.exit(0)
if args[0]=='pull': sys.exit(9 if failure=='pull' else 0)
if args[:2]==['image','inspect']:
    if '--format' in args: print('linux|amd64|'+'a'*40); sys.exit(0)
    if failure=='missing': sys.exit(1)
    web='/web:' in args[-1] or '-web:' in args[-1]
    target='web' if web else 'php'
    commit='d'*40 if failure=='commit' else 'a'*40
    fingerprint='invalid' if failure=='frontend' else os.environ['MOCK_FINGERPRINT']
    print(json.dumps([{'Id': 'sha256:'+('c' if web else 'b')*64,'Os':'linux', 'Architecture':'arm64' if failure=='architecture' else 'amd64',
      'Config':{'Labels':{'org.opencontainers.image.revision':commit, 'org.opencontainers.image.source':'https://github.com/example/soamco-budget',
        'io.soamco.frontend-config.sha256':fingerprint}},'RepoDigests':['ghcr.io/example/soamco-budget/'+target+'@sha256:'+'e'*64]}])); sys.exit(0)
if 'config --format json' in command:
    args_public=json.loads((root/'args.json').read_text())
    prepared='prepared.yml' in command
    services={}
    for service in ['app','web','logs-init','maintenance','horizon','scheduler','reverb']:
        image=os.environ.get('SOAMCO_WEB_IMAGE' if service=='web' else 'SOAMCO_PHP_IMAGE','placeholder')
        services[service]={'image':image,'pull_policy':'never'}
    services['app']['environment']={'APP_ENV':'production','APP_DEBUG':'false','APP_KEY':'SYNTHETIC-KEY-DO-NOT-LOG','DB_CONNECTION':'pgsql','DB_HOST':'db','DB_DATABASE':'synthetic','DB_USERNAME':'synthetic'}
    if not prepared: services['app']['build']={'args':args_public}
    if failure=='config': services['app']['environment']['APP_KEY']=''
    print(json.dumps({'services':services})); sys.exit(0)
if 'firebase-credentials-check.php' in command and failure=='credentials': sys.exit(10)
if 'postgres-connection-check.php' in command and failure=='database':
    print('PostgreSQL preflight failed: Cannot connect and authenticate',file=sys.stderr); sys.exit(11)
if 'build app web' in command and failure=='dirty-during-build': (root/'changed').write_text('yes')
if 'migrate --force' in command and failure=='migrate': sys.exit(8)
if 'force-recreate' in command and failure=='health': sys.exit(9)
if 'exec -T app application-healthcheck' in command and failure=='health': sys.exit(9)
if 'exec -T proxy test -s' in command: sys.exit(1)
if 'ps --status running -q' in command: print('old-container')
if args[0]=='inspect': print('sha256:'+'f'*64)
if '2019/config/' in command: print('{}')
`,
    );
    chmodSync(join(root, 'mock'), 0o755);

    for (const name of ['docker', 'git', 'flock', 'stat', 'sudo', 'python3']) {
        copyFileSync(join(root, 'mock'), join(root, name));
        chmodSync(join(root, name), 0o755);
    }

    const run = (mode, failure = '', extra = [], selection = 'legacy') => {
        writeFileSync(join(root, 'calls'), '');
        const result = spawnSync(
            'bash',
            [
                join(root, 'bin/deploy-production'),
                ...(mode ? [mode] : []),
                ...extra,
            ],
            {
                env: {
                    ...process.env,
                    PATH: root + ':' + process.env.PATH,
                    MOCK_ROOT: root,
                    MOCK_FAILURE: failure,
                    MOCK_FINGERPRINT:
                        plan.bake.target.php.labels[
                            'io.soamco.frontend-config.sha256'
                        ],
                    DOCKER_CONFIG: join(root, 'docker-config'),
                    SOAMCO_DEPLOY_SELECTION: selection,
                    SOAMCO_IMAGE_REPOSITORY: 'example/soamco-budget',
                    SOAMCO_PHP_IMAGE: '',
                    SOAMCO_WEB_IMAGE: '',
                },
                encoding: 'utf8',
                timeout: 15000,
            },
        );

        return { result, calls: readFileSync(join(root, 'calls'), 'utf8') };
    };

    try {
        callback({ root, run });
    } finally {
        rmSync(root, { recursive: true, force: true });
    }
}

for (const mode of ['', 'local', 'ghcr', 'ssh']) {
    test(`deployment ${mode || 'default'} uses only its explicit preparation path`, () =>
        fixture(({ run }) => {
            const { result, calls } = run(mode);
            assert.equal(result.status, 0, result.stderr);
            assert.match(calls, /migrate --force/);
            assert.match(calls, /--no-deps --no-build --force-recreate --wait/);

            if (!mode || mode === 'local') {
                assert.match(calls, /build app web/);
                assert.doesNotMatch(calls, /docker pull/);
            } else {
                assert.doesNotMatch(calls, /build app web|docker image load/);
                assert.match(calls, new RegExp(phpId));
                assert.match(calls, new RegExp(webId));
                assert.ok(
                    calls.indexOf('image inspect') <
                        calls.indexOf('artisan down'),
                );
                assert.ok(
                    calls.indexOf('firebase-credentials-check.php') <
                        calls.indexOf('artisan down'),
                );
                assert.doesNotMatch(
                    result.stdout + result.stderr,
                    /SYNTHETIC-(AUTH|KEY)-DO-NOT-LOG/,
                );

                if (mode === 'ghcr') {
                    assert.match(
                        calls,
                        /pull --platform linux\/amd64 ghcr.io\/example\/soamco-budget\/php:sha-/,
                    );
                } else {
                    assert.doesNotMatch(calls, /docker pull/);
                }
            }
        }));
}

for (const [mode, failure] of [
    ['ghcr', 'pull'],
    ['ssh', 'missing'],
    ['ssh', 'commit'],
    ['ghcr', 'architecture'],
    ['ssh', 'frontend'],
    ['ssh', 'config'],
    ['ssh', 'credentials'],
    ['ssh', 'dirty'],
]) {
    test(`${mode} ${failure} stops before maintenance without fallback`, () =>
        fixture(({ run }) => {
            const { result, calls } = run(
                mode,
                failure,
                failure === 'dirty' ? ['--skip-pull'] : [],
            );
            assert.notEqual(result.status, 0);
            assert.doesNotMatch(
                calls,
                /build app web|artisan down|migrate --force|force-recreate|caddy reload/,
            );
        }));
}

for (const mode of ['ghcr', 'ssh']) {
    for (const failure of ['migrate', 'health']) {
        test(`${mode} preserves existing maintenance recovery after ${failure}`, () =>
            fixture(({ run }) => {
                const { result, calls } = run(mode, failure);
                assert.notEqual(result.status, 0);
                assert.doesNotMatch(
                    calls,
                    /volume rm|down -v|image prune|build app web/,
                );

                if (failure === 'migrate') {
                    assert.match(calls, /artisan up/);
                    assert.doesNotMatch(calls, /force-recreate/);
                } else {
                    assert.doesNotMatch(
                        calls,
                        /artisan up|caddy reload --config \/config\/soamco-deploy-normal.json/,
                    );
                }
            }));
    }
}

test('proposed main wrapper delegates pull to the existing lock using the original Git identity', () =>
    fixture(({ run }) => {
        const wrapper = read('docker/deploy.sh.example');
        assert.doesNotMatch(wrapper, /git pull|--skip-pull/);
        assert.match(wrapper, /--pull-user soamco --pull-branch main "\$@"/);
        const { result, calls } = run('ssh', '', [
            '--pull-user',
            'soamco',
            '--pull-branch',
            'main',
        ]);
        assert.equal(result.status, 0, result.stderr);
        assert.ok(
            calls.indexOf('flock -n 9') <
                calls.indexOf('sudo -u soamco git pull --ff-only origin main'),
        );
    }));

test('invalid modes and an occupied lock cannot start Git or Docker', () =>
    fixture(({ run }) => {
        let execution = run('automatic');
        assert.equal(execution.result.status, 2);
        assert.equal(execution.calls, '');
        execution = run('ssh', 'lock');
        assert.notEqual(execution.result.status, 0);
        assert.doesNotMatch(execution.calls, /git |docker /);
    }));

test('GHCR requires credentials before contacting the registry while SSH needs none', () =>
    fixture(({ root, run }) => {
        rmSync(join(root, 'docker-config/config.json'));
        let execution = run('ghcr');
        assert.notEqual(execution.result.status, 0);
        assert.doesNotMatch(
            execution.calls,
            /docker pull|artisan down|build app web/,
        );
        execution = run('ssh');
        assert.equal(execution.result.status, 0, execution.result.stderr);
        assert.doesNotMatch(execution.calls, /docker pull/);
    }));

test('explicit mode option and skip-pull do not silently select another preparation path', () =>
    fixture(({ run }) => {
        const { result, calls } = run('--mode', '', ['ssh', '--skip-pull']);
        assert.equal(result.status, 0, result.stderr);
        assert.doesNotMatch(calls, /git pull|build app web|docker pull/);
    }));

test('the proposed wrapper refuses a checkout on another branch before pulling or preparing images', () =>
    fixture(({ run }) => {
        const { result, calls } = run('ssh', 'branch', [
            '--pull-user',
            'soamco',
            '--pull-branch',
            'main',
        ]);
        assert.notEqual(result.status, 0);
        assert.doesNotMatch(calls, /git pull|docker /);
    }));

function writeSelection(root, mode, changes = {}) {
    const preparation =
        mode === 'local'
            ? null
            : {
                  workflow:
                      mode === 'ghcr'
                          ? 'production-images.yml'
                          : 'production-images-ssh.yml',
                  run_id: 42,
                  run_attempt: 1,
              };
    const record = {
        schema: 'soamco-deploy-selection-v1',
        commit: sha,
        repository: 'example/soamco-budget',
        mode,
        selection_run_id: 400,
        preparation,
        ...changes,
    };
    writeFileSync(
        join(root, 'selection.txt'),
        `object ${sha}\ntype commit\ntag soamco-deploy-${sha}\ntagger Soamco Deployment Selection <synthetic@example.invalid> 1 +0000\n\n${JSON.stringify(record)}\n`,
    );
}

for (const mode of ['local', 'ghcr', 'ssh']) {
    test(`activated no-argument deployment uses only the exact commit's ${mode} selection`, () =>
        fixture(({ root, run }) => {
            writeSelection(root, mode);
            const { result, calls } = run('', '', [], 'github');
            assert.equal(result.status, 0, result.stderr);
            assert.match(
                calls,
                new RegExp(
                    'git fetch --quiet --no-tags origin refs/tags/soamco-deploy-' +
                        sha,
                ),
            );
            assert.match(
                result.stdout,
                new RegExp('GitHub selection: mode=' + mode + ' commit=' + sha),
            );

            if (mode === 'local') {
                assert.match(calls, /build app web/);
            } else {
                assert.doesNotMatch(calls, /build app web/);
            }

            if (mode === 'ssh') {
                assert.doesNotMatch(calls, /docker pull/);
            }
        }));
}

for (const failure of ['choice-unavailable', 'choice-lightweight']) {
    test(`activated ${failure} never uses a cached selection or the local default`, () =>
        fixture(({ root, run }) => {
            writeSelection(root, 'local');
            const { result, calls } = run('', failure, [], 'github');
            assert.notEqual(result.status, 0);
            assert.doesNotMatch(calls, /docker |artisan down|build app web/);
        }));
}

for (const changes of [
    { commit: 'd'.repeat(40) },
    { repository: 'other/repository' },
    { mode: 'automatic' },
    { preparation: null },
    {
        preparation: {
            workflow: 'production-images-ssh.yml',
            run_id: 42,
            run_attempt: 1,
        },
    },
]) {
    test(`activated invalid record ${JSON.stringify(changes)} fails before preparation`, () =>
        fixture(({ root, run }) => {
            writeSelection(root, 'ghcr', changes);
            const { result, calls } = run('', '', [], 'github');
            assert.notEqual(result.status, 0);
            assert.doesNotMatch(calls, /docker |artisan down|build app web/);
        }));
}

for (const [mode, failure] of [
    ['ghcr', 'pull'],
    ['ssh', 'missing'],
    ['local', 'dirty'],
]) {
    test(`activated ${mode} ${failure} cannot bypass required preparation or checkout checks`, () =>
        fixture(({ root, run }) => {
            writeSelection(root, mode);
            const { result, calls } = run(
                '',
                failure,
                ['--skip-pull'],
                'github',
            );
            assert.notEqual(result.status, 0);
            assert.doesNotMatch(
                calls,
                /artisan down|build app web|migrate --force/,
            );
        }));
}

test('legacy behavior stays active until explicitly enabled, and active selection rejects CLI bypasses', () =>
    fixture(({ root, run }) => {
        writeSelection(root, 'ghcr');
        let execution = run('');
        assert.equal(execution.result.status, 0, execution.result.stderr);
        assert.match(execution.calls, /build app web/);
        assert.doesNotMatch(execution.calls, /git fetch/);
        execution = run('local', '', [], 'github');
        assert.equal(execution.result.status, 2);
        assert.equal(execution.calls, '');
    }));

test('pull atomically replaces the running script and only the fresh version deploys under the same lock', () =>
    fixture(({ root, run }) => {
        const { result, calls } = run('', 'update-script', [
            '--pull-user',
            'soamco',
            '--pull-branch',
            'main',
        ]);
        assert.equal(result.status, 0, result.stderr);
        assert.match(result.stdout, /UPDATED_DEPLOY PHP=/);
        assert.equal((calls.match(/^flock -n 9$/gm) ?? []).length, 2);
        assert.equal((calls.match(/^git pull /gm) ?? []).length, 1);
        assert.ok(calls.indexOf('flock -n 9') < calls.indexOf('git pull'));
        assert.match(
            readFileSync(join(root, 'bin/deploy-production'), 'utf8'),
            /UPDATED_DEPLOY PHP=/,
        );
    }));

for (const mode of ['', 'local']) {
    for (const failure of [
        'dirty',
        'staged',
        'untracked',
        'untracked-example',
    ]) {
        test(`local ${mode || 'default'} --skip-pull rejects ${failure} inputs before building`, () =>
            fixture(({ run }) => {
                const { result, calls } = run(mode, failure, ['--skip-pull']);
                assert.notEqual(result.status, 0);
                assert.doesNotMatch(calls, /docker |artisan down/);
            }));
    }
}

test('local still accepts server-only files excluded from its build and detects changes during preparation', () =>
    fixture(({ run }) => {
        let execution = run('local', 'server-files', ['--skip-pull']);
        assert.equal(execution.result.status, 0, execution.result.stderr);
        execution = run('local', 'dirty-during-build', ['--skip-pull']);
        assert.notEqual(execution.result.status, 0);
        assert.match(execution.calls, /build app web/);
        assert.doesNotMatch(
            execution.calls,
            /artisan down|migrate --force|force-recreate/,
        );
    }));

for (const mode of ['local', 'ghcr', 'ssh']) {
    test(`${mode} rejects PostgreSQL authentication failure before any maintenance or migration`, () =>
        fixture(({ run }) => {
            const { result, calls } = run(mode, 'database', ['--skip-pull']);
            assert.equal(result.status, 11, result.stderr);
            assert.match(
                calls,
                /run --rm --no-deps --user www-data --entrypoint php app docker\/php\/postgres-connection-check.php/,
            );
            assert.doesNotMatch(
                calls,
                /artisan down|migrate --force|force-recreate|caddy reload| stop /,
            );
        }));
}

test('the internal inherited-lock option cannot bypass the deployment lock', () =>
    fixture(({ run }) => {
        const { result, calls } = run('ssh', '', [
            '--skip-pull',
            '--inherit-lock',
        ]);
        assert.notEqual(result.status, 0);
        assert.match(result.stderr, /Invalid inherited deployment lock/);
        assert.doesNotMatch(calls, /git |docker /);
    }));

test('an updated script without the inherited-lock protocol stops without stale execution or fallback', () =>
    fixture(({ run }) => {
        const { result, calls } = run('local', 'unsupported-update');
        assert.notEqual(result.status, 0);
        assert.match(
            result.stderr,
            /does not support the inherited-lock protocol/,
        );
        assert.doesNotMatch(calls, /docker /);
    }));
