import { createHash } from 'node:crypto';
import { mkdirSync, writeFileSync } from 'node:fs';
import { join } from 'node:path';

const buildRefs = ['refs/heads/release/3.5.4-beta', 'refs/heads/main'];
const publicArguments = [
    'VITE_APP_NAME',
    'VITE_FIREBASE_API_KEY',
    'VITE_FIREBASE_AUTH_DOMAIN',
    'VITE_FIREBASE_PROJECT_ID',
    'VITE_FIREBASE_STORAGE_BUCKET',
    'VITE_FIREBASE_MESSAGING_SENDER_ID',
    'VITE_FIREBASE_APP_ID',
    'VITE_FIREBASE_VAPID_PUBLIC_KEY',
    'VITE_REVERB_APP_KEY',
    'VITE_REVERB_HOST',
    'VITE_REVERB_PORT',
    'VITE_REVERB_SCHEME',
];

export function imagePlan(context, environment = {}, transport = 'ghcr') {
    if (
        !['ghcr', 'ssh'].includes(transport) ||
        context.eventName !== 'workflow_dispatch' ||
        !buildRefs.includes(context.ref) ||
        context.payload.repository.fork ||
        !/^[a-f0-9]{40}$/.test(context.sha) ||
        !/^[A-Za-z0-9_.-]+$/.test(context.repo.owner) ||
        !/^[A-Za-z0-9_.-]+$/.test(context.repo.repo)
    ) {
        throw new Error(
            'Only a manual main or release/3.5.4-beta commit build is allowed.',
        );
    }

    const owner = context.repo.owner.toLowerCase();
    const repository = context.repo.repo.toLowerCase();
    const tag = `sha-${context.sha}`;
    const packages = ['php', 'web'].map((target) => ({
        target,
        name: `${repository}/${target}`,
        image:
            transport === 'ssh'
                ? `soamco-budget-${target}:${tag}`
                : `ghcr.io/${owner}/${repository}/${target}:${tag}`,
    }));
    const args = Object.fromEntries(
        publicArguments.map((name) => [name, environment[name] ?? '']),
    );
    args.VITE_APP_NAME ||= 'Soamco Budget';
    args.VITE_REVERB_PORT ||= '443';
    args.VITE_REVERB_SCHEME ||= 'https';
    const labels = {
        'org.opencontainers.image.source': `https://github.com/${context.repo.owner}/${context.repo.repo}`,
        'org.opencontainers.image.revision': context.sha,
        'io.soamco.frontend-config.sha256': createHash('sha256')
            .update(
                JSON.stringify(
                    Object.keys(args)
                        .sort()
                        .map((name) => [name, args[name]]),
                ),
            )
            .digest('hex'),
    };
    const shared = { context: '.', platforms: ['linux/amd64'], labels };

    return {
        owner,
        tag,
        packages,
        bake: {
            group: { default: { targets: ['php', 'web'] } },
            target: {
                php: {
                    ...shared,
                    dockerfile: 'docker/php/Dockerfile',
                    target: 'production',
                    args,
                    tags: [packages[0].image],
                },
                web: {
                    ...shared,
                    dockerfile: 'docker/nginx/Dockerfile',
                    contexts: { app_assets: 'target:php' },
                    tags: [packages[1].image],
                },
            },
        },
    };
}

export function prepareSsh({ context, core }, environment = process.env) {
    const plan = imagePlan(context, environment, 'ssh');
    const directory = join(environment.RUNNER_TEMP, 'soamco-ssh-images');
    mkdirSync(directory, { mode: 0o700 });

    for (const [target, configuration] of Object.entries(plan.bake.target)) {
        configuration.output = [
            `type=docker,dest=${join(directory, target + '.export.tar')},compression=uncompressed,force-compression=true`,
        ];
    }

    const file = join(environment.RUNNER_TEMP, 'soamco-ssh-bake.json');
    writeFileSync(file, JSON.stringify(plan.bake), { mode: 0o600, flag: 'wx' });
    core.setOutput('bake-file', file);
    core.setOutput('export-directory', directory);
}

async function packageDetails(github, owner, name) {
    const account = await github.rest.users.getByUsername({ username: owner });
    const base =
        account.data.type === 'Organization'
            ? '/orgs/{org}/packages/container/{package_name}'
            : '/users/{username}/packages/container/{package_name}';
    const parameters = { org: owner, username: owner, package_name: name };
    let response;

    try {
        response = await github.request(`GET ${base}`, parameters);
    } catch (error) {
        if (error.status === 404) {
            return null;
        }

        throw new Error(
            'Cannot verify GHCR package access; publication refused.',
        );
    }

    if (response.data.visibility !== 'private') {
        throw new Error('GHCR package is not private; publication refused.');
    }

    const versions = await github.paginate(`GET ${base}/versions`, {
        ...parameters,
        per_page: 100,
    });

    return versions;
}

export async function prepare(
    { github, context, core },
    environment = process.env,
) {
    const plan = imagePlan(context, environment);

    for (const item of plan.packages) {
        const versions = await packageDetails(github, plan.owner, item.name);

        if (
            versions?.some((version) =>
                version.metadata?.container?.tags?.includes(plan.tag),
            )
        ) {
            throw new Error(
                'Commit tag already exists; overwriting is forbidden.',
            );
        }
    }

    const file = join(environment.RUNNER_TEMP, 'soamco-production-images.json');
    writeFileSync(file, JSON.stringify(plan.bake), { mode: 0o600, flag: 'wx' });
    core.setOutput('bake-file', file);
}

export async function verifyPublished({ github, context, core }, metadata) {
    const plan = imagePlan(context);
    const rows = [];

    for (const item of plan.packages) {
        const digest = metadata[item.target]?.['containerimage.digest'];

        if (!/^sha256:[a-f0-9]{64}$/.test(digest ?? '')) {
            throw new Error('Missing image digest; publication is incomplete.');
        }

        const versions = await packageDetails(github, plan.owner, item.name);
        const published = versions?.find((version) =>
            version.metadata?.container?.tags?.includes(plan.tag),
        );

        if (published?.name !== digest) {
            throw new Error(
                'Private GHCR commit tag does not match the built digest.',
            );
        }

        rows.push([
            item.target,
            item.image,
            `ghcr.io/${plan.owner}/${item.name}@${digest}`,
        ]);
    }

    await core.summary
        .addHeading('Immagini private preparate — nessun deployment')
        .addTable([
            [
                { data: 'Immagine', header: true },
                { data: 'Tag commit', header: true },
                { data: 'Riferimento immutabile', header: true },
            ],
            ...rows,
        ])
        .write();
}
