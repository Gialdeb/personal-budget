import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import yaml from 'js-yaml';
import { selectDeployment } from '../../bin/deployment-choice-ci.mjs';

const sha = 'a'.repeat(40);
const context = {
    eventName: 'workflow_dispatch',
    ref: 'refs/heads/main',
    sha,
    runId: 400,
    repo: { owner: 'example', repo: 'soamco-budget' },
    payload: { repository: { fork: false } },
};
const policies = {
    ghcr: {
        workflow: 'production-images.yml',
        steps: ['Verify private publication and report immutable digests'],
    },
    ssh: {
        workflow: 'production-images-ssh.yml',
        steps: [
            'Verify layers and prepare checksummed transfer bundle',
            'Transfer and load images only using pinned SSH',
        ],
    },
};

function fixture(mode, overrides = {}) {
    const policy = policies[mode] ?? policies.ghcr;
    const run = {
        id: 42,
        run_attempt: 2,
        head_sha: sha,
        head_branch: 'main',
        event: 'workflow_dispatch',
        status: 'completed',
        conclusion: 'success',
        path: `.github/workflows/${policy.workflow}`,
        head_repository: { full_name: 'example/soamco-budget' },
        ...overrides.run,
    };
    const job = {
        name: 'images',
        head_sha: sha,
        run_id: 42,
        status: 'completed',
        conclusion: 'success',
        steps: policy.steps.map((name) => ({
            name,
            status: 'completed',
            conclusion: 'success',
        })),
        ...overrides.job,
    };
    const calls = [];
    const writes = [];
    const github = {
        rest: {
            repos: {
                getBranch: async () => ({
                    data: { commit: { sha: overrides.main ?? sha } },
                }),
            },
            git: {
                getRef: async () => {
                    if (overrides.existing) {
                        return { data: {} };
                    }

                    throw Object.assign(new Error('absent'), {
                        status: overrides.lookupError ?? 404,
                    });
                },
                createTag: async (value) => {
                    writes.push({ type: 'tag', value });

                    return { data: { sha: 'b'.repeat(40) } };
                },
                createRef: async (value) => {
                    if (overrides.race) {
                        throw Object.assign(new Error('already exists'), {
                            status: 422,
                        });
                    }

                    writes.push({ type: 'ref', value });
                },
            },
        },
        paginate: async (route, values) => {
            calls.push({ route, values });

            return route.endsWith('/runs')
                ? (overrides.runs ?? [
                      { ...run, id: 9999, head_sha: 'c'.repeat(40) },
                      run,
                  ])
                : [job];
        },
    };
    const core = {
        summary: {
            addHeading() {
                return this;
            },
            addRaw() {
                return this;
            },
            async write() {},
        },
    };

    return { github, core, context, calls, writes };
}

for (const mode of ['local', 'ghcr', 'ssh']) {
    test(`records explicit ${mode} against the exact main commit`, async () => {
        const fixtureData = fixture(mode);
        await selectDeployment(fixtureData, mode);
        const { writes, calls } = fixtureData;
        assert.equal(writes.length, 2);
        assert.equal(writes[0].value.object, sha);
        assert.equal(writes[0].value.type, 'commit');
        assert.equal(writes[1].value.ref, 'refs/tags/soamco-deploy-' + sha);
        const record = JSON.parse(writes[0].value.message);
        assert.equal(record.commit, sha);
        assert.equal(record.mode, mode);
        assert.equal(record.repository, 'example/soamco-budget');

        if (mode === 'local') {
            assert.equal(record.preparation, null);
            assert.deepEqual(calls, []);
        } else {
            assert.equal(record.preparation.run_id, 42);
            assert.equal(record.preparation.run_attempt, 2);
            assert.equal(calls[0].values.head_sha, sha);
            assert.equal(calls[0].values.workflow_id, policies[mode].workflow);
            assert.equal(calls[0].values.branch, 'main');
            assert.equal(calls[0].values.per_page, 100);
            assert.equal(calls[1].values.run_id, 42);
            assert.equal(calls[1].values.attempt_number, 2);
        }
    });
}

for (const mode of ['ghcr', 'ssh']) {
    for (const invalid of [
        { runs: [] },
        { run: { head_sha: 'd'.repeat(40) } },
        { run: { head_branch: 'release/3.5.4-beta' } },
        { run: { path: '.github/workflows/unrelated.yml' } },
        { job: { conclusion: 'skipped' } },
        { job: { steps: [] } },
        { job: { head_sha: 'd'.repeat(40) } },
        { run: { head_repository: { full_name: 'other/repository' } } },
    ]) {
        test(`${mode} rejects missing, unrelated or skipped preparation ${JSON.stringify(invalid)}`, async () => {
            const fixtureData = fixture(mode, invalid);
            await assert.rejects(
                selectDeployment(fixtureData, mode),
                /No verified/,
            );
            assert.deepEqual(fixtureData.writes, []);
        });
    }
}

for (const invalid of [
    { existing: true },
    { main: 'e'.repeat(40) },
    { lookupError: 403 },
]) {
    test(`refuses to overwrite or guess a choice ${JSON.stringify(invalid)}`, async () => {
        const fixtureData = fixture('local', invalid);
        await assert.rejects(selectDeployment(fixtureData, 'local'));
        assert.deepEqual(fixtureData.writes, []);
    });
}

test('atomic reference creation rejects a concurrent selection instead of updating it', async () => {
    const fixtureData = fixture('local', { race: true });
    await assert.rejects(
        selectDeployment(fixtureData, 'local'),
        /already exists/,
    );
    assert.deepEqual(
        fixtureData.writes.map((entry) => entry.type),
        ['tag'],
    );
});

for (const change of [
    { eventName: 'push' },
    { ref: 'refs/heads/release/3.5.4-beta' },
]) {
    test(`rejects non-main or automatic selection ${JSON.stringify(change)}`, async () => {
        const fixtureData = fixture('local');
        await assert.rejects(
            selectDeployment(
                { ...fixtureData, context: { ...context, ...change } },
                'local',
            ),
        );
        assert.deepEqual(fixtureData.writes, []);
    });
}

test('selection workflow is manual metadata only, with three explicit choices and no runtime credentials', () => {
    const text = readFileSync(
        new URL(
            '../../.github/workflows/deployment-choice.yml',
            import.meta.url,
        ),
        'utf8',
    );
    const workflow = yaml.load(text);
    assert.deepEqual(Object.keys(workflow.on), ['workflow_dispatch']);
    assert.deepEqual(workflow.on.workflow_dispatch.inputs.mode.options, [
        'ghcr',
        'ssh',
        'local',
    ]);
    assert.equal(workflow.on.workflow_dispatch.inputs.mode.required, true);
    assert.deepEqual(workflow.jobs.select.permissions, {
        contents: 'write',
        actions: 'read',
    });
    assert.doesNotMatch(
        text,
        /secrets\.|docker\/|ssh |deploy-production|deploy\.sh|workflow_run:/,
    );
    assert.equal(workflow.concurrency['cancel-in-progress'], false);
});
