import { useForm as inertiaUseForm } from '@inertiajs/vue3';
import { notifyValidation } from '@/lib/notifications';

/** Preserve Inertia's overloads, state and submission callbacks. */
export const useForm: typeof inertiaUseForm = ((...args: unknown[]) => {
    const form = (
        inertiaUseForm as (
            ...args: unknown[]
        ) => ReturnType<typeof inertiaUseForm>
    )(...args);
    const setError = form.setError.bind(form);
    form.setError = ((...values: unknown[]) => {
        const result = (setError as (...args: unknown[]) => typeof form)(
            ...values,
        );
        notifyValidation(form.errors);

        return result;
    }) as typeof form.setError;

    return form;
}) as typeof inertiaUseForm;
