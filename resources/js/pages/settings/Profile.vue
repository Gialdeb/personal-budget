<script setup lang="ts">
import { Form, Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { CheckCircle2, CircleAlert, LifeBuoy } from 'lucide-vue-next';
import { computed, onUnmounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { update as updateImpersonationConsentAction } from '@/actions/App/Http/Controllers/Settings/ImpersonationConsentController';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import DeleteUser from '@/components/DeleteUser.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { edit } from '@/routes/profile';
import { update as updateLocaleAction } from '@/routes/settings/locale';
import { updateCurrency as updateCurrencyAction } from '@/routes/settings/profile';
import { send } from '@/routes/verification';
import type { BreadcrumbItem } from '@/types';

type Props = {
    mustVerifyEmail: boolean;
    status?: string;
    preferences: {
        locale: string;
        format_locale: string;
        base_currency_code: string;
        can_update_base_currency: boolean;
        base_currency_lock_message: string | null;
    };
    options: {
        locales: Array<{ code: string; label: string }>;
        format_locales: Array<{ code: string; label: string }>;
        base_currencies: Array<{ code: string; label: string }>;
    };
};

const props = defineProps<Props>();

type FeedbackState = {
    variant: 'default' | 'destructive';
    title: string;
    message: string;
};

const { t } = useI18n();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: t('settings.sections.profile'),
        href: edit(),
    },
];

const page = usePage();
const user = computed(() => page.props.auth.user);
const flash = computed(
    () => (page.props.flash ?? {}) as { success?: string | null },
);
const pageErrors = computed(
    () => (page.props.errors ?? {}) as Record<string, string | undefined>,
);
const consentForm = useForm({
    is_impersonable: Boolean(user.value?.is_impersonable),
});
const localeForm = useForm({
    locale: props.preferences.locale,
});
const formatLocaleForm = useForm({
    name: user.value?.name ?? '',
    surname: user.value?.surname ?? '',
    email: user.value?.email ?? '',
    format_locale: props.preferences.format_locale,
});
const baseCurrencyForm = useForm({
    base_currency_code: props.preferences.base_currency_code,
});
const consentChanged = computed(
    () => consentForm.is_impersonable !== Boolean(user.value?.is_impersonable),
);
const isBaseCurrencyLocked = computed(
    () => !props.preferences.can_update_base_currency,
);
const profileFeedback = ref<FeedbackState | null>(null);
let feedbackTimeout: ReturnType<typeof setTimeout> | null = null;

watch(
    user,
    (currentUser) => {
        consentForm.defaults('is_impersonable', Boolean(currentUser?.is_impersonable));
        consentForm.is_impersonable = Boolean(currentUser?.is_impersonable);
        formatLocaleForm.defaults({
            name: currentUser?.name ?? '',
            surname: currentUser?.surname ?? '',
            email: currentUser?.email ?? '',
            format_locale: props.preferences.format_locale,
        });
        formatLocaleForm.name = currentUser?.name ?? '';
        formatLocaleForm.surname = currentUser?.surname ?? '';
        formatLocaleForm.email = currentUser?.email ?? '';
    },
    { immediate: true, deep: true },
);

watch(
    () => props.preferences,
    (preferences) => {
        localeForm.defaults('locale', preferences.locale);
        localeForm.locale = preferences.locale;

        formatLocaleForm.defaults('format_locale', preferences.format_locale);
        formatLocaleForm.format_locale = preferences.format_locale;

        baseCurrencyForm.defaults('base_currency_code', preferences.base_currency_code);
        baseCurrencyForm.base_currency_code = preferences.base_currency_code;
    },
    { immediate: true, deep: true },
);

watch(
    flash,
    (currentFlash) => {
        if (currentFlash.success) {
            profileFeedback.value = {
                variant: 'default',
                title: t('settings.profile.feedback.successTitle'),
                message: currentFlash.success,
            };
        }
    },
    { immediate: true, deep: true },
);

watch(
    pageErrors,
    (errors) => {
        const message = errors.is_impersonable;

        if (!message) {
            return;
        }

        profileFeedback.value = {
            variant: 'destructive',
            title: t('settings.profile.feedback.errorTitle'),
            message,
        };
    },
    { immediate: true, deep: true },
);

