<script setup lang="ts">
import { Form, Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { CheckCircle2, CircleAlert, LifeBuoy } from 'lucide-vue-next';
import { computed, onUnmounted, ref, useTemplateRef, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { update as updateImpersonationConsentAction } from '@/actions/App/Http/Controllers/Settings/ImpersonationConsentController';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import DeleteUser from '@/components/DeleteUser.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import ProfileAvatarCropDialog from '@/components/profile/ProfileAvatarCropDialog.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { getInitials } from '@/composables/useInitials';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { edit } from '@/routes/profile';
import { update as updateLocaleAction } from '@/routes/settings/locale';
import { updateCurrency as updateCurrencyAction } from '@/routes/settings/profile';
import { update as updateNotificationPreferencesAction } from '@/routes/settings/profile/notification-preferences';
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
    notification_preferences: {
        categories: Array<{
            uuid: string;
            key: string;
            label: string;
            description: string | null;
            channels: {
                email: boolean;
                in_app: boolean;
            };
            preferences: {
                email_enabled: boolean;
                in_app_enabled: boolean;
            };
            defaults: {
                email_enabled: boolean;
                in_app_enabled: boolean;
            };
        }>;
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
const notificationPreferencesForm = useForm({
    categories: props.notification_preferences.categories.map((category) => ({
        uuid: category.uuid,
        email_enabled: category.preferences.email_enabled,
        in_app_enabled: category.preferences.in_app_enabled,
    })),
});
const consentChanged = computed(
    () => consentForm.is_impersonable !== Boolean(user.value?.is_impersonable),
);
const isBaseCurrencyLocked = computed(
    () => !props.preferences.can_update_base_currency,
);
const profileFeedback = ref<FeedbackState | null>(null);
const avatarInputRef = useTemplateRef<HTMLInputElement>('avatarInputRef');
const avatarCropOpen = ref(false);
const avatarSourceFile = ref<File | null>(null);
const avatarPreviewUrl = ref<string | null>(null);
const avatarForm = useForm({
    name: user.value?.name ?? '',
    surname: user.value?.surname ?? '',
    email: user.value?.email ?? '',
    format_locale: props.preferences.format_locale,
    avatar_image: null as File | null,
    avatar_remove: false,
});
let feedbackTimeout: ReturnType<typeof setTimeout> | null = null;
const displayedAvatar = computed(
    () => avatarPreviewUrl.value ?? user.value?.avatar ?? null,
);
const hasAvatar = computed(
    () => displayedAvatar.value !== null && displayedAvatar.value !== '',
);

watch(
    user,
    (currentUser) => {
        consentForm.defaults(
            'is_impersonable',
            Boolean(currentUser?.is_impersonable),
        );
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
        avatarForm.defaults({
            name: currentUser?.name ?? '',
            surname: currentUser?.surname ?? '',
            email: currentUser?.email ?? '',
            format_locale: props.preferences.format_locale,
            avatar_image: null,
            avatar_remove: false,
        });
        avatarForm.name = currentUser?.name ?? '';
        avatarForm.surname = currentUser?.surname ?? '';
        avatarForm.email = currentUser?.email ?? '';
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

        baseCurrencyForm.defaults(
            'base_currency_code',
            preferences.base_currency_code,
        );
        baseCurrencyForm.base_currency_code = preferences.base_currency_code;
        avatarForm.defaults('format_locale', preferences.format_locale);
        avatarForm.format_locale = preferences.format_locale;
    },
    { immediate: true, deep: true },
);

watch(
    () => props.notification_preferences,
    (notificationPreferences) => {
        const categories = notificationPreferences.categories.map(
            (category) => ({
                uuid: category.uuid,
                email_enabled: category.preferences.email_enabled,
                in_app_enabled: category.preferences.in_app_enabled,
            }),
        );

        notificationPreferencesForm.defaults('categories', categories);
        notificationPreferencesForm.categories = categories;
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

watch(avatarCropOpen, (open) => {
    if (!open) {
        avatarSourceFile.value = null;
    }
});

watch(
    () => user.value?.avatar ?? null,
    (nextAvatar, previousAvatar) => {
        if (
            avatarPreviewUrl.value &&
            nextAvatar &&
            nextAvatar !== previousAvatar
        ) {
            URL.revokeObjectURL(avatarPreviewUrl.value);
            avatarPreviewUrl.value = null;
        }
    },
);

onUnmounted(() => {
    if (feedbackTimeout) {
        clearTimeout(feedbackTimeout);
    }

    if (avatarPreviewUrl.value) {
        URL.revokeObjectURL(avatarPreviewUrl.value);
    }
});

function updateImpersonationConsent(checked: boolean | 'indeterminate'): void {
    consentForm.is_impersonable = checked === true;
}

function submitImpersonationConsent(): void {
    consentForm.patch(updateImpersonationConsentAction().url, {
        preserveScroll: true,
        onSuccess: () => {
            consentForm.defaults(
                'is_impersonable',
                consentForm.is_impersonable,
            );
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
            formatLocaleForm.defaults(
                'format_locale',
                formatLocaleForm.format_locale,
            );
        },
    });
}

function openAvatarPicker(): void {
    avatarInputRef.value?.click();
}

function handleAvatarSelection(event: Event): void {
    const target = event.target;

    if (!(target instanceof HTMLInputElement)) {
        return;
    }

    const nextFile = target.files?.[0] ?? null;

    if (!nextFile) {
        return;
    }

    avatarSourceFile.value = nextFile;
    avatarCropOpen.value = true;
    target.value = '';
}

function submitAvatarUpdate(): void {
    avatarForm.patch(ProfileController.update().url, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            avatarForm.defaults('avatar_remove', false);
            avatarForm.avatar_remove = false;
            avatarForm.avatar_image = null;

            router.reload({
                preserveScroll: true,
                only: ['auth'],
            });
        },
    });
}

function confirmAvatarCrop(payload: {
    file: File;
    previewUrl: string;
}): void {
    if (avatarPreviewUrl.value) {
        URL.revokeObjectURL(avatarPreviewUrl.value);
    }

    avatarPreviewUrl.value = payload.previewUrl;
    avatarForm.avatar_image = payload.file;
    avatarForm.avatar_remove = false;
    submitAvatarUpdate();
}

function removeAvatar(): void {
    if (avatarPreviewUrl.value) {
        URL.revokeObjectURL(avatarPreviewUrl.value);
        avatarPreviewUrl.value = null;
    }

    avatarForm.avatar_image = null;
    avatarForm.avatar_remove = true;
    submitAvatarUpdate();
}

function submitBaseCurrency(): void {
    if (isBaseCurrencyLocked.value) {
        return;
    }

    baseCurrencyForm.patch(updateCurrencyAction().url, {
        preserveScroll: true,
        onSuccess: () => {
            baseCurrencyForm.defaults(
                'base_currency_code',
                baseCurrencyForm.base_currency_code,
            );
        },
    });
}

const notificationCategories = computed(() =>
    props.notification_preferences.categories.map((category, index) => ({
        ...category,
        form: notificationPreferencesForm.categories[index],
    })),
);

const notificationPreferencesError = computed(() => {
    return (
        notificationPreferencesForm.errors.categories ||
        Object.entries(notificationPreferencesForm.errors).find(([key]) =>
            key.startsWith('categories.'),
        )?.[1] ||
        null
    );
});

function updateNotificationChannel(
    index: number,
    channel: 'email_enabled' | 'in_app_enabled',
): void {
    const currentCategory = notificationPreferencesForm.categories[index];

    if (!currentCategory) {
        return;
    }

    notificationPreferencesForm.categories[index] = {
        ...currentCategory,
        [channel]: !currentCategory[channel],
    };
}

function submitNotificationPreferences(): void {
    notificationPreferencesForm.patch(
        updateNotificationPreferencesAction().url,
        {
            preserveScroll: true,
            onSuccess: () => {
                notificationPreferencesForm.defaults(
                    'categories',
                    notificationPreferencesForm.categories,
                );
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

                <div
                    class="border-b border-slate-200/80 px-8 py-8 dark:border-slate-800"
                >
                    <div
                        class="grid gap-6 rounded-[1.75rem] border border-slate-200/80 bg-slate-50/80 p-6 lg:grid-cols-[auto_minmax(0,1fr)] dark:border-slate-800 dark:bg-slate-900/70"
                    >
                        <div class="flex justify-center lg:justify-start">
                            <Avatar
                                class="h-28 w-28 overflow-hidden rounded-[2rem] ring-1 ring-slate-200 ring-offset-4 ring-offset-white dark:ring-slate-700 dark:ring-offset-slate-950"
                            >
                                <AvatarImage
                                    v-if="hasAvatar"
                                    :src="displayedAvatar!"
                                    :alt="user.name"
                                    class="object-cover"
                                />
                                <AvatarFallback
                                    class="rounded-[2rem] bg-gradient-to-br from-sky-500 via-cyan-500 to-emerald-500 text-3xl font-semibold text-white"
                                >
                                    {{ getInitials(user.name) }}
                                </AvatarFallback>
                            </Avatar>
                        </div>

                        <div class="space-y-5">
                            <div class="space-y-2">
                                <h2
                                    class="text-lg font-semibold text-slate-950 dark:text-slate-50"
                                >
                                    {{ t('settings.profile.avatar.title') }}
                                </h2>
                                <p
                                    class="max-w-2xl text-sm leading-6 text-slate-600 dark:text-slate-300"
                                >
                                    {{
                                        t(
                                            'settings.profile.avatar.description',
                                        )
                                    }}
                                </p>
                            </div>

                            <div class="flex flex-wrap gap-3">
                                <input
                                    ref="avatarInputRef"
                                    type="file"
                                    class="sr-only"
                                    accept="image/png,image/jpeg,image/webp"
                                    @change="handleAvatarSelection"
                                />
                                <Button
                                    type="button"
                                    class="h-11 rounded-xl px-5"
                                    :disabled="avatarForm.processing"
                                    @click="openAvatarPicker"
                                >
                                    {{ t('settings.profile.avatar.upload') }}
                                </Button>
                                <Button
                                    v-if="hasAvatar"
                                    type="button"
                                    variant="outline"
                                    class="h-11 rounded-xl px-5"
                                    :disabled="avatarForm.processing"
                                    @click="removeAvatar"
                                >
                                    {{ t('settings.profile.avatar.remove') }}
                                </Button>
                            </div>

                            <div class="space-y-2">
                                <p
                                    class="text-xs leading-5 text-slate-500 dark:text-slate-400"
                                >
                                    {{ t('settings.profile.avatar.helper') }}
                                </p>
                                <InputError
                                    :message="avatarForm.errors.avatar_image"
                                />
                            </div>
                        </div>
                    </div>
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
                        <CircleAlert v-else class="h-4 w-4" />
                        <AlertTitle>{{ profileFeedback.title }}</AlertTitle>
                        <AlertDescription>{{
                            profileFeedback.message
                        }}</AlertDescription>
                    </Alert>

                    <div class="grid gap-6 md:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="name">{{
                                t('settings.profile.fields.name')
                            }}</Label>
                            <Input
                                id="name"
                                class="mt-1 block h-11 w-full rounded-xl border-slate-200 bg-white/90"
                                name="name"
                                :defaultValue="user.name"
                                required
                                autocomplete="name"
                                :placeholder="
                                    t('settings.profile.placeholders.name')
                                "
                            />
                            <InputError class="mt-2" :message="errors.name" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="surname">{{
                                t('settings.profile.fields.surname')
                            }}</Label>
                            <Input
                                id="surname"
                                class="mt-1 block h-11 w-full rounded-xl border-slate-200 bg-white/90"
                                name="surname"
                                :defaultValue="user.surname ?? ''"
                                autocomplete="family-name"
                                :placeholder="
                                    t('settings.profile.placeholders.surname')
                                "
                            />
                            <InputError
                                class="mt-2"
                                :message="errors.surname"
                            />
                        </div>

                        <div class="grid gap-2">
                            <Label for="email">{{
                                t('settings.profile.fields.email')
                            }}</Label>
                            <Input
                                id="email"
                                type="email"
                                class="mt-1 block h-11 w-full rounded-xl border-slate-200 bg-white/90"
                                name="email"
                                :defaultValue="user.email"
                                required
                                autocomplete="username"
                                :placeholder="
                                    t('settings.profile.placeholders.email')
                                "
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

            <ProfileAvatarCropDialog
                v-model:open="avatarCropOpen"
                :file="avatarSourceFile"
                @confirm="confirmAvatarCrop"
            />

            <section
                class="overflow-hidden rounded-[2rem] border border-slate-200/80 bg-white/95 shadow-[0_30px_90px_-50px_rgba(15,23,42,0.45)] backdrop-blur dark:border-slate-800 dark:bg-slate-950/85"
            >
                <div
                    class="border-b border-slate-200/70 bg-gradient-to-r from-indigo-500/10 via-sky-500/10 to-emerald-500/10 px-8 py-7 dark:border-slate-800"
                >
                    <Heading
                        variant="small"
                        :title="t('settings.profile.regional.title')"
                        :description="
                            t('settings.profile.regional.description')
                        "
                    />
                </div>

                <div class="space-y-6 px-8 py-8">
                    <form
                        class="grid gap-4 rounded-[1.75rem] border border-slate-200/80 bg-slate-50/80 p-5 dark:border-slate-800 dark:bg-slate-900/70"
                        @submit.prevent="submitLocale"
                    >
                        <div class="grid gap-2">
                            <Label for="profile-locale">{{
                                t('settings.profile.regional.locale.label')
                            }}</Label>
                            <select
                                id="profile-locale"
                                v-model="localeForm.locale"
                                class="mt-1 block h-11 w-full rounded-xl border border-slate-200 bg-white/90 px-3 text-sm text-slate-950 shadow-xs transition-colors focus-visible:border-sky-500 focus-visible:ring-2 focus-visible:ring-sky-500/30 focus-visible:outline-none dark:border-slate-800 dark:bg-slate-950/80 dark:text-slate-50"
                                name="locale"
                            >
                                <option value="" disabled>
                                    {{
                                        t(
                                            'settings.profile.regional.locale.placeholder',
                                        )
                                    }}
                                </option>
                                <option
                                    v-for="option in props.options.locales"
                                    :key="option.code"
                                    :value="option.code"
                                >
                                    {{ option.label }}
                                </option>
                            </select>
                            <p
                                class="text-sm leading-6 text-slate-500 dark:text-slate-400"
                            >
                                {{
                                    t('settings.profile.regional.locale.helper')
                                }}
                            </p>
                            <InputError
                                class="mt-1"
                                :message="pageErrors.locale"
                            />
                        </div>

                        <div class="flex items-center gap-3">
                            <Button
                                :disabled="localeForm.processing"
                                class="h-11 rounded-xl px-5"
                            >
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
                            <Label for="profile-format-locale">{{
                                t(
                                    'settings.profile.regional.formatLocale.label',
                                )
                            }}</Label>
                            <select
                                id="profile-format-locale"
                                v-model="formatLocaleForm.format_locale"
                                class="mt-1 block h-11 w-full rounded-xl border border-slate-200 bg-white/90 px-3 text-sm text-slate-950 shadow-xs transition-colors focus-visible:border-sky-500 focus-visible:ring-2 focus-visible:ring-sky-500/30 focus-visible:outline-none dark:border-slate-800 dark:bg-slate-950/80 dark:text-slate-50"
                                name="format_locale"
                            >
                                <option value="" disabled>
                                    {{
                                        t(
                                            'settings.profile.regional.formatLocale.placeholder',
                                        )
                                    }}
                                </option>
                                <option
                                    v-for="option in props.options
                                        .format_locales"
                                    :key="option.code"
                                    :value="option.code"
                                >
                                    {{ option.label }}
                                </option>
                            </select>
                            <p
                                class="text-sm leading-6 text-slate-500 dark:text-slate-400"
                            >
                                {{
                                    t(
                                        'settings.profile.regional.formatLocale.helper',
                                    )
                                }}
                            </p>
                            <InputError
                                class="mt-1"
                                :message="pageErrors.format_locale"
                            />
                        </div>

                        <div class="flex items-center gap-3">
                            <Button
                                :disabled="formatLocaleForm.processing"
                                class="h-11 rounded-xl px-5"
                            >
                                {{
                                    t(
                                        'settings.profile.regional.formatLocale.save',
                                    )
                                }}
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
                            <Label for="profile-base-currency">{{
                                t(
                                    'settings.profile.regional.baseCurrency.label',
                                )
                            }}</Label>
                            <select
                                id="profile-base-currency"
                                v-model="baseCurrencyForm.base_currency_code"
                                :disabled="
                                    isBaseCurrencyLocked ||
                                    baseCurrencyForm.processing
                                "
                                class="mt-1 block h-11 w-full rounded-xl border border-slate-200 bg-white/90 px-3 text-sm text-slate-950 shadow-xs transition-colors focus-visible:border-sky-500 focus-visible:ring-2 focus-visible:ring-sky-500/30 focus-visible:outline-none dark:border-slate-800 dark:bg-slate-950/80 dark:text-slate-50"
                                name="base_currency_code"
                            >
                                <option value="" disabled>
                                    {{
                                        t(
                                            'settings.profile.regional.baseCurrency.placeholder',
                                        )
                                    }}
                                </option>
                                <option
                                    v-for="option in props.options
                                        .base_currencies"
                                    :key="option.code"
                                    :value="option.code"
                                >
                                    {{ option.label }}
                                </option>
                            </select>
                            <p
                                class="text-sm leading-6 text-slate-500 dark:text-slate-400"
                            >
                                {{
                                    t(
                                        'settings.profile.regional.baseCurrency.helper',
                                    )
                                }}
                            </p>
                            <p
                                v-if="
                                    isBaseCurrencyLocked &&
                                    props.preferences.base_currency_lock_message
                                "
                                class="text-sm leading-6 text-amber-700 dark:text-amber-300"
                            >
                                {{
                                    props.preferences.base_currency_lock_message
                                }}
                            </p>
                            <InputError
                                class="mt-1"
                                :message="pageErrors.base_currency_code"
                            />
                        </div>

                        <div class="flex items-center gap-3">
                            <Button
                                :disabled="
                                    baseCurrencyForm.processing ||
                                    isBaseCurrencyLocked
                                "
                                class="h-11 rounded-xl px-5"
                            >
                                {{
                                    t(
                                        'settings.profile.regional.baseCurrency.save',
                                    )
                                }}
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
                    class="border-b border-slate-200/70 bg-gradient-to-r from-emerald-500/10 via-sky-500/10 to-cyan-500/10 px-8 py-7 dark:border-slate-800"
                >
                    <Heading
                        variant="small"
                        :title="t('settings.profile.notifications.title')"
                        :description="
                            t('settings.profile.notifications.description')
                        "
                    />
                </div>

                <div class="space-y-6 px-8 py-8">
                    <div
                        v-if="notificationCategories.length === 0"
                        class="rounded-[1.75rem] border border-dashed border-slate-300/90 bg-slate-50/80 px-5 py-8 text-center dark:border-slate-700 dark:bg-slate-900/60"
                    >
                        <h2
                            class="text-base font-semibold text-slate-950 dark:text-slate-50"
                        >
                            {{
                                t('settings.profile.notifications.empty.title')
                            }}
                        </h2>
                        <p
                            class="mt-2 text-sm leading-6 text-slate-500 dark:text-slate-400"
                        >
                            {{
                                t(
                                    'settings.profile.notifications.empty.description',
                                )
                            }}
                        </p>
                    </div>

                    <form
                        v-else
                        class="space-y-5"
                        @submit.prevent="submitNotificationPreferences"
                    >
                        <Alert
                            v-if="notificationPreferencesError"
                            variant="destructive"
                            class="rounded-[1.5rem]"
                        >
                            <CircleAlert class="h-4 w-4" />
                            <AlertTitle>{{
                                t('settings.profile.feedback.errorTitle')
                            }}</AlertTitle>
                            <AlertDescription>{{
                                notificationPreferencesError
                            }}</AlertDescription>
                        </Alert>

                        <article
                            v-for="(category, index) in notificationCategories"
                            :key="category.uuid"
                            class="rounded-[1.75rem] border border-slate-200/80 bg-slate-50/80 p-5 dark:border-slate-800 dark:bg-slate-900/70"
                        >
                            <div
                                class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between"
                            >
                                <div class="max-w-2xl space-y-2">
                                    <h3
                                        class="text-base font-semibold text-slate-950 dark:text-slate-50"
                                    >
                                        {{ category.label }}
                                    </h3>
                                    <p
                                        class="text-sm leading-6 text-slate-500 dark:text-slate-400"
                                    >
                                        {{ category.description }}
                                    </p>
                                </div>

                                <div
                                    class="grid min-w-full gap-3 sm:grid-cols-2 lg:min-w-[24rem]"
                                >
                                    <button
                                        v-if="category.channels.email"
                                        type="button"
                                        class="flex items-center justify-between gap-3 rounded-2xl border px-4 py-3 text-left transition-colors"
                                        :class="
                                            category.form?.email_enabled
                                                ? 'border-emerald-200 bg-emerald-50 text-emerald-900 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-100'
                                                : 'border-slate-200 bg-white text-slate-700 dark:border-slate-800 dark:bg-slate-950 dark:text-slate-200'
                                        "
                                        @click="
                                            updateNotificationChannel(
                                                index,
                                                'email_enabled',
                                            )
                                        "
                                    >
                                        <div class="space-y-1">
                                            <p class="text-sm font-medium">
                                                {{
                                                    t(
                                                        'settings.profile.notifications.channels.email',
                                                    )
                                                }}
                                            </p>
                                            <p class="text-xs text-current/75">
                                                {{
                                                    t(
                                                        'settings.profile.notifications.channelDescriptions.email',
                                                    )
                                                }}
                                            </p>
                                        </div>
                                        <span
                                            class="inline-flex h-7 w-12 items-center rounded-full px-1 transition-colors"
                                            :class="
                                                category.form?.email_enabled
                                                    ? 'bg-emerald-600'
                                                    : 'bg-slate-300 dark:bg-slate-700'
                                            "
                                        >
                                            <span
                                                class="h-5 w-5 rounded-full bg-white shadow-sm transition-transform"
                                                :class="
                                                    category.form?.email_enabled
                                                        ? 'translate-x-5'
                                                        : 'translate-x-0'
                                                "
                                            />
                                        </span>
                                    </button>

                                    <button
                                        v-if="category.channels.in_app"
                                        type="button"
                                        class="flex items-center justify-between gap-3 rounded-2xl border px-4 py-3 text-left transition-colors"
                                        :class="
                                            category.form?.in_app_enabled
                                                ? 'border-sky-200 bg-sky-50 text-sky-900 dark:border-sky-500/20 dark:bg-sky-500/10 dark:text-sky-100'
                                                : 'border-slate-200 bg-white text-slate-700 dark:border-slate-800 dark:bg-slate-950 dark:text-slate-200'
                                        "
                                        @click="
                                            updateNotificationChannel(
                                                index,
                                                'in_app_enabled',
                                            )
                                        "
                                    >
                                        <div class="space-y-1">
                                            <p class="text-sm font-medium">
                                                {{
                                                    t(
                                                        'settings.profile.notifications.channels.dashboard',
                                                    )
                                                }}
                                            </p>
                                            <p class="text-xs text-current/75">
                                                {{
                                                    t(
                                                        'settings.profile.notifications.channelDescriptions.dashboard',
                                                    )
                                                }}
                                            </p>
                                        </div>
                                        <span
                                            class="inline-flex h-7 w-12 items-center rounded-full px-1 transition-colors"
                                            :class="
                                                category.form?.in_app_enabled
                                                    ? 'bg-sky-600'
                                                    : 'bg-slate-300 dark:bg-slate-700'
                                            "
                                        >
                                            <span
                                                class="h-5 w-5 rounded-full bg-white shadow-sm transition-transform"
                                                :class="
                                                    category.form
                                                        ?.in_app_enabled
                                                        ? 'translate-x-5'
                                                        : 'translate-x-0'
                                                "
                                            />
                                        </span>
                                    </button>
                                </div>
                            </div>
                        </article>

                        <div
                            class="flex items-center gap-3 border-t border-slate-200/80 pt-2 dark:border-slate-800"
                        >
                            <Button
                                :disabled="
                                    notificationPreferencesForm.processing
                                "
                                class="h-11 rounded-xl px-5"
                            >
                                {{ t('settings.profile.notifications.save') }}
                            </Button>
                            <p
                                v-show="
                                    notificationPreferencesForm.recentlySuccessful
                                "
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
                        :description="
                            t('settings.profile.impersonation.description')
                        "
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
                                    @update:model-value="
                                        updateImpersonationConsent
                                    "
                                />
                                <div class="space-y-1">
                                    <p
                                        class="font-medium text-slate-950 dark:text-slate-50"
                                    >
                                        {{
                                            t(
                                                'settings.profile.impersonation.label',
                                            )
                                        }}
                                    </p>
                                    <p
                                        class="text-sm leading-6 text-slate-500 dark:text-slate-400"
                                    >
                                        {{
                                            t(
                                                'settings.profile.impersonation.helper',
                                            )
                                        }}
                                    </p>
                                    <p
                                        class="text-xs text-slate-500 dark:text-slate-400"
                                    >
                                        {{
                                            consentForm.is_impersonable
                                                ? t(
                                                      'settings.profile.impersonation.enabledState',
                                                  )
                                                : t(
                                                      'settings.profile.impersonation.disabledState',
                                                  )
                                        }}
                                    </p>
                                </div>
                            </label>

                            <InputError
                                :message="consentForm.errors.is_impersonable"
                            />

                            <div class="flex items-center gap-3">
                                <Button
                                    type="button"
                                    class="h-11 rounded-xl px-5"
                                    :disabled="
                                        consentForm.processing ||
                                        !consentChanged
                                    "
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
                                        v-show="
                                            !consentForm.processing &&
                                            !consentChanged &&
                                            consentForm.wasSuccessful
                                        "
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
