import { shallowRef } from 'vue';
import { effects } from '@/actions/App/Http/Controllers/Settings/PendingCategoryController';
import { userErrorMessage } from '@/lib/notification-feedback.js';

type Transfer = {
    source: string;
    destination: string;
    amount: string;
    currency: string;
    year: number;
    month: number;
};
export const categoryConfirmation = shallowRef<{
    transfers: Transfer[];
    resolve: (value: boolean) => void;
} | null>(null);

export async function confirmPendingCategory(
    data: Record<string, unknown>,
    signal?: AbortSignal,
): Promise<string | null> {
    const response = await fetch(effects.url(), {
        method: 'POST',
        signal,
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN':
                document.querySelector<HTMLMetaElement>(
                    'meta[name="csrf-token"]',
                )?.content ?? '',
        },
        body: JSON.stringify({
            category_uuid: data.category_uuid,
            account_uuid: data.account_uuid,
            account_id: data.account_id,
        }),
    });
    const payload = await response.json();

    if (!response.ok) {
        throw new Error(
            userErrorMessage(
                Object.values(payload.errors ?? {})
                    .flat()
                    .join(' ') ||
                    'Non è stato possibile verificare i budget. Riprova prima di salvare.',
            ),
        );
    }

    if (!payload.transfers.length) {
        return payload.confirmation;
    }

    if (categoryConfirmation.value) {
        return null;
    }

    const accepted = await new Promise<boolean>((resolve) => {
        categoryConfirmation.value = { transfers: payload.transfers, resolve };
        signal?.addEventListener('abort', () => resolve(false), { once: true });

        if (signal?.aborted) {
            resolve(false);
        }
    });
    categoryConfirmation.value = null;

    return accepted ? payload.confirmation : null;
}
