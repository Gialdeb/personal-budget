import { useForm as inertiaUseForm } from '@inertiajs/vue3';
import { userErrorMessage } from '@/lib/notification-feedback.js';
import { confirmPendingCategory } from '@/lib/pending-category-confirmation';

/** Local validation stays inline. Provisional categories are confirmed at the main submit. */
export const useForm: typeof inertiaUseForm = ((...args: unknown[]) => {
    const form = (
        inertiaUseForm as (
            ...values: unknown[]
        ) => ReturnType<typeof inertiaUseForm>
    )(...args);
    const originalSubmit = form.submit.bind(form) as (
        ...values: unknown[]
    ) => void;
    const originalTransform = form.transform.bind(form);
    let transform = (data: Record<string, unknown>): Record<string, unknown> =>
        data;
    let busy = false;
    let controller: AbortController | null = null;
    const originalCancel = form.cancel.bind(form);
    form.cancel = () => {
        controller?.abort();
        originalCancel();
    };
    const originalReset = form.reset.bind(form);
    form.reset = ((...fields: unknown[]) => {
        controller?.abort();

        return (originalReset as (...args: unknown[]) => unknown)(...fields);
    }) as typeof form.reset;
    form.transform = ((callback: typeof transform) => {
        transform = callback;
        originalTransform(callback);

        return form;
    }) as typeof form.transform;
    form.submit = ((...values: unknown[]) => {
        const data = transform(form.data());

        if (
            !['post', 'put', 'patch'].includes(String(values[0])) ||
            typeof data.category_uuid !== 'string' ||
            !data.category_uuid.startsWith('pending-category:')
        ) {
            return originalSubmit(...values);
        }

        if (busy) {
            return;
        }

        busy = true;
        controller = new AbortController();
        const signal = controller.signal;
        form.processing = true;
        void (async () => {
            let submitted = false;
            const release = () => {
                busy = false;
                form.processing = false;
                originalTransform(transform);
            };

            try {
                const confirmation = await confirmPendingCategory(data, signal);

                if (confirmation === null || signal.aborted) {
                    return;
                }

                originalTransform(() => ({
                    ...data,
                    inline_budget_confirmation: confirmation,
                }));
                form.processing = false;
                const options = (values[2] ?? {}) as Record<string, unknown>;
                const finish = options.onFinish as (() => void) | undefined;
                const before = options.onBefore as
                    | ((...args: unknown[]) => unknown)
                    | undefined;
                submitted = true;
                originalSubmit(values[0], values[1], {
                    ...options,
                    onBefore: (...args: unknown[]) => {
                        const result = before?.(...args);

                        if (result === false) {
                            release();
                        }

                        return result;
                    },
                    onFinish: () => {
                        release();
                        finish?.();
                    },
                });
            } catch (error) {
                submitted = false;

                if (!signal.aborted) {
                    form.setError(
                        'category_uuid',
                        userErrorMessage(
                            error instanceof Error
                                ? error.message
                                : 'Non è stato possibile confermare i budget. Riprova.',
                        ),
                    );
                }
            } finally {
                if (!submitted) {
                    release();
                }
            }
        })();
    }) as typeof form.submit;

    return form;
}) as typeof inertiaUseForm;
