const preparations = {
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

function requireCondition(condition, message) {
    if (!condition) {
        throw new Error(message);
    }
}

async function preparationProof(github, context, mode) {
    if (mode === 'local') {
        return null;
    }

    const policy = preparations[mode];
    const parameters = { ...context.repo };
    const runs = await github.paginate(
        'GET /repos/{owner}/{repo}/actions/workflows/{workflow_id}/runs',
        {
            ...parameters,
            workflow_id: policy.workflow,
            head_sha: context.sha,
            branch: 'main',
            event: 'workflow_dispatch',
            status: 'success',
            per_page: 100,
        },
    );
    const repository =
        `${context.repo.owner}/${context.repo.repo}`.toLowerCase();
    const candidates = runs
        .filter(
            (run) =>
                run.head_sha === context.sha &&
                run.head_branch === 'main' &&
                run.event === 'workflow_dispatch' &&
                run.status === 'completed' &&
                run.conclusion === 'success' &&
                run.path === `.github/workflows/${policy.workflow}` &&
                run.head_repository?.full_name?.toLowerCase() === repository &&
                Number.isSafeInteger(run.id) &&
                run.id > 0 &&
                Number.isSafeInteger(run.run_attempt) &&
                run.run_attempt > 0,
        )
        .sort((left, right) => left.id - right.id);

    for (const run of candidates) {
        const jobs = await github.paginate(
            'GET /repos/{owner}/{repo}/actions/runs/{run_id}/attempts/{attempt_number}/jobs',
            {
                ...parameters,
                run_id: run.id,
                attempt_number: run.run_attempt,
                per_page: 100,
            },
        );
        const verified = jobs.some(
            (job) =>
                job.name === 'images' &&
                job.head_sha === context.sha &&
                job.run_id === run.id &&
                job.status === 'completed' &&
                job.conclusion === 'success' &&
                policy.steps.every((name) =>
                    job.steps?.some(
                        (step) =>
                            step.name === name &&
                            step.status === 'completed' &&
                            step.conclusion === 'success',
                    ),
                ),
        );

        if (verified) {
            return {
                workflow: policy.workflow,
                run_id: run.id,
                run_attempt: run.run_attempt,
            };
        }
    }

    throw new Error(
        `No verified ${mode} preparation for this exact main commit; prepare images first. Skipped or unrelated runs do not qualify.`,
    );
}

export async function selectDeployment({ github, context, core }, mode) {
    requireCondition(
        context.eventName === 'workflow_dispatch' &&
            context.ref === 'refs/heads/main' &&
            !context.payload.repository.fork &&
            Number.isSafeInteger(context.runId) &&
            context.runId > 0 &&
            /^[a-f0-9]{40}$/.test(context.sha) &&
            ['local', 'ghcr', 'ssh'].includes(mode),
        'Select an explicit mode manually on main.',
    );
    const parameters = { ...context.repo };
    const tag = `soamco-deploy-${context.sha}`;
    const current = await github.rest.repos.getBranch({
        ...parameters,
        branch: 'main',
    });
    requireCondition(
        current.data.commit.sha === context.sha,
        'main advanced; select the current main commit again.',
    );

    try {
        await github.rest.git.getRef({ ...parameters, ref: `tags/${tag}` });

        throw new Error(
            'A selection already exists for this commit; it cannot be overwritten.',
        );
    } catch (error) {
        if (error.status !== 404) {
            throw error;
        }
    }

    const preparation = await preparationProof(github, context, mode);
    const record = {
        schema: 'soamco-deploy-selection-v1',
        commit: context.sha,
        repository: `${context.repo.owner}/${context.repo.repo}`.toLowerCase(),
        mode,
        selection_run_id: context.runId,
        preparation,
    };
    // Creating a ref is atomic and fails if another run already selected this SHA.
    // Never update an existing ref. No source commit, image build or deployment.
    const annotated = await github.rest.git.createTag({
        ...parameters,
        tag,
        message: JSON.stringify(record),
        object: context.sha,
        type: 'commit',
        tagger: {
            name: 'Soamco Deployment Selection',
            email: '41898282+github-actions[bot]@users.noreply.github.com',
        },
    });
    await github.rest.git.createRef({
        ...parameters,
        ref: `refs/tags/${tag}`,
        sha: annotated.data.sha,
    });
    await core.summary
        .addHeading('Modalità registrata — nessun deployment')
        .addRaw(
            `Commit main: \`${context.sha}\`\n\nModalità: \`${mode}\`\n\nScelta: \`${tag}\`\n`,
        )
        .write();
}
