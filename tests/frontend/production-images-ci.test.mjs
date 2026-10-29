import assert from 'node:assert/strict';
import { existsSync, mkdtempSync, readFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import test from 'node:test';
import yaml from 'js-yaml';
import {
    imagePlan,
    prepare,
    prepareSsh,
    verifyPublished,
} from '../../bin/production-images-ci.mjs';

const context = {
    eventName: 'workflow_dispatch',
    ref: 'refs/heads/release/3.5.4-beta',
    sha: 'a'.repeat(40),
    repo: { owner: 'ExampleOwner', repo: 'Soamco-Budget' },
    payload: { repository: { fork: false } },
};
const read = (path) =>
    readFileSync(new URL('../../' + path, import.meta.url), 'utf8');

function api({
    visibility = 'private',
    versions = [],
    missing = false,
    status = null,
    type = 'Organization',
} = {}) {
    const calls = [];

    return {
        calls,
        rest: { users: { getByUsername: async () => ({ data: { type } }) } },
        request: async (route, parameters) => {
            calls.push({ route, parameters });

            if (missing || status) {
                throw Object.assign(new Error('synthetic API error'), {
                    status: status ?? 404,
                });
            }

            return { data: { visibility } };
        },
        paginate: async (route, parameters) => {
            calls.push({ route, parameters });

            return versions;
        },
    };
}

async function withRunner(callback) {
    const temporary = mkdtempSync(join(tmpdir(), 'soamco-images-ci-'));
    const outputs = {};
    const summary = {
        rows: null,
        written: false,
        addHeading() {
            return this;
        },
        addTable(rows) {
            this.rows = rows;

            return this;
        },
        async write() {
            this.written = true;
        },
    };
    const core = {
        setOutput: (name, value) => {
            outputs[name] = value;
        },
        summary,
    };

    try {
        await callback({
            temporary,
            outputs,
            core,
            environment: { RUNNER_TEMP: temporary },
        });
    } finally {
        rmSync(temporary, { recursive: true, force: true });
    }
}

test('image workflow builds only on GitHub and has no production deployment or runtime credentials', () => {
    const workflow = yaml.load(read('.github/workflows/production-images.yml'));
    assert.ok('workflow_dispatch' in workflow.on);
    assert.equal(workflow.concurrency['cancel-in-progress'], false);
    assert.equal(workflow.jobs.images['runs-on'], 'ubuntu-24.04');
    assert.deepEqual(workflow.jobs.images.permissions, {
        contents: 'read',
        packages: 'write',
    });
    assert.match(workflow.jobs.images.if, /refs\/heads\/release\/3\.5\.4-beta/);
    const steps = workflow.jobs.images.steps;
    const checkout = steps.find((step) =>
        step.uses?.startsWith('actions/checkout@'),
    );
    assert.equal(
        checkout.with.ref,
        '${{ needs.verify-ci.outputs.sha || github.sha }}',
    );
    assert.equal(checkout.with['persist-credentials'], false);
    const bake = steps.find((step) =>
        step.uses?.startsWith('docker/bake-action@'),
    );
    assert.equal(bake.with.source, '.');
    assert.equal(bake.with.push, true);
    assert.equal(bake.with.provenance, 'mode=min');
    assert.equal(workflow.jobs.images.env.DOCKER_BUILD_RECORD_UPLOAD, 'false');
    const serialized = JSON.stringify(steps);
    assert.doesNotMatch(
        serialized,
        /deploy-production|docker compose|ssh |scp |POSTGRES_PASSWORD|latest/,
    );
    assert.deepEqual(
        [...serialized.matchAll(/secrets\.([A-Z_]+)/g)].map(
            (match) => match[1],
        ),
        ['GITHUB_TOKEN'],
    );
    assert.ok(
        steps.findIndex((step) => step.id === 'prepare') <
            steps.findIndex((step) => step.id === 'build'),
    );
    assert.match(read('.dockerignore'), /^\.env\n\.env\.\*$/m);
});

test('production targets build amd64 PHP and reuse its exact frontend assets in web', () => {
    const plan = imagePlan(context, {
        VITE_APP_NAME: 'Synthetic title',
        VITE_REVERB_HOST: 'example.invalid',
        APP_KEY: 'never-pass-this',
    });
    assert.equal(plan.tag, 'sha-' + context.sha);
    assert.deepEqual(plan.bake.group.default.targets, ['php', 'web']);
    assert.equal(plan.bake.target.php.target, 'production');
    assert.deepEqual(plan.bake.target.web.contexts, {
        app_assets: 'target:php',
    });
    assert.equal(plan.bake.target.php.args.VITE_APP_NAME, 'Synthetic title');
    assert.equal(plan.bake.target.php.args.VITE_REVERB_PORT, '443');
    assert.equal(plan.bake.target.php.args.VITE_REVERB_SCHEME, 'https');
    assert.ok(
        Object.keys(plan.bake.target.php.args).every((name) =>
            name.startsWith('VITE_'),
        ),
    );
    assert.doesNotMatch(
        JSON.stringify(plan),
        /never-pass-this|latest|"APP_KEY"|\.env/,
    );

    for (const target of Object.values(plan.bake.target)) {
        assert.deepEqual(target.platforms, ['linux/amd64']);
        assert.equal(
            target.labels['org.opencontainers.image.revision'],
            context.sha,
        );
        assert.match(
            target.tags[0],
            /^ghcr\.io\/exampleowner\/soamco-budget\/(php|web):sha-[a-f0-9]{40}$/,
        );
    }

    assert.match(
        read('docker/php/Dockerfile'),
        /npm ci --include=dev && npm run build/,
    );
    assert.match(read('docker/nginx/Dockerfile'), /FROM app_assets AS assets/);
});

for (const change of [
    { eventName: 'push' },
    { ref: 'refs/heads/develop' },
    { sha: 'latest' },
    { payload: { repository: { fork: true } } },
]) {
    test(`rejects unsupported source ${JSON.stringify(change)}`, () => {
        assert.throws(
            () => imagePlan({ ...context, ...change }),
            /Only a manual/,
        );
    });
}

for (const type of ['Organization', 'User']) {
    test(`prepares private new ${type} packages without registry writes`, async () => {
        await withRunner(async ({ environment, outputs, core }) => {
            const github = api({ missing: true, type });
            await prepare({ github, context, core }, environment);
            const bake = JSON.parse(readFileSync(outputs['bake-file'], 'utf8'));
            assert.deepEqual(bake, imagePlan(context).bake);
            assert.ok(
                github.calls.every(({ route }) =>
                    route.startsWith(
                        type === 'Organization' ? 'GET /orgs/' : 'GET /users/',
                    ),
                ),
            );
        });
    });
}

for (const options of [
    { visibility: 'public' },
    { visibility: 'internal' },
    { status: 403 },
    { status: 500 },
    {
        versions: [
            { metadata: { container: { tags: ['sha-' + context.sha] } } },
        ],
    },
]) {
    test(`refuses unsafe publication ${JSON.stringify(options)}`, async () => {
        await withRunner(async ({ environment, outputs, core, temporary }) => {
            await assert.rejects(
                prepare({ github: api(options), context, core }, environment),
            );
            assert.deepEqual(outputs, {});
            assert.equal(
                existsSync(join(temporary, 'soamco-production-images.json')),
                false,
            );
        });
    });
}

test('refuses to rebuild a commit if only the web image already exists', async () => {
    await withRunner(async ({ environment, outputs, core }) => {
        const github = api();
        github.paginate = async (_route, parameters) =>
            parameters.package_name.endsWith('/web')
                ? [
                      {
                          metadata: {
                              container: { tags: ['sha-' + context.sha] },
                          },
                      },
                  ]
                : [];
        await assert.rejects(
            prepare({ github, context, core }, environment),
            /overwriting is forbidden/,
        );
        assert.deepEqual(outputs, {});
    });
});

test('verifies private publication against digests before reporting usable images', async () => {
    await withRunner(async ({ core }) => {
        const digest = 'sha256:' + 'b'.repeat(64);
        const github = api({
            versions: [
                {
                    name: digest,
                    metadata: { container: { tags: ['sha-' + context.sha] } },
                },
            ],
        });
        await verifyPublished(
            { github, context, core },
            {
                php: { 'containerimage.digest': digest },
                web: { 'containerimage.digest': digest },
            },
        );
        assert.equal(core.summary.written, true);
        assert.equal(core.summary.rows.length, 3);
        assert.equal(
            core.summary.rows[1][2],
            `ghcr.io/exampleowner/soamco-budget/php@${digest}`,
        );
    });
});

for (const failure of ['public', 'missing-digest', 'mismatched-digest']) {
    test(`never reports successful publication on ${failure}`, async () => {
        await withRunner(async ({ core }) => {
            const digest = 'sha256:' + 'b'.repeat(64);
            const github = api({
                visibility: failure === 'public' ? 'public' : 'private',
                versions: [
                    {
                        name: 'sha256:' + 'c'.repeat(64),
                        metadata: {
                            container: { tags: ['sha-' + context.sha] },
                        },
                    },
                ],
            });
            const metadata =
                failure === 'missing-digest'
                    ? {}
                    : { php: { 'containerimage.digest': digest } };
            await assert.rejects(
                verifyPublished({ github, context, core }, metadata),
            );
            assert.equal(core.summary.written, false);
        });
    });
}

test('SSH preparation reuses the production targets without registry access or credentials', async () => {
    await withRunner(async ({ environment, outputs, core }) => {
        prepareSsh(
            { context, core },
            {
                ...environment,
                VITE_APP_NAME: 'Synthetic SSH',
                SOAMCO_SSH_PRIVATE_KEY: 'DO-NOT-BUILD-THIS',
            },
        );
        const bake = JSON.parse(readFileSync(outputs['bake-file'], 'utf8'));
        assert.equal(bake.target.php.args.VITE_APP_NAME, 'Synthetic SSH');
        assert.equal(bake.target.php.target, 'production');
        assert.deepEqual(bake.target.web.contexts, {
            app_assets: 'target:php',
        });
        assert.doesNotMatch(
            JSON.stringify(bake),
            /ghcr\.io|DO-NOT-BUILD-THIS|registry|type=image/,
        );

        for (const [target, configuration] of Object.entries(bake.target)) {
            assert.deepEqual(configuration.platforms, ['linux/amd64']);
            assert.deepEqual(configuration.tags, [
                `soamco-budget-${target}:sha-${context.sha}`,
            ]);
            assert.equal(
                configuration.output[0],
                `type=docker,dest=${outputs['export-directory']}/${target}.export.tar,compression=uncompressed,force-compression=true`,
            );
        }
    });
});

test('SSH workflow limits secrets to transfer and cannot deploy or access GHCR', () => {
    const workflow = yaml.load(
        read('.github/workflows/production-images-ssh.yml'),
    );
    assert.deepEqual(Object.keys(workflow.on), ['workflow_dispatch']);
    assert.deepEqual(workflow.permissions, { contents: 'read' });
    assert.equal(workflow.concurrency['cancel-in-progress'], false);
    const job = workflow.jobs.images;
    assert.equal(job['runs-on'], 'ubuntu-24.04');
    assert.match(job.if, /refs\/heads\/release\/3\.5\.4-beta/);
    assert.doesNotMatch(JSON.stringify(job.env), /secrets\./);
    const build = job.steps.find((step) =>
        step.uses?.startsWith('docker/bake-action@'),
    );
    assert.equal(build.with.push, false);
    assert.equal(build.with.provenance, false);
    assert.equal(
        build.with.allow,
        'fs.write=${{ steps.prepare.outputs.export-directory }}',
    );
    const transferStep = job.steps.find(
        (step) => step.env?.SOAMCO_SSH_PRIVATE_KEY,
    );
    assert.match(transferStep.run, /production_images_transfer\.py send/);
    assert.equal(
        transferStep.env.SOAMCO_SSH_KNOWN_HOSTS,
        '${{ secrets.SOAMCO_SSH_KNOWN_HOSTS }}',
    );
    assert.ok(job.steps.indexOf(transferStep) > job.steps.indexOf(build));
    assert.doesNotMatch(
        JSON.stringify(job),
        /docker\/login-action|ghcr\.io|packages.*write|deploy-production|deploy\.sh|docker compose|ssh-keyscan/,
    );
    assert.ok(
        job.steps.some(
            (step) =>
                step.if === 'always()' &&
                step.run.includes('soamco-ssh-images'),
        ),
    );
});

test('manual image preparation also builds the exact main commit distributed by the VPS wrapper', () => {
    for (const transport of ['ghcr', 'ssh']) {
        const plan = imagePlan(
            { ...context, ref: 'refs/heads/main' },
            {},
            transport,
        );
        assert.equal(plan.tag, 'sha-' + context.sha);
        assert.match(
            plan.bake.target.php.labels['io.soamco.frontend-config.sha256'],
            /^[a-f0-9]{64}$/,
        );
        assert.equal(
            plan.bake.target.php.labels['io.soamco.frontend-config.sha256'],
            plan.bake.target.web.labels['io.soamco.frontend-config.sha256'],
        );
    }
});

const automaticContext = {
    ...context,
    eventName: 'workflow_run',
    sha: 'b'.repeat(40),
    payload: {
        repository: { fork: false, full_name: 'ExampleOwner/Soamco-Budget' },
        workflow_run: {
            head_sha: context.sha,
            head_branch: 'main',
            event: 'push',
            conclusion: 'success',
            head_repository: { full_name: 'ExampleOwner/Soamco-Budget' },
        },
    },
};

test('automatic checkout, tags and revision use the tested SHA, not the default branch tip', () => {
    const workflow = yaml.load(read('.github/workflows/production-images.yml'));
    assert.deepEqual(workflow.on.workflow_run, {
        workflows: ['tests', 'linter'],
        types: ['completed'],
        branches: ['main'],
    });
    assert.deepEqual(workflow.permissions, { contents: 'read' });
    assert.deepEqual(workflow.jobs['verify-ci'].permissions, {
        actions: 'read',
    });
    assert.equal(workflow.jobs.images.needs, 'verify-ci');
    assert.match(workflow.jobs.images.if, /always\(\)/);
    assert.match(workflow.jobs.images.if, /needs.verify-ci.outputs.sha != ''/);
    const plan = imagePlan(automaticContext);
    assert.equal(plan.tag, 'sha-' + context.sha);
    assert.equal(
        plan.bake.target.php.labels['org.opencontainers.image.revision'],
        context.sha,
    );
    assert.throws(() => imagePlan(automaticContext, {}, 'ssh'));
});

for (const change of [
    { head_branch: 'develop' },
    { event: 'pull_request' },
    { conclusion: 'failure' },
    { conclusion: 'cancelled' },
    { head_repository: { full_name: 'fork/repo' } },
    { head_sha: 'invalid' },
]) {
    test(`automatic image plan rejects ${JSON.stringify(change)}`, () => {
        assert.throws(() =>
            imagePlan({
                ...automaticContext,
                payload: {
                    ...automaticContext.payload,
                    workflow_run: {
                        ...automaticContext.payload.workflow_run,
                        ...change,
                    },
                },
            }),
        );
    });
}

async function runGate(workflows) {
    const workflow = yaml.load(read('.github/workflows/production-images.yml'));
    const script = workflow.jobs['verify-ci'].steps[0].with.script;
    const outputs = {};
    await new (Object.getPrototypeOf(async function () {}).constructor)(
        'github',
        'context',
        'core',
        script,
    )(
        {
            rest: { actions: { listWorkflowRuns: 'list' } },
            paginate: async (_route, params) => {
                assert.equal(params.head_sha, context.sha);
                assert.equal(params.branch, 'main');
                assert.equal(params.event, 'push');

                return workflows[params.workflow_id] ?? [];
            },
        },
        automaticContext,
        {
            info() {},
            setOutput: (key, value) => {
                outputs[key] = value;
            },
        },
    );

    return outputs;
}

const passedRun = {
    ...automaticContext.payload.workflow_run,
    status: 'completed',
    run_number: 1,
};

test('CI gate allows only both successful workflows for the identical main push', async () => {
    assert.deepEqual(
        await runGate({ 'tests.yml': [passedRun], 'lint.yml': [passedRun] }),
        { sha: context.sha },
    );

    for (const workflow of ['tests.yml', 'lint.yml']) {
        for (const runs of [
            [],
            [{ ...passedRun, conclusion: 'failure' }],
            [{ ...passedRun, conclusion: 'cancelled' }],
            [{ ...passedRun, status: 'in_progress', conclusion: null }],
            [{ ...passedRun, status: 'queued', conclusion: null }],
            [{ ...passedRun, head_sha: 'c'.repeat(40) }],
            [{ ...passedRun, head_branch: 'develop' }],
            [{ ...passedRun, event: 'pull_request' }],
            [passedRun, { ...passedRun, run_number: 2, conclusion: 'failure' }],
        ]) {
            assert.deepEqual(
                await runGate({
                    'tests.yml': [passedRun],
                    'lint.yml': [passedRun],
                    [workflow]: runs,
                }),
                {},
            );
        }
    }
});

test('repeated automatic events cannot republish an existing commit tag', async () => {
    await withRunner(async ({ environment, core }) => {
        const github = api({
            versions: [
                { metadata: { container: { tags: ['sha-' + context.sha] } } },
            ],
        });
        await assert.rejects(
            prepare({ github, context: automaticContext, core }, environment),
            /overwriting is forbidden/,
        );
    });
});

test('workflow_run preserves the toolkit prototype repo getter during normalization', async () => {
    const properties = { ...automaticContext };
    delete properties.repo;
    const toolkitContext = Object.assign(
        Object.create({
            get repo() {
                return context.repo;
            },
        }),
        properties,
    );
    delete toolkitContext.repo;
    assert.equal(Object.hasOwn(toolkitContext, 'repo'), false);
    assert.equal(imagePlan(toolkitContext).tag, 'sha-' + context.sha);
    await withRunner(async ({ environment, core, outputs }) => {
        await prepare(
            { github: api({ missing: true }), context: toolkitContext, core },
            environment,
        );
        assert.ok(outputs['bake-file']);
    });
});
