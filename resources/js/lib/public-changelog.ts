import {
    index as changelogFeedIndex,
    show as changelogFeedShow,
} from '@/routes/changelog/releases';
import type { PublicChangelogRelease } from '@/types';

type ChangelogFeedResponse = {
    data: PublicChangelogRelease[];
};

type ChangelogDetailResponse = {
    data: PublicChangelogRelease;
};

export async function fetchPublicChangelogIndex(
    locale: string,
): Promise<PublicChangelogRelease[]> {
    const response = await fetch(
        changelogFeedIndex.url({
            query: {
                locale,
            },
        }),
        {
            headers: {
                Accept: 'application/json',
            },
        },
    );

    if (!response.ok) {
        throw new Error(`Unable to load changelog index: ${response.status}`);
    }

    const payload = (await response.json()) as ChangelogFeedResponse;

    return payload.data;
}

export async function fetchPublicChangelogRelease(
    versionLabel: string,
    locale: string,
): Promise<PublicChangelogRelease | null> {
    const response = await fetch(
        changelogFeedShow.url(versionLabel, {
            query: {
                locale,
            },
        }),
        {
            headers: {
                Accept: 'application/json',
            },
        },
    );

    if (response.status === 404) {
        return null;
    }

    if (!response.ok) {
        throw new Error(`Unable to load changelog release: ${response.status}`);
    }

    const payload = (await response.json()) as ChangelogDetailResponse;

    return payload.data;
}
