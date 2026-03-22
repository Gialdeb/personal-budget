<script setup lang="ts">
import { Form, Head, Link, router, usePage } from '@inertiajs/vue3';
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
import { send } from '@/routes/verification';
import type { BreadcrumbItem } from '@/types';

type Props = {
    mustVerifyEmail: boolean;
    status?: string;
};

defineProps<Props>();

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
const impersonationConsent = ref(Boolean(user.value?.is_impersonable));
const consentUpdating = ref(false);
const profileFeedback = ref<FeedbackState | null>(null);
let feedbackTimeout: ReturnType<typeof setTimeout> | null = null;

watch(
    user,
    (currentUser) => {
        impersonationConsent.value = Boolean(currentUser?.is_impersonable);
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
    const value = checked === true;

    impersonationConsent.value = value;
    consentUpdating.value = true;

    router.patch(
        updateImpersonationConsentAction().url,
        {
            is_impersonable: value,
        },
        {
            preserveScroll: true,
            preserveState: true,
            onError: () => {
                impersonationConsent.value = Boolean(user.value?.is_impersonable);
            },
            onFinish: () => {
                consentUpdating.value = false;
            },
        },
    );
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
                        class="rounded-[1.75rem] border border-slate-200/80 bg-slate-50/80 p-5 dark:border-slate-800 dark:bg-slate-900/70"
                    >
                        <div class="flex items-start gap-4">
                            <div
                                class="flex h-12 w-12 items-center justify-center rounded-2xl bg-white text-slate-700 dark:bg-slate-950 dark:text-slate-200"
                            >
                                <LifeBuoy class="h-5 w-5" />
                            </div>

                            <div class="min-w-0 flex-1 space-y-4">
                                <div class="space-y-1">
                                    <h2 class="text-base font-semibold tracking-tight text-slate-950 dark:text-slate-50">
                                        {{ t('settings.profile.impersonation.title') }}
                                    </h2>
                                    <p class="text-sm leading-6 text-slate-600 dark:text-slate-300">
                                        {{ t('settings.profile.impersonation.description') }}
                                    </p>
                                </div>

                                <label
                                    class="flex items-start gap-3 rounded-2xl border border-slate-200/80 bg-white/80 p-4 dark:border-slate-800 dark:bg-slate-950/70"
                                >
                                    <Checkbox
                                        :checked="impersonationConsent"
                                        :disabled="consentUpdating"
                                        @update:checked="updateImpersonationConsent"
                                    />
                                    <div class="space-y-1">
                                        <p class="font-medium text-slate-950 dark:text-slate-50">
                                            {{ t('settings.profile.impersonation.label') }}
                                        </p>
                                        <p class="text-sm leading-6 text-slate-500 dark:text-slate-400">
                                            {{ t('settings.profile.impersonation.helper') }}
                                        </p>
                                        <p class="text-xs text-slate-500 dark:text-slate-400">
                                            {{ impersonationConsent ? t('settings.profile.impersonation.enabledState') : t('settings.profile.impersonation.disabledState') }}
                                        </p>
                                    </div>
                                </label>
                            </div>
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

            <DeleteUser />
        </SettingsLayout>
    </AppLayout>
</template>