watch(profileFeedback, (value) => {
    if (feedbackTimeout) {
        clearTimeout(feedbackTimeout);
        feedbackTimeout = null;
    }

    if (!value) {
        return;
    }

    feedbackTimeout = setTimeout(() => {
        profileFeedback.value = null;
        feedbackTimeout = null;
    }, 4000);
});

onUnmounted(() => {
    if (feedbackTimeout) {
        clearTimeout(feedbackTimeout);
    }
});

function updateImpersonationConsent(checked: boolean | 'indeterminate'): void {
    consentForm.is_impersonable = checked === true;
}

function submitImpersonationConsent(): void {
    consentForm.patch(updateImpersonationConsentAction().url, {
        preserveScroll: true,
        onSuccess: () => {
            consentForm.defaults('is_impersonable', consentForm.is_impersonable);
        },
        onError: () => {
            consentForm.reset();
        },
    });
}

function submitLocale(): void {
    localeForm.patch(updateLocaleAction().url, {
        preserveScroll: true,
        onSuccess: () => {
            localeForm.defaults('locale', localeForm.locale);
        },
    });
}

function submitFormatLocale(): void {
    formatLocaleForm.patch(ProfileController.update().url, {
        preserveScroll: true,
        onSuccess: () => {
            formatLocaleForm.defaults('format_locale', formatLocaleForm.format_locale);
        },
    });
}

function submitBaseCurrency(): void {
    if (isBaseCurrencyLocked.value) {
        return;
    }

    baseCurrencyForm.patch(updateCurrencyAction().url, {
        preserveScroll: true,
        onSuccess: () => {
            baseCurrencyForm.defaults('base_currency_code', baseCurrencyForm.base_currency_code);
        },
    });
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="t('settings.sections.profile')" />

        <h1 class="sr-only">{{ t('settings.sections.profile') }}</h1>

        <SettingsLayout>
            <section
                class="overflow-hidden rounded-[2rem] border border-slate-200/80 bg-white/95 shadow-[0_30px_90px_-50px_rgba(15,23,42,0.45)] backdrop-blur dark:border-slate-800 dark:bg-slate-950/85"
            >
                <div
                    class="border-b border-slate-200/70 bg-gradient-to-r from-sky-500/10 via-cyan-500/10 to-emerald-500/10 px-8 py-7 dark:border-slate-800"
                >
                    <Heading
                        variant="small"
                        :title="t('settings.profile.title')"
                        :description="t('settings.profile.description')"
                    />
                </div>

                <Form
                    v-bind="ProfileController.update.form()"
                    class="space-y-8 px-8 py-8"
                    v-slot="{ errors, processing, recentlySuccessful }"
                >
                    <input
                        type="hidden"
                        name="format_locale"
                        :value="props.preferences.format_locale"
                    />

                    <Alert
                        v-if="profileFeedback"
                        :variant="profileFeedback.variant"
                        class="rounded-[1.5rem]"
                    >
                        <CheckCircle2
                            v-if="profileFeedback.variant === 'default'"
                            class="h-4 w-4"
                        />
                        <CircleAlert
                            v-else
                            class="h-4 w-4"
                        />
                        <AlertTitle>{{ profileFeedback.title }}</AlertTitle>
                        <AlertDescription>{{ profileFeedback.message }}</AlertDescription>
                    </Alert>

                    <div class="grid gap-6 md:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="name">{{ t('settings.profile.fields.name') }}</Label>
                            <Input
                                id="name"
                                class="mt-1 block h-11 w-full rounded-xl border-slate-200 bg-white/90"
                                name="name"
                                :defaultValue="user.name"
                                required
                                autocomplete="name"
                                :placeholder="t('settings.profile.placeholders.name')"
                            />
                            <InputError class="mt-2" :message="errors.name" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="surname">{{ t('settings.profile.fields.surname') }}</Label>
                            <Input
                                id="surname"
                                class="mt-1 block h-11 w-full rounded-xl border-slate-200 bg-white/90"
                                name="surname"
                                :defaultValue="user.surname ?? ''"
                                autocomplete="family-name"
                                :placeholder="t('settings.profile.placeholders.surname')"
                            />
                            <InputError
                                class="mt-2"
                                :message="errors.surname"
                            />
                        </div>

                        <div class="grid gap-2">
                            <Label for="email">{{ t('settings.profile.fields.email') }}</Label>
                            <Input
                                id="email"
                                type="email"
                                class="mt-1 block h-11 w-full rounded-xl border-slate-200 bg-white/90"
                                name="email"
                                :defaultValue="user.email"
                                required
                                autocomplete="username"
                                :placeholder="t('settings.profile.placeholders.email')"
                            />
                            <InputError class="mt-2" :message="errors.email" />
                        </div>
                    </div>

                    <div
                        v-if="mustVerifyEmail && !user.email_verified_at"
                        class="rounded-2xl border border-amber-200 bg-amber-50/80 p-4 dark:border-amber-500/20 dark:bg-amber-500/10"
                    >
                        <p
                            class="text-sm leading-6 text-amber-900 dark:text-amber-100"
                        >
                            {{ t('settings.profile.verify.notice') }}
                            <Link
                                :href="send()"
                                as="button"
                                method="post"
                                class="font-medium underline decoration-amber-400 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current"
                            >
                                {{ t('settings.profile.verify.resend') }}
                            </Link>
                        </p>
                        <div
                            v-if="status === 'verification-link-sent'"
                            class="mt-2 text-sm font-medium text-emerald-700 dark:text-emerald-300"
                        >
                            {{ t('settings.profile.verify.sent') }}
                        </div>
                    </div>

                    <div
                        class="flex flex-col gap-3 border-t border-slate-200/80 pt-6 sm:flex-row sm:items-center dark:border-slate-800"
                    >
                        <Button
                            :disabled="processing"
                            class="h-11 rounded-xl px-5"
                            data-test="update-profile-button"
                        >
                            {{ t('settings.profile.save') }}
                        </Button>

                        <Transition
                            enter-active-class="transition ease-in-out"
                            enter-from-class="opacity-0"
                            leave-active-class="transition ease-in-out"
                            leave-to-class="opacity-0"
                        >
                            <p
                                v-show="recentlySuccessful"
                                class="text-sm text-slate-500 dark:text-slate-400"
                            >
                                {{ t('app.common.saved') }}
                            </p>
                        </Transition>
                    </div>
                </Form>
            </section>

            <section
                class="overflow-hidden rounded-[2rem] border border-slate-200/80 bg-white/95 shadow-[0_30px_90px_-50px_rgba(15,23,42,0.45)] backdrop-blur dark:border-slate-800 dark:bg-slate-950/85"
            >
                <div
                    class="border-b border-slate-200/70 bg-gradient-to-r from-indigo-500/10 via-sky-500/10 to-emerald-500/10 px-8 py-7 dark:border-slate-800"
                >
                    <Heading
                        variant="small"
                        :title="t('settings.profile.regional.title')"
                        :description="t('settings.profile.regional.description')"
                    />
                </div>

                <div class="space-y-6 px-8 py-8">
                    <form
                        class="grid gap-4 rounded-[1.75rem] border border-slate-200/80 bg-slate-50/80 p-5 dark:border-slate-800 dark:bg-slate-900/70"
                        @submit.prevent="submitLocale"
                    >
                        <div class="grid gap-2">
                            <Label for="profile-locale">{{ t('settings.profile.regional.locale.label') }}</Label>
                            <select
                                id="profile-locale"
                                v-model="localeForm.locale"
                                class="mt-1 block h-11 w-full rounded-xl border border-slate-200 bg-white/90 px-3 text-sm text-slate-950 shadow-xs transition-colors focus-visible:border-sky-500 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-500/30 dark:border-slate-800 dark:bg-slate-950/80 dark:text-slate-50"
                                name="locale"
                            >
                                <option value="" disabled>
                                    {{ t('settings.profile.regional.locale.placeholder') }}
                                </option>
                                <option
                                    v-for="option in props.options.locales"
                                    :key="option.code"
                                    :value="option.code"
                                >
                                    {{ option.label }}
                                </option>
                            </select>
                            <p class="text-sm leading-6 text-slate-500 dark:text-slate-400">
                                {{ t('settings.profile.regional.locale.helper') }}
                            </p>
                            <InputError class="mt-1" :message="pageErrors.locale" />
                        </div>

                        <div class="flex items-center gap-3">
                            <Button :disabled="localeForm.processing" class="h-11 rounded-xl px-5">
                                {{ t('settings.profile.regional.locale.save') }}
                            </Button>
                            <p
                                v-show="localeForm.recentlySuccessful"
                                class="text-sm text-slate-500 dark:text-slate-400"
                            >
                                {{ t('app.common.saved') }}
                            </p>
                        </div>
                    </form>

                    <form
                        class="grid gap-4 rounded-[1.75rem] border border-slate-200/80 bg-slate-50/80 p-5 dark:border-slate-800 dark:bg-slate-900/70"
                        @submit.prevent="submitFormatLocale"
                    >
                        <div class="grid gap-2">
                            <Label for="profile-format-locale">{{ t('settings.profile.regional.formatLocale.label') }}</Label>
                            <select
                                id="profile-format-locale"
                                v-model="formatLocaleForm.format_locale"
                                class="mt-1 block h-11 w-full rounded-xl border border-slate-200 bg-white/90 px-3 text-sm text-slate-950 shadow-xs transition-colors focus-visible:border-sky-500 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-500/30 dark:border-slate-800 dark:bg-slate-950/80 dark:text-slate-50"
                                name="format_locale"
                            >
                                <option value="" disabled>
                                    {{ t('settings.profile.regional.formatLocale.placeholder') }}
                                </option>
                                <option
                                    v-for="option in props.options.format_locales"
                                    :key="option.code"
                                    :value="option.code"
                                >
                                    {{ option.label }}
                                </option>
                            </select>
                            <p class="text-sm leading-6 text-slate-500 dark:text-slate-400">
                                {{ t('settings.profile.regional.formatLocale.helper') }}
                            </p>
                            <InputError class="mt-1" :message="pageErrors.format_locale" />
                        </div>

                        <div class="flex items-center gap-3">
                            <Button :disabled="formatLocaleForm.processing" class="h-11 rounded-xl px-5">
                                {{ t('settings.profile.regional.formatLocale.save') }}
                            </Button>
                            <p
                                v-show="formatLocaleForm.recentlySuccessful"
                                class="text-sm text-slate-500 dark:text-slate-400"
                            >
                                {{ t('app.common.saved') }}
                            </p>
                        </div>
                    </form>

                    <form
                        class="grid gap-4 rounded-[1.75rem] border border-slate-200/80 bg-slate-50/80 p-5 dark:border-slate-800 dark:bg-slate-900/70"
                        @submit.prevent="submitBaseCurrency"
                    >
                        <div class="grid gap-2">
                            <Label for="profile-base-currency">{{ t('settings.profile.regional.baseCurrency.label') }}</Label>
                            <select
                                id="profile-base-currency"
                                v-model="baseCurrencyForm.base_currency_code"
                                :disabled="isBaseCurrencyLocked || baseCurrencyForm.processing"
                                class="mt-1 block h-11 w-full rounded-xl border border-slate-200 bg-white/90 px-3 text-sm text-slate-950 shadow-xs transition-colors focus-visible:border-sky-500 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-500/30 dark:border-slate-800 dark:bg-slate-950/80 dark:text-slate-50"
                                name="base_currency_code"
                            >
                                <option value="" disabled>
                                    {{ t('settings.profile.regional.baseCurrency.placeholder') }}
                                </option>
                                <option
                                    v-for="option in props.options.base_currencies"
                                    :key="option.code"
                                    :value="option.code"
                                >
                                    {{ option.label }}
                                </option>
                            </select>
                            <p class="text-sm leading-6 text-slate-500 dark:text-slate-400">
                                {{ t('settings.profile.regional.baseCurrency.helper') }}
                            </p>
                            <p
                                v-if="isBaseCurrencyLocked && props.preferences.base_currency_lock_message"
                                class="text-sm leading-6 text-amber-700 dark:text-amber-300"
                            >
                                {{ props.preferences.base_currency_lock_message }}
                            </p>
                            <InputError class="mt-1" :message="pageErrors.base_currency_code" />
                        </div>

                        <div class="flex items-center gap-3">
                            <Button
                                :disabled="baseCurrencyForm.processing || isBaseCurrencyLocked"
                                class="h-11 rounded-xl px-5"
                            >
                                {{ t('settings.profile.regional.baseCurrency.save') }}
                            </Button>
                            <p
                                v-show="baseCurrencyForm.recentlySuccessful"
                                class="text-sm text-slate-500 dark:text-slate-400"
                            >
                                {{ t('app.common.saved') }}
                            </p>
                        </div>
                    </form>
                </div>
            </section>

            <section
                class="overflow-hidden rounded-[2rem] border border-slate-200/80 bg-white/95 shadow-[0_30px_90px_-50px_rgba(15,23,42,0.45)] backdrop-blur dark:border-slate-800 dark:bg-slate-950/85"
            >
                <div
                    class="border-b border-slate-200/70 bg-gradient-to-r from-amber-500/10 via-orange-500/10 to-sky-500/10 px-8 py-7 dark:border-slate-800"
                >
                    <Heading
                        variant="small"
                        :title="t('settings.profile.impersonation.title')"
                        :description="t('settings.profile.impersonation.description')"
                    />
                </div>

                <div class="space-y-6 px-8 py-8">
                    <div
                        class="flex items-start gap-4 rounded-[1.75rem] border border-slate-200/80 bg-slate-50/80 p-5 dark:border-slate-800 dark:bg-slate-900/70"
                    >
                        <div
                            class="flex h-12 w-12 items-center justify-center rounded-2xl bg-white text-slate-700 dark:bg-slate-950 dark:text-slate-200"
                        >
                            <LifeBuoy class="h-5 w-5" />
                        </div>

                        <div class="min-w-0 flex-1 space-y-4">
                            <label
                                class="flex items-start gap-3 rounded-2xl border border-slate-200/80 bg-white/80 p-4 dark:border-slate-800 dark:bg-slate-950/70"
                            >
                                <Checkbox
                                    :model-value="consentForm.is_impersonable"
                                    :disabled="consentForm.processing"
                                    @update:model-value="updateImpersonationConsent"
                                />
                                <div class="space-y-1">
                                    <p class="font-medium text-slate-950 dark:text-slate-50">
                                        {{ t('settings.profile.impersonation.label') }}
                                    </p>
                                    <p class="text-sm leading-6 text-slate-500 dark:text-slate-400">
                                        {{ t('settings.profile.impersonation.helper') }}
                                    </p>
                                    <p class="text-xs text-slate-500 dark:text-slate-400">
                                        {{ consentForm.is_impersonable ? t('settings.profile.impersonation.enabledState') : t('settings.profile.impersonation.disabledState') }}
                                    </p>
                                </div>
                            </label>

                            <InputError :message="consentForm.errors.is_impersonable" />

                            <div class="flex items-center gap-3">
                                <Button
                                    type="button"
                                    class="h-11 rounded-xl px-5"
                                    :disabled="consentForm.processing || !consentChanged"
                                    @click="submitImpersonationConsent"
                                >
                                    {{ t('settings.profile.save') }}
                                </Button>

                                <Transition
                                    enter-active-class="transition ease-in-out"
                                    enter-from-class="opacity-0"
                                    leave-active-class="transition ease-in-out"
                                    leave-to-class="opacity-0"
                                >
                                    <p
                                        v-show="!consentForm.processing && !consentChanged && consentForm.wasSuccessful"
                                        class="text-sm text-slate-500 dark:text-slate-400"
                                    >
                                        {{ t('app.common.saved') }}
                                    </p>
                                </Transition>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <DeleteUser />
        </SettingsLayout>
    </AppLayout>
</template>
