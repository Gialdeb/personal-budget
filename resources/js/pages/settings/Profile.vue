<script setup lang="ts">
import { Form, Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import {
    CircleAlert,
    LifeBuoy,
    LaptopMinimal,
    LogOut,
    ShieldCheck,
    Smartphone,
    Tablet,
} from 'lucide-vue-next';
import {
    computed,
    onMounted,
    onUnmounted,
    ref,
    useTemplateRef,
    watch,
} from 'vue';
import { useI18n } from 'vue-i18n';
import DeleteUser from '@/components/DeleteUser.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import ProfileAvatarCropDialog from '@/components/profile/ProfileAvatarCropDialog.vue';
import SensitiveValue from '@/components/SensitiveValue.vue';
import KofiSupportWidget from '@/components/support/KofiSupportWidget.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import AppToastStack from '@/components/ui/AppToastStack.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { getInitials } from '@/composables/useInitials';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { formatCurrency } from '@/lib/currency';
import {
    cleanupCurrentBrowserPushRegistration,
    clearMissingServiceWorkerCleanupDeadline,
    clearCurrentBrowserPushToken,
    clearPersistedCurrentPushToken,
    getOrCreatePushDeviceIdentifier,
    readFirebaseMessagingConfig,
    readCurrentPushDeviceContext,
    readPersistedCurrentPushToken,
    registerCurrentBrowserPushToken,
    requestNotificationPermission,
    shouldCleanupMissingServiceWorker,
    synchronizeCurrentBrowserPushRegistration,
    supportsWebPushRegistration,
} from '@/lib/push-notifications';
import { edit, update as updateProfileAction } from '@/routes/profile';
import { update as updateLocaleAction } from '@/routes/settings/locale';
import { updateCurrency as updateCurrencyAction } from '@/routes/settings/profile';
import { update as updateNotificationPreferencesAction } from '@/routes/settings/profile/notification-preferences';
import {
    destroy as destroyPushTokenAction,
    status as pushTokenStatusAction,
    store as storePushTokenAction,
} from '@/routes/settings/profile/push-tokens';
import { send } from '@/routes/verification';
import type { BreadcrumbItem } from '@/types';
import { update as updateImpersonationConsentAction } from '@/actions/App/Http/Controllers/Settings/ImpersonationConsentController.ts';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController.ts';

type Props = {
    mustVerifyEmail: boolean;
    status?: string;
    preferences: {
        locale: string;
        format_locale: string;
        number_thousands_separator: string | null;
        number_decimal_separator: string | null;
        date_format: string | null;
        base_currency_code: string;
        can_update_base_currency: boolean;
        base_currency_lock_message: string | null;
    };
    notification_preferences: {
        push: {
            visible: boolean;
            enabled: boolean;
            active_tokens_count: number;
        };
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
    active_sessions: {
        current_session_id: string;
        items: Array<{
            id: string;
            ip_address: string;
            user_agent: string | null;
            browser: string;
            operating_system: string;
            device_type: string;
            device_label: string;
            last_activity_at: string;
            last_activity_human: string;
            is_current: boolean;
            is_revocable: boolean;
        }>;
    };
    support: {
        support_state: string;
        last_donation_at: string | null;
        next_reminder_at: string | null;
        donations_count: number;
        history: Array<{
            id: number;
            provider: string;
            amount: string;
            currency: string;
            status: string;
            paid_at: string | null;
        }>;
        show_kofi_widget: boolean;
        support_prompt_variant:
            | 'first_support'
            | 'renew_support'
            | 'support_again'
            | null;
        kofi_widget: {
            script_url: string;
            page_id: string;
            button_color: string;
        };
    };
    options: {
        locales: Array<{ code: string; label: string }>;
        format_locales: Array<{ code: string; label: string }>;
        number_thousands_separators: Array<{ key: string; value: string }>;
        number_decimal_separators: Array<{ key: string; value: string }>;
        date_formats: Array<{ value: string }>;
        base_currencies: Array<{
            code: string;
            name: string;
            symbol: string;
            label: string;
        }>;
    };
};

const props = defineProps<Props>();

type FeedbackState = {
    variant: 'default' | 'destructive';
    title: string;
    message: string;
};

type PushWebDeviceState =
    | 'unsupported'
    | 'misconfigured'
    | 'disabled'
    | 'enabling'
    | 'enabled'
    | 'denied';

const { t } = useI18n();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: t('settings.sections.profile'),
        href: edit(),
    },
];

const page = usePage();
const user = computed(() => page.props.auth.user);
const pushNotificationsFeatureEnabled = computed(
    () => page.props.features?.push_notifications_enabled === true,
);
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
    number_thousands_separator: props.preferences.number_thousands_separator,
    number_decimal_separator: props.preferences.number_decimal_separator,
    date_format: props.preferences.date_format,
});
const baseCurrencyForm = useForm({
    base_currency_code: props.preferences.base_currency_code,
});
const notificationPreferencesForm = useForm({
    push: {
        enabled: props.notification_preferences.push.enabled,
    },
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
const selectedBaseCurrencyCode = computed(
    () =>
        baseCurrencyForm.base_currency_code ||
        props.preferences.base_currency_code,
);
const thousandsSeparatorOptions = computed(() =>
    props.options.number_thousands_separators.map((option) => ({
        ...option,
        label: t(
            `settings.profile.regional.formatLocale.separators.${option.key}`,
        ),
        example: formatNumberWithSeparators(
            1234567.89,
            separatorCharacter(option.value),
            safeDecimalSeparator(formatLocaleForm.number_decimal_separator),
        ),
        disabled:
            option.value ===
            safeDecimalSeparator(formatLocaleForm.number_decimal_separator),
    })),
);
const decimalSeparatorOptions = computed(() =>
    props.options.number_decimal_separators.map((option) => ({
        ...option,
        label: t(
            `settings.profile.regional.formatLocale.separators.${option.key}`,
        ),
        example: formatNumberWithSeparators(
            1234.56,
            separatorCharacter(
                safeThousandsSeparator(
                    formatLocaleForm.number_thousands_separator,
                ),
            ),
            option.value,
        ),
        disabled:
            option.value ===
            safeThousandsSeparator(formatLocaleForm.number_thousands_separator),
    })),
);
const dateFormatOptions = computed(() =>
    props.options.date_formats.map((option) => ({
        ...option,
        example: formatDatePattern(new Date(2026, 3, 11), option.value),
    })),
);
const formatPreview = computed(() => ({
    number: formatNumberWithSeparators(
        1234.56,
        separatorCharacter(
            safeThousandsSeparator(formatLocaleForm.number_thousands_separator),
        ),
        safeDecimalSeparator(formatLocaleForm.number_decimal_separator),
    ),
    amount: formatAmountPreview(
        1234.56,
        selectedBaseCurrencyCode.value,
        safeThousandsSeparator(formatLocaleForm.number_thousands_separator),
        safeDecimalSeparator(formatLocaleForm.number_decimal_separator),
    ),
    date: formatDatePattern(
        new Date(2026, 3, 11),
        safeDateFormat(formatLocaleForm.date_format),
    ),
}));
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
const revokeSessionForm = useForm({});
const revokeOtherSessionsForm = useForm({});
let feedbackTimeout: ReturnType<typeof setTimeout> | null = null;
const pushWebFeedback = ref<FeedbackState | null>(null);
const pushWebSubmitting = ref(false);
const pushWebDeviceState = ref<PushWebDeviceState>('disabled');
const pushWebActiveTokensCount = ref(
    props.notification_preferences.push.active_tokens_count,
);
const pushWebInitialized = ref(false);
const displayedAvatar = computed(
    () => avatarPreviewUrl.value ?? user.value?.avatar ?? null,
);
const hasAvatar = computed(
    () => displayedAvatar.value !== null && displayedAvatar.value !== '',
);
const activeSessions = computed(() => props.active_sessions.items);
const supportHistory = computed(() => props.support.history);
const shouldShowKofiPrompt = computed(() => props.support.show_kofi_widget);
const supportPromptCopy = computed(() => {
    const variant = props.support.support_prompt_variant;

    if (!variant) {
        return null;
    }

    return {
        eyebrow: t('settings.profile.support.prompt.eyebrow'),
        title: t(`settings.profile.support.prompt.variants.${variant}.title`),
        description: t(
            `settings.profile.support.prompt.variants.${variant}.description`,
        ),
        note: t('settings.profile.support.prompt.note'),
        button: t(`dashboard.supportPrompt.variants.${variant}.button`),
    };
});

function formatNumberWithSeparators(
    value: number,
    thousandsSeparator: string,
    decimalSeparator: string,
    fractionDigits = 2,
): string {
    const safeThousandsSeparator =
        thousandsSeparator === ' ' ? '\u00A0' : thousandsSeparator;
    const normalizedValue = Math.abs(value).toFixed(fractionDigits);
    const [integerPart = '0', decimalPart = ''] = normalizedValue.split('.');
    const groupedIntegerPart = integerPart.replace(
        /\B(?=(\d{3})+(?!\d))/g,
        safeThousandsSeparator,
    );
    const sign = value < 0 ? '-' : '';

    if (fractionDigits === 0) {
        return `${sign}${groupedIntegerPart}`;
    }

    return `${sign}${groupedIntegerPart}${decimalSeparator}${decimalPart}`;
}

function separatorCharacter(value: string): string {
    return value === 'space' ? ' ' : value;
}

function safeThousandsSeparator(value: string | null | undefined): string {
    return value === '.' || value === ',' || value === 'space' ? value : '.';
}

function safeDecimalSeparator(value: string | null | undefined): string {
    return value === ',' || value === '.' ? value : ',';
}

function safeDateFormat(value: string | null | undefined): string {
    return typeof value === 'string' && value !== '' ? value : 'D MMM YYYY';
}

function formatAmountPreview(
    value: number,
    currencyCode: string,
    thousandsSeparator: string,
    decimalSeparator: string,
): string {
    const currencyMeta = props.options.base_currencies.find(
        (currency) => currency.code === currencyCode,
    );
    const fractionDigits = currencyCode === 'JPY' ? 0 : 2;
    const formattedAmount = formatNumberWithSeparators(
        value,
        separatorCharacter(thousandsSeparator),
        decimalSeparator,
        fractionDigits,
    );
    const indicator = currencyMeta?.symbol ?? currencyCode;

    if (currencyCode === 'JPY') {
        return `${currencyCode} ${formattedAmount}`;
    }

    return `${indicator} ${formattedAmount}`;
}

function formatDatePattern(
    date: Date,
    pattern: string | null | undefined,
): string {
    const safePattern = safeDateFormat(pattern);
    const day = String(date.getDate());
    const paddedDay = day.padStart(2, '0');
    const month = String(date.getMonth() + 1);
    const paddedMonth = month.padStart(2, '0');
    const year = String(date.getFullYear());
    const shortMonth = new Intl.DateTimeFormat(props.preferences.locale, {
        month: 'short',
    }).format(date);

    return safePattern
        .replace('YYYY', year)
        .replace('MMM', shortMonth)
        .replace('DD', paddedDay)
        .replace('MM', paddedMonth)
        .replace('D', day);
}
const otherSessionsCount = computed(
    () => activeSessions.value.filter((session) => !session.is_current).length,
);
const revokeDialogSession = ref<
    Props['active_sessions']['items'][number] | null
>(null);
const revokeOthersDialogOpen = ref(false);

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
            number_thousands_separator:
                props.preferences.number_thousands_separator,
            number_decimal_separator:
                props.preferences.number_decimal_separator,
            date_format: props.preferences.date_format,
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
        formatLocaleForm.defaults(
            'number_thousands_separator',
            preferences.number_thousands_separator,
        );
        formatLocaleForm.number_thousands_separator =
            preferences.number_thousands_separator;
        formatLocaleForm.defaults(
            'number_decimal_separator',
            preferences.number_decimal_separator,
        );
        formatLocaleForm.number_decimal_separator =
            preferences.number_decimal_separator;
        formatLocaleForm.defaults('date_format', preferences.date_format);
        formatLocaleForm.date_format = preferences.date_format;

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

        notificationPreferencesForm.defaults('push', {
            enabled: notificationPreferences.push.enabled,
        });
        notificationPreferencesForm.push = {
            enabled: notificationPreferences.push.enabled,
        };
        pushWebActiveTokensCount.value =
            notificationPreferences.push.active_tokens_count;
        notificationPreferencesForm.defaults('categories', categories);
        notificationPreferencesForm.categories = categories;
    },
    { immediate: true, deep: true },
);

onMounted(() => {
    void initializePushWebDeviceState();
});

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
                preserveUrl: true,
                only: ['auth'],
            });
        },
    });
}

function confirmAvatarCrop(payload: { file: File; previewUrl: string }): void {
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
                    'push',
                    notificationPreferencesForm.push,
                );
                notificationPreferencesForm.defaults(
                    'categories',
                    notificationPreferencesForm.categories,
                );
            },
        },
    );
}

function readCsrfToken(): string {
    return (
        document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute('content') ?? ''
    );
}

type PushTokenResponse = {
    message?: string;
    push?: {
        enabled: boolean;
        active_tokens_count: number;
        global_enabled?: boolean;
        current_device_enabled?: boolean;
    };
    errors?: Record<string, string[] | string>;
};

async function submitPushTokenRequest(
    url: string,
    method: 'POST' | 'DELETE',
    payload: Record<string, unknown>,
): Promise<PushTokenResponse> {
    const response = await fetch(url, {
        method,
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': readCsrfToken(),
        },
        body: JSON.stringify(payload),
    });

    const contentType = response.headers.get('content-type') ?? '';
    const data = contentType.includes('application/json')
        ? ((await response.json()) as PushTokenResponse)
        : null;

    if (!response.ok) {
        const firstError = data?.errors ? Object.values(data.errors)[0] : null;
        const errorMessage = Array.isArray(firstError)
            ? firstError[0]
            : firstError;

        throw new Error(
            data?.message || errorMessage || `push-request-${response.status}`,
        );
    }

    return data ?? {};
}

function applyPushPreferenceState(
    enabled: boolean,
    activeTokensCount: number,
): void {
    notificationPreferencesForm.push = {
        enabled,
    };
    notificationPreferencesForm.defaults('push', {
        enabled,
    });
    pushWebActiveTokensCount.value = activeTokensCount;
}

const isPushWebDeviceEnabled = computed(
    () => pushWebDeviceState.value === 'enabled',
);

const isPushWebToggleDisabled = computed(
    () =>
        pushWebSubmitting.value ||
        pushWebDeviceState.value === 'unsupported' ||
        pushWebDeviceState.value === 'misconfigured',
);

const pushWebDeviceStateMessage = computed(() => {
    if (pushWebDeviceState.value === 'unsupported') {
        return t('settings.profile.notifications.push.status.unsupported');
    }

    if (pushWebDeviceState.value === 'misconfigured') {
        return t('settings.profile.notifications.push.status.configMissing');
    }

    if (pushWebDeviceState.value === 'denied') {
        return t('settings.profile.notifications.push.status.permissionDenied');
    }

    if (pushWebDeviceState.value === 'enabling') {
        return t('settings.profile.notifications.push.status.processing');
    }

    if (pushWebDeviceState.value === 'enabled') {
        return t('settings.profile.notifications.push.enabledState');
    }

    return t('settings.profile.notifications.push.disabledState');
});

function setPushWebFeedback(
    variant: FeedbackState['variant'],
    message: string,
): void {
    pushWebFeedback.value = {
        variant,
        title:
            variant === 'default'
                ? t('settings.profile.feedback.successTitle')
                : t('settings.profile.feedback.errorTitle'),
        message,
    };
}

function translatePushWebError(error: unknown, fallbackKey: string): string {
    const message = error instanceof Error ? error.message : '';

    if (message === 'firebase-config-missing') {
        return t('settings.profile.notifications.push.status.configMissing');
    }

    if (message === 'push-unsupported') {
        return t('settings.profile.notifications.push.status.unsupported');
    }

    if (message === 'push-permission-denied') {
        return t('settings.profile.notifications.push.status.permissionDenied');
    }

    return t(fallbackKey);
}

async function fetchCurrentPushDeviceRegistrationStatus(
    token: string,
): Promise<PushTokenResponse> {
    return submitPushTokenRequest(pushTokenStatusAction().url, 'POST', {
        token,
        platform: 'web',
        device_identifier: getOrCreatePushDeviceIdentifier(),
    });
}

async function initializePushWebDeviceState(): Promise<void> {
    if (!pushNotificationsFeatureEnabled.value) {
        pushWebDeviceState.value = 'misconfigured';
        pushWebInitialized.value = true;

        return;
    }

    const deviceContext = await readCurrentPushDeviceContext();

    if (!deviceContext.hasSupportedBrowser) {
        pushWebDeviceState.value = 'unsupported';
        pushWebInitialized.value = true;

        return;
    }

    if (!deviceContext.hasValidConfig) {
        pushWebDeviceState.value = 'misconfigured';
        pushWebInitialized.value = true;

        return;
    }

    if (deviceContext.permission === 'denied') {
        await cleanupCurrentBrowserPushRegistration({
            destroyUrl: destroyPushTokenAction().url,
            reason: 'permission_revoked',
        });
        pushWebDeviceState.value = 'denied';
        pushWebInitialized.value = true;

        return;
    }

    if (!deviceContext.hasExplicitServiceWorkerRegistration) {
        if (deviceContext.hasPendingServiceWorkerRegistration) {
            clearMissingServiceWorkerCleanupDeadline();
            pushWebDeviceState.value = 'disabled';
            pushWebInitialized.value = true;

            return;
        }

        if (!shouldCleanupMissingServiceWorker()) {
            pushWebDeviceState.value = 'disabled';
            pushWebInitialized.value = true;

            return;
        }

        await cleanupCurrentBrowserPushRegistration({
            destroyUrl: destroyPushTokenAction().url,
            reason: 'service_worker_missing',
        });

        pushWebDeviceState.value = 'disabled';
        pushWebInitialized.value = true;

        return;
    }

    try {
        if (deviceContext.permission === 'granted') {
            await synchronizeCurrentBrowserPushRegistration({
                isAuthenticated: page.props.auth?.user !== null,
                featureEnabled: pushNotificationsFeatureEnabled.value,
                locale: props.preferences.locale,
                storeUrl: storePushTokenAction().url,
                destroyUrl: destroyPushTokenAction().url,
            });
        }

        clearMissingServiceWorkerCleanupDeadline();

        const currentToken = readPersistedCurrentPushToken();

        if (!currentToken) {
            await cleanupCurrentBrowserPushRegistration({
                destroyUrl: destroyPushTokenAction().url,
                reason: 'browser_token_missing',
            });
            pushWebDeviceState.value = 'disabled';
            pushWebInitialized.value = true;

            return;
        }

        const payload =
            await fetchCurrentPushDeviceRegistrationStatus(currentToken);

        applyPushPreferenceState(
            payload.push?.global_enabled ??
                props.notification_preferences.push.enabled,
            payload.push?.active_tokens_count ??
                props.notification_preferences.push.active_tokens_count,
        );

        pushWebDeviceState.value = payload.push?.current_device_enabled
            ? 'enabled'
            : 'disabled';

        if (!payload.push?.current_device_enabled) {
            await cleanupCurrentBrowserPushRegistration({
                destroyUrl: destroyPushTokenAction().url,
                token: currentToken,
                reason: 'backend_device_inactive',
            });
        }
    } catch {
        await cleanupCurrentBrowserPushRegistration({
            destroyUrl: destroyPushTokenAction().url,
            reason: 'status_check_failed',
        });
        pushWebDeviceState.value = 'disabled';
    } finally {
        pushWebInitialized.value = true;
    }
}

async function togglePushWebPreference(): Promise<void> {
    if (pushWebSubmitting.value) {
        return;
    }

    const nextEnabled = !isPushWebDeviceEnabled.value;

    pushWebSubmitting.value = true;
    pushWebDeviceState.value = nextEnabled ? 'enabling' : 'disabled';
    pushWebFeedback.value = {
        variant: 'default',
        title: t('settings.profile.feedback.successTitle'),
        message: t('settings.profile.notifications.push.status.processing'),
    };

    try {
        let validationError: string | null = null;

        if (!pushNotificationsFeatureEnabled.value) {
            validationError = 'firebase-config-missing';
        }

        if (nextEnabled) {
            if (
                validationError === null &&
                readFirebaseMessagingConfig() === null
            ) {
                validationError = 'firebase-config-missing';
            }

            const supported =
                validationError === null
                    ? await supportsWebPushRegistration()
                    : false;

            if (validationError === null && !supported) {
                validationError = 'push-unsupported';
            }

            const permission =
                validationError === null
                    ? await requestNotificationPermission()
                    : 'default';

            if (validationError === null && permission !== 'granted') {
                validationError = 'push-permission-denied';
            }

            if (validationError !== null) {
                const error = new Error(validationError);

                applyPushPreferenceState(
                    notificationPreferencesForm.push.enabled,
                    props.notification_preferences.push.active_tokens_count,
                );
                pushWebDeviceState.value =
                    validationError === 'push-permission-denied'
                        ? 'denied'
                        : 'disabled';
                setPushWebFeedback(
                    'destructive',
                    translatePushWebError(
                        error,
                        'settings.profile.notifications.push.status.registrationFailed',
                    ),
                );

                return;
            }

            const token = await registerCurrentBrowserPushToken();
            const payload = await submitPushTokenRequest(
                storePushTokenAction().url,
                'POST',
                {
                    token,
                    platform: 'web',
                    locale: props.preferences.locale,
                    device_identifier: getOrCreatePushDeviceIdentifier(),
                },
            );

            applyPushPreferenceState(
                payload.push?.global_enabled ?? payload.push?.enabled ?? true,
                payload.push?.active_tokens_count ??
                    pushWebActiveTokensCount.value,
            );
            pushWebDeviceState.value = 'enabled';
            setPushWebFeedback(
                'default',
                payload.message ??
                    t(
                        'settings.profile.notifications.push.status.enabledSuccess',
                    ),
            );

            return;
        }

        const payload = await submitPushTokenRequest(
            destroyPushTokenAction().url,
            'DELETE',
            {
                token: readPersistedCurrentPushToken(),
                platform: 'web',
                device_identifier: getOrCreatePushDeviceIdentifier(),
            },
        );

        applyPushPreferenceState(
            payload.push?.global_enabled ??
                payload.push?.enabled ??
                notificationPreferencesForm.push.enabled,
            payload.push?.active_tokens_count ?? 0,
        );
        pushWebDeviceState.value = 'disabled';

        try {
            await clearCurrentBrowserPushToken();
        } catch {
            clearPersistedCurrentPushToken();
        }

        setPushWebFeedback(
            'default',
            payload.message ??
                t('settings.profile.notifications.push.status.disabledSuccess'),
        );
    } catch (error) {
        applyPushPreferenceState(
            notificationPreferencesForm.push.enabled,
            props.notification_preferences.push.active_tokens_count,
        );
        pushWebDeviceState.value =
            error instanceof Error && error.message === 'push-permission-denied'
                ? 'denied'
                : 'disabled';
        setPushWebFeedback(
            'destructive',
            translatePushWebError(
                error,
                nextEnabled
                    ? 'settings.profile.notifications.push.status.registrationFailed'
                    : 'settings.profile.notifications.push.status.disableFailed',
            ),
        );
    } finally {
        pushWebSubmitting.value = false;
        pushWebInitialized.value = true;
    }
}

function iconForDeviceType(deviceType: string) {
    if (deviceType === 'Mobile') {
        return Smartphone;
    }

    if (deviceType === 'Tablet') {
        return Tablet;
    }

    return LaptopMinimal;
}

function openRevokeSessionDialog(
    session: Props['active_sessions']['items'][number],
): void {
    revokeDialogSession.value = session;
}

function closeRevokeSessionDialog(): void {
    revokeDialogSession.value = null;
}

function submitSessionRevocation(): void {
    if (!revokeDialogSession.value) {
        return;
    }

    revokeSessionForm.delete(
        ProfileController.destroySession(revokeDialogSession.value.id).url,
        {
            preserveScroll: true,
            onSuccess: () => {
                closeRevokeSessionDialog();
            },
        },
    );
}

function submitRevokeOtherSessions(): void {
    revokeOtherSessionsForm.delete(
        ProfileController.destroyOtherSessions().url,
        {
            preserveScroll: true,
            onSuccess: () => {
                revokeOthersDialogOpen.value = false;
            },
        },
    );
}

function formatSupportDate(value: string | null): string {
    if (!value) {
        return t('settings.profile.support.empty.value');
    }

    return new Intl.DateTimeFormat(undefined, {
        year: 'numeric',
        month: 'short',
        day: '2-digit',
    }).format(new Date(value));
}

function formatSupportAmount(amount: string, currency: string): string {
    return formatCurrency(
        Number(amount),
        currency,
        props.preferences.format_locale,
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
                                        t('settings.profile.avatar.description')
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
                    :action="updateProfileAction().url"
                    method="patch"
                    class="space-y-8 px-8 py-8"
                    v-slot="{ errors, processing, recentlySuccessful }"
                >
                    <AppToastStack
                        :items="[
                            profileFeedback,
                            pushWebInitialized ? pushWebFeedback : null,
                        ]"
                    />
                    <input
                        type="hidden"
                        name="format_locale"
                        :value="props.preferences.format_locale"
                    />

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
                        <fieldset class="grid gap-5">
                            <legend
                                class="text-sm font-medium text-slate-950 dark:text-slate-50"
                            >
                                {{
                                    t(
                                        'settings.profile.regional.formatLocale.label',
                                    )
                                }}
                            </legend>
                            <p
                                class="text-sm leading-6 text-slate-500 dark:text-slate-400"
                            >
                                {{
                                    t(
                                        'settings.profile.regional.formatLocale.helper',
                                    )
                                }}
                            </p>
                            <input
                                v-model="formatLocaleForm.format_locale"
                                type="hidden"
                                name="format_locale"
                            />

                            <div class="grid gap-4 lg:grid-cols-2">
                                <div class="grid gap-3">
                                    <p
                                        class="text-xs font-semibold tracking-[0.18em] text-slate-500 uppercase dark:text-slate-400"
                                    >
                                        {{
                                            t(
                                                'settings.profile.regional.formatLocale.thousandsSeparator',
                                            )
                                        }}
                                    </p>
                                    <div class="flex flex-wrap gap-2">
                                        <label
                                            v-for="option in thousandsSeparatorOptions"
                                            :key="option.key"
                                            class="cursor-pointer rounded-2xl border px-4 py-3 text-sm transition-colors"
                                            :class="
                                                formatLocaleForm.number_thousands_separator ===
                                                option.value
                                                    ? 'border-sky-500 bg-sky-50 text-sky-900 ring-2 ring-sky-500/20 dark:border-sky-400 dark:bg-sky-950/40 dark:text-sky-100'
                                                    : option.disabled
                                                      ? 'cursor-not-allowed border-slate-200/80 bg-slate-100/80 text-slate-400 dark:border-slate-800 dark:bg-slate-900/40 dark:text-slate-600'
                                                      : 'border-slate-200/80 bg-white/85 text-slate-700 hover:border-sky-300 hover:bg-sky-50/70 dark:border-slate-800 dark:bg-slate-950/60 dark:text-slate-200 dark:hover:border-sky-700 dark:hover:bg-sky-950/30'
                                            "
                                        >
                                            <input
                                                v-model="
                                                    formatLocaleForm.number_thousands_separator
                                                "
                                                class="sr-only"
                                                type="radio"
                                                name="number_thousands_separator"
                                                :value="option.value"
                                                :disabled="option.disabled"
                                            />
                                            <span class="block font-semibold">
                                                {{ option.label }}
                                            </span>
                                            <span
                                                class="mt-1 block text-xs opacity-75"
                                            >
                                                {{ option.example }}
                                            </span>
                                        </label>
                                    </div>
                                </div>

                                <div class="grid gap-3">
                                    <p
                                        class="text-xs font-semibold tracking-[0.18em] text-slate-500 uppercase dark:text-slate-400"
                                    >
                                        {{
                                            t(
                                                'settings.profile.regional.formatLocale.decimalSeparator',
                                            )
                                        }}
                                    </p>
                                    <div class="flex flex-wrap gap-2">
                                        <label
                                            v-for="option in decimalSeparatorOptions"
                                            :key="option.key"
                                            class="cursor-pointer rounded-2xl border px-4 py-3 text-sm transition-colors"
                                            :class="
                                                formatLocaleForm.number_decimal_separator ===
                                                option.value
                                                    ? 'border-sky-500 bg-sky-50 text-sky-900 ring-2 ring-sky-500/20 dark:border-sky-400 dark:bg-sky-950/40 dark:text-sky-100'
                                                    : option.disabled
                                                      ? 'cursor-not-allowed border-slate-200/80 bg-slate-100/80 text-slate-400 dark:border-slate-800 dark:bg-slate-900/40 dark:text-slate-600'
                                                      : 'border-slate-200/80 bg-white/85 text-slate-700 hover:border-sky-300 hover:bg-sky-50/70 dark:border-slate-800 dark:bg-slate-950/60 dark:text-slate-200 dark:hover:border-sky-700 dark:hover:bg-sky-950/30'
                                            "
                                        >
                                            <input
                                                v-model="
                                                    formatLocaleForm.number_decimal_separator
                                                "
                                                class="sr-only"
                                                type="radio"
                                                name="number_decimal_separator"
                                                :value="option.value"
                                                :disabled="option.disabled"
                                            />
                                            <span class="block font-semibold">
                                                {{ option.label }}
                                            </span>
                                            <span
                                                class="mt-1 block text-xs opacity-75"
                                            >
                                                {{ option.example }}
                                            </span>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div class="grid gap-3">
                                <p
                                    class="text-xs font-semibold tracking-[0.18em] text-slate-500 uppercase dark:text-slate-400"
                                >
                                    {{
                                        t(
                                            'settings.profile.regional.formatLocale.dateFormat',
                                        )
                                    }}
                                </p>
                                <div class="grid gap-2 sm:grid-cols-2">
                                    <label
                                        v-for="option in dateFormatOptions"
                                        :key="option.value"
                                        class="cursor-pointer rounded-2xl border bg-white/85 p-4 transition-colors hover:border-sky-300 hover:bg-sky-50/70 dark:bg-slate-950/60 dark:hover:border-sky-700 dark:hover:bg-sky-950/30"
                                        :class="
                                            formatLocaleForm.date_format ===
                                            option.value
                                                ? 'border-sky-500 ring-2 ring-sky-500/20 dark:border-sky-400'
                                                : 'border-slate-200/80 dark:border-slate-800'
                                        "
                                    >
                                        <input
                                            v-model="
                                                formatLocaleForm.date_format
                                            "
                                            class="sr-only"
                                            type="radio"
                                            name="date_format"
                                            :value="option.value"
                                        />
                                        <span
                                            class="block text-sm font-semibold text-slate-950 dark:text-slate-50"
                                        >
                                            {{ option.value }}
                                        </span>
                                        <span
                                            class="mt-1 block text-sm text-slate-500 dark:text-slate-400"
                                        >
                                            {{ option.example }}
                                        </span>
                                    </label>
                                </div>
                            </div>

                            <div
                                class="rounded-[1.35rem] border border-sky-100 bg-white/80 p-4 shadow-xs dark:border-sky-950/60 dark:bg-slate-950/50"
                            >
                                <p
                                    class="text-xs font-semibold tracking-[0.2em] text-sky-700 uppercase dark:text-sky-300"
                                >
                                    {{
                                        t(
                                            'settings.profile.regional.formatLocale.preview.title',
                                        )
                                    }}
                                </p>
                                <dl class="mt-4 grid gap-3 sm:grid-cols-3">
                                    <div
                                        class="rounded-2xl border border-slate-200/80 bg-slate-50/80 p-3 dark:border-slate-800 dark:bg-slate-900/70"
                                    >
                                        <dt
                                            class="text-xs font-medium text-slate-500 dark:text-slate-400"
                                        >
                                            {{
                                                t(
                                                    'settings.profile.regional.formatLocale.preview.number',
                                                )
                                            }}
                                        </dt>
                                        <dd
                                            class="mt-1 text-sm font-semibold text-slate-950 dark:text-slate-50"
                                        >
                                            {{ formatPreview.number }}
                                        </dd>
                                    </div>
                                    <div
                                        class="rounded-2xl border border-slate-200/80 bg-slate-50/80 p-3 dark:border-slate-800 dark:bg-slate-900/70"
                                    >
                                        <dt
                                            class="text-xs font-medium text-slate-500 dark:text-slate-400"
                                        >
                                            {{
                                                t(
                                                    'settings.profile.regional.formatLocale.preview.amount',
                                                )
                                            }}
                                        </dt>
                                        <dd
                                            class="mt-1 text-sm font-semibold text-slate-950 dark:text-slate-50"
                                        >
                                            <SensitiveValue
                                                :value="formatPreview.amount"
                                            />
                                        </dd>
                                    </div>
                                    <div
                                        class="rounded-2xl border border-slate-200/80 bg-slate-50/80 p-3 dark:border-slate-800 dark:bg-slate-900/70"
                                    >
                                        <dt
                                            class="text-xs font-medium text-slate-500 dark:text-slate-400"
                                        >
                                            {{
                                                t(
                                                    'settings.profile.regional.formatLocale.preview.date',
                                                )
                                            }}
                                        </dt>
                                        <dd
                                            class="mt-1 text-sm font-semibold text-slate-950 dark:text-slate-50"
                                        >
                                            {{ formatPreview.date }}
                                        </dd>
                                    </div>
                                </dl>
                            </div>

                            <InputError
                                class="mt-1"
                                :message="pageErrors.format_locale"
                            />
                            <InputError
                                class="mt-1"
                                :message="pageErrors.number_thousands_separator"
                            />
                            <InputError
                                class="mt-1"
                                :message="pageErrors.number_decimal_separator"
                            />
                            <InputError
                                class="mt-1"
                                :message="pageErrors.date_format"
                            />
                        </fieldset>

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
                id="support"
                class="overflow-hidden rounded-[2rem] border border-slate-200/80 bg-white/95 shadow-[0_30px_90px_-50px_rgba(15,23,42,0.45)] backdrop-blur dark:border-slate-800 dark:bg-slate-950/85"
            >
                <div
                    class="border-b border-slate-200/70 bg-gradient-to-r from-rose-500/10 via-amber-500/10 to-sky-500/10 px-8 py-7 dark:border-slate-800"
                >
                    <Heading
                        variant="small"
                        :title="t('settings.profile.support.title')"
                        :description="t('settings.profile.support.description')"
                    />
                </div>

                <div class="space-y-6 px-8 py-8">
                    <div class="grid gap-4 md:grid-cols-2">
                        <div
                            class="rounded-[1.5rem] border border-slate-200/80 bg-slate-50/80 p-5 dark:border-slate-800 dark:bg-slate-900/70"
                        >
                            <p
                                class="text-xs font-medium tracking-[0.16em] text-slate-400 uppercase"
                            >
                                {{
                                    t('settings.profile.support.summary.state')
                                }}
                            </p>
                            <p
                                class="mt-3 text-base font-semibold text-slate-950 dark:text-slate-50"
                            >
                                {{
                                    t(
                                        `settings.profile.support.states.${props.support.support_state}`,
                                    )
                                }}
                            </p>
                        </div>
                        <div
                            class="rounded-[1.5rem] border border-slate-200/80 bg-slate-50/80 p-5 dark:border-slate-800 dark:bg-slate-900/70"
                        >
                            <p
                                class="text-xs font-medium tracking-[0.16em] text-slate-400 uppercase"
                            >
                                {{
                                    t(
                                        'settings.profile.support.summary.lastDonation',
                                    )
                                }}
                            </p>
                            <p
                                class="mt-3 text-base font-semibold text-slate-950 dark:text-slate-50"
                            >
                                {{
                                    formatSupportDate(
                                        props.support.last_donation_at,
                                    )
                                }}
                            </p>
                        </div>
                    </div>

                    <div
                        v-if="supportHistory.length === 0"
                        class="rounded-[1.75rem] border border-dashed border-slate-300/90 bg-slate-50/80 px-5 py-8 text-center dark:border-slate-700 dark:bg-slate-900/60"
                    >
                        <h2
                            class="text-base font-semibold text-slate-950 dark:text-slate-50"
                        >
                            {{ t('settings.profile.support.empty.title') }}
                        </h2>
                        <p
                            class="mt-2 text-sm leading-6 text-slate-500 dark:text-slate-400"
                        >
                            {{
                                t('settings.profile.support.empty.description')
                            }}
                        </p>
                    </div>

                    <div v-else class="space-y-4">
                        <article
                            v-for="donation in supportHistory"
                            :key="donation.id"
                            class="rounded-[1.5rem] border border-slate-200/80 bg-slate-50/80 p-5 dark:border-slate-800 dark:bg-slate-900/70"
                        >
                            <div
                                class="grid gap-4 md:grid-cols-[minmax(0,1fr)_auto] md:items-start"
                            >
                                <div class="space-y-3">
                                    <div
                                        class="flex flex-wrap items-center gap-2"
                                    >
                                        <p
                                            class="text-sm font-semibold text-slate-950 dark:text-slate-50"
                                        >
                                            <SensitiveValue
                                                :value="
                                                    formatSupportAmount(
                                                        donation.amount,
                                                        donation.currency,
                                                    )
                                                "
                                            />
                                        </p>
                                        <Badge
                                            variant="secondary"
                                            class="rounded-full"
                                        >
                                            {{ donation.provider }}
                                        </Badge>
                                        <Badge
                                            variant="outline"
                                            class="rounded-full"
                                        >
                                            {{ donation.status }}
                                        </Badge>
                                    </div>
                                    <p
                                        class="text-sm text-slate-500 dark:text-slate-400"
                                    >
                                        {{
                                            t(
                                                'settings.profile.support.history.date',
                                            )
                                        }}:
                                        {{
                                            formatSupportDate(donation.paid_at)
                                        }}
                                    </p>
                                </div>
                                <p
                                    class="text-sm text-slate-500 md:text-right dark:text-slate-400"
                                >
                                    #{{ donation.id }}
                                </p>
                            </div>
                        </article>
                    </div>

                    <div
                        v-if="shouldShowKofiPrompt && supportPromptCopy"
                        class="rounded-[1.75rem] border border-rose-200/70 bg-[linear-gradient(135deg,rgba(255,247,243,0.98),rgba(255,255,255,0.96))] p-6 shadow-sm dark:border-rose-300/15 dark:bg-[linear-gradient(180deg,rgba(54,25,24,0.88),rgba(15,23,42,0.96))]"
                    >
                        <div class="space-y-4">
                            <Badge
                                class="w-fit rounded-full bg-rose-100 px-3 py-1 text-rose-900 dark:bg-rose-400/10 dark:text-rose-100"
                            >
                                {{ supportPromptCopy.eyebrow }}
                            </Badge>
                            <div class="space-y-2">
                                <h2
                                    class="text-lg font-semibold tracking-tight text-slate-950 dark:text-slate-50"
                                >
                                    {{ supportPromptCopy.title }}
                                </h2>
                                <p
                                    class="max-w-2xl text-sm leading-6 text-slate-600 dark:text-slate-300"
                                >
                                    {{ supportPromptCopy.description }}
                                </p>
                            </div>
                            <div
                                class="rounded-[1.5rem] border border-white/80 bg-white/80 p-4 shadow-sm dark:border-white/10 dark:bg-white/[0.04]"
                            >
                                <KofiSupportWidget
                                    :button-label="supportPromptCopy.button"
                                    :button-color="
                                        props.support.kofi_widget.button_color
                                    "
                                    :page-id="props.support.kofi_widget.page_id"
                                    :script-url="
                                        props.support.kofi_widget.script_url
                                    "
                                />
                                <p
                                    class="mt-3 text-xs leading-5 text-slate-500 dark:text-slate-400"
                                >
                                    {{ supportPromptCopy.note }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section
                class="overflow-hidden rounded-[2rem] border border-slate-200/80 bg-white/95 shadow-[0_30px_90px_-50px_rgba(15,23,42,0.45)] backdrop-blur dark:border-slate-800 dark:bg-slate-950/85"
            >
                <div
                    class="border-b border-slate-200/70 bg-gradient-to-r from-emerald-500/10 via-sky-500/10 to-indigo-500/10 px-8 py-7 dark:border-slate-800"
                >
                    <Heading
                        variant="small"
                        :title="t('settings.profile.active_sessions.title')"
                        :description="
                            t('settings.profile.active_sessions.description')
                        "
                    />
                </div>

                <div
                    class="space-y-6 px-5 py-6 sm:px-6 sm:py-7 lg:px-8 lg:py-8"
                >
                    <div
                        class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between"
                    >
                        <div
                            class="flex items-start gap-3 text-sm text-slate-500 dark:text-slate-400"
                        >
                            <ShieldCheck
                                class="mt-0.5 h-4 w-4 shrink-0 text-emerald-600 dark:text-emerald-400"
                            />
                            <span class="leading-6">{{
                                t(
                                    'settings.profile.active_sessions.current_helper',
                                )
                            }}</span>
                        </div>

                        <Button
                            type="button"
                            variant="outline"
                            class="h-11 w-full rounded-xl px-5 sm:w-auto"
                            :disabled="
                                otherSessionsCount === 0 ||
                                revokeOtherSessionsForm.processing
                            "
                            @click="revokeOthersDialogOpen = true"
                        >
                            <LogOut class="h-4 w-4" />
                            {{
                                t(
                                    'settings.profile.active_sessions.actions.revoke_others',
                                )
                            }}
                        </Button>
                    </div>

                    <div
                        v-if="activeSessions.length === 0"
                        class="rounded-[1.75rem] border border-dashed border-slate-200 bg-slate-50/80 px-6 py-8 text-center dark:border-slate-800 dark:bg-slate-900/70"
                    >
                        <p
                            class="text-sm font-semibold text-slate-900 dark:text-slate-100"
                        >
                            {{
                                t(
                                    'settings.profile.active_sessions.empty.title',
                                )
                            }}
                        </p>
                        <p
                            class="mt-2 text-sm text-slate-500 dark:text-slate-400"
                        >
                            {{
                                t(
                                    'settings.profile.active_sessions.empty.description',
                                )
                            }}
                        </p>
                    </div>

                    <div v-else class="space-y-4">
                        <article
                            v-for="session in activeSessions"
                            :key="session.id"
                            class="rounded-[1.75rem] border border-slate-200/80 bg-slate-50/80 p-5 dark:border-slate-800 dark:bg-slate-900/70"
                        >
                            <div
                                class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_auto] xl:items-start"
                            >
                                <div
                                    class="flex min-w-0 flex-col gap-4 sm:flex-row"
                                >
                                    <div
                                        class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-white text-slate-700 shadow-sm dark:bg-slate-950 dark:text-slate-200"
                                    >
                                        <component
                                            :is="
                                                iconForDeviceType(
                                                    session.device_type,
                                                )
                                            "
                                            class="h-5 w-5"
                                        />
                                    </div>

                                    <div class="min-w-0 space-y-3">
                                        <div
                                            class="flex flex-wrap items-center gap-2"
                                        >
                                            <h3
                                                class="min-w-0 text-sm font-semibold text-slate-950 dark:text-slate-50"
                                            >
                                                {{ session.device_label }}
                                            </h3>
                                            <span
                                                v-if="session.is_current"
                                                class="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-1 text-[11px] font-semibold text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300"
                                            >
                                                {{
                                                    t(
                                                        'settings.profile.active_sessions.current_badge',
                                                    )
                                                }}
                                            </span>
                                        </div>

                                        <dl
                                            class="grid gap-3 text-sm text-slate-600 md:grid-cols-2 xl:grid-cols-3 dark:text-slate-300"
                                        >
                                            <div
                                                class="space-y-1 rounded-2xl border border-slate-200/80 bg-white/80 px-4 py-3 dark:border-slate-800 dark:bg-slate-950/70"
                                            >
                                                <dt
                                                    class="text-xs font-medium tracking-[0.16em] text-slate-400 uppercase"
                                                >
                                                    {{
                                                        t(
                                                            'settings.profile.active_sessions.fields.ip_address',
                                                        )
                                                    }}
                                                </dt>
                                                <dd
                                                    class="font-medium break-all text-slate-900 dark:text-slate-100"
                                                >
                                                    {{ session.ip_address }}
                                                </dd>
                                            </div>
                                            <div
                                                class="space-y-1 rounded-2xl border border-slate-200/80 bg-white/80 px-4 py-3 dark:border-slate-800 dark:bg-slate-950/70"
                                            >
                                                <dt
                                                    class="text-xs font-medium tracking-[0.16em] text-slate-400 uppercase"
                                                >
                                                    {{
                                                        t(
                                                            'settings.profile.active_sessions.fields.device',
                                                        )
                                                    }}
                                                </dt>
                                                <dd
                                                    class="leading-6 break-words"
                                                >
                                                    {{ session.browser }} ·
                                                    {{
                                                        session.operating_system
                                                    }}
                                                    · {{ session.device_type }}
                                                </dd>
                                            </div>
                                            <div
                                                class="space-y-1 rounded-2xl border border-slate-200/80 bg-white/80 px-4 py-3 dark:border-slate-800 dark:bg-slate-950/70"
                                            >
                                                <dt
                                                    class="text-xs font-medium tracking-[0.16em] text-slate-400 uppercase"
                                                >
                                                    {{
                                                        t(
                                                            'settings.profile.active_sessions.fields.last_activity',
                                                        )
                                                    }}
                                                </dt>
                                                <dd
                                                    :title="
                                                        session.last_activity_at
                                                    "
                                                    class="font-medium text-slate-900 dark:text-slate-100"
                                                >
                                                    {{
                                                        session.last_activity_human
                                                    }}
                                                </dd>
                                            </div>
                                        </dl>
                                    </div>
                                </div>

                                <div
                                    class="flex shrink-0 items-center gap-3 xl:justify-end"
                                >
                                    <Button
                                        v-if="session.is_revocable"
                                        type="button"
                                        variant="outline"
                                        class="h-10 w-full rounded-xl px-4 sm:w-auto"
                                        :disabled="revokeSessionForm.processing"
                                        @click="
                                            openRevokeSessionDialog(session)
                                        "
                                    >
                                        {{
                                            t(
                                                'settings.profile.active_sessions.actions.revoke',
                                            )
                                        }}
                                    </Button>
                                </div>
                            </div>
                        </article>
                    </div>
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
                            v-if="props.notification_preferences.push.visible"
                            class="rounded-[1.75rem] border border-slate-200/80 bg-slate-50/80 p-5 dark:border-slate-800 dark:bg-slate-900/70"
                        >
                            <div
                                class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between"
                            >
                                <div class="max-w-2xl space-y-2">
                                    <h3
                                        class="text-base font-semibold text-slate-950 dark:text-slate-50"
                                    >
                                        {{
                                            t(
                                                'settings.profile.notifications.push.title',
                                            )
                                        }}
                                    </h3>
                                    <p
                                        class="text-sm leading-6 text-slate-500 dark:text-slate-400"
                                    >
                                        {{
                                            t(
                                                'settings.profile.notifications.push.description',
                                            )
                                        }}
                                    </p>
                                </div>

                                <button
                                    type="button"
                                    :disabled="isPushWebToggleDisabled"
                                    class="flex min-w-full items-center justify-between gap-3 rounded-2xl border px-4 py-3 text-left transition-colors sm:min-w-[24rem]"
                                    :class="
                                        isPushWebDeviceEnabled
                                            ? 'border-violet-200 bg-violet-50 text-violet-900 dark:border-violet-500/20 dark:bg-violet-500/10 dark:text-violet-100'
                                            : 'border-slate-200 bg-white text-slate-700 dark:border-slate-800 dark:bg-slate-950 dark:text-slate-200'
                                    "
                                    @click="togglePushWebPreference"
                                >
                                    <div class="space-y-1">
                                        <p class="text-sm font-medium">
                                            {{
                                                t(
                                                    'settings.profile.notifications.push.toggle',
                                                )
                                            }}
                                        </p>
                                        <p class="text-xs text-current/75">
                                            {{ pushWebDeviceStateMessage }}
                                        </p>
                                    </div>
                                    <span
                                        class="inline-flex h-7 w-12 items-center rounded-full px-1 transition-colors"
                                        :class="
                                            isPushWebDeviceEnabled
                                                ? 'bg-violet-600'
                                                : 'bg-slate-300 dark:bg-slate-700'
                                        "
                                    >
                                        <span
                                            class="h-5 w-5 rounded-full bg-white shadow-sm transition-transform"
                                            :class="
                                                isPushWebDeviceEnabled
                                                    ? 'translate-x-5'
                                                    : 'translate-x-0'
                                            "
                                        />
                                    </span>
                                </button>
                            </div>

                            <p
                                class="mt-4 text-xs leading-5 text-slate-500 dark:text-slate-400"
                            >
                                {{
                                    notificationPreferencesForm.push.enabled
                                        ? t(
                                              'settings.profile.notifications.push.enabledState',
                                          )
                                        : t(
                                              'settings.profile.notifications.push.disabledState',
                                          )
                                }}
                            </p>
                        </article>

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
                        class="rounded-[1.4rem] border border-slate-200/80 bg-slate-50/80 p-3.5 sm:flex sm:items-start sm:gap-4 sm:rounded-[1.75rem] sm:p-5 dark:border-slate-800 dark:bg-slate-900/70"
                    >
                        <div
                            class="mb-3 flex h-10 w-10 items-center justify-center rounded-[1rem] bg-white text-slate-700 sm:mb-0 sm:h-12 sm:w-12 sm:rounded-2xl dark:bg-slate-950 dark:text-slate-200"
                        >
                            <LifeBuoy class="h-4 w-4 sm:h-5 sm:w-5" />
                        </div>

                        <div class="min-w-0 flex-1 space-y-4">
                            <label
                                class="grid gap-3 rounded-[1.2rem] border border-slate-200/80 bg-white/80 p-3.5 sm:flex sm:items-start sm:gap-3 sm:rounded-2xl sm:p-4 dark:border-slate-800 dark:bg-slate-950/70"
                            >
                                <div
                                    class="flex items-start gap-3 sm:min-w-0 sm:flex-1"
                                >
                                    <Checkbox
                                        class="mt-0.5 shrink-0"
                                        :model-value="
                                            consentForm.is_impersonable
                                        "
                                        :disabled="consentForm.processing"
                                        @update:model-value="
                                            updateImpersonationConsent
                                        "
                                    />
                                    <div class="min-w-0 space-y-1">
                                        <p
                                            class="text-sm font-medium text-slate-950 sm:text-base dark:text-slate-50"
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
                                            class="text-xs leading-5 text-slate-500 dark:text-slate-400"
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
                                </div>

                                <div
                                    class="flex items-center justify-start sm:justify-end"
                                >
                                    <p
                                        class="inline-flex rounded-full px-3 py-1 text-xs font-medium"
                                        :class="
                                            consentForm.is_impersonable
                                                ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300'
                                                : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300'
                                        "
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

                            <div
                                class="flex flex-col gap-3 sm:flex-row sm:items-center"
                            >
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

            <Dialog
                :open="revokeDialogSession !== null"
                @update:open="!$event && closeRevokeSessionDialog()"
            >
                <DialogContent class="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>
                            {{
                                t(
                                    'settings.profile.active_sessions.confirmations.single_title',
                                )
                            }}
                        </DialogTitle>
                        <DialogDescription>
                            {{
                                t(
                                    'settings.profile.active_sessions.confirmations.single_description',
                                )
                            }}
                        </DialogDescription>
                    </DialogHeader>

                    <div
                        v-if="revokeDialogSession"
                        class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm dark:border-slate-800 dark:bg-slate-900"
                    >
                        <p
                            class="font-medium text-slate-950 dark:text-slate-50"
                        >
                            {{ revokeDialogSession.device_label }}
                        </p>
                        <p class="mt-1 text-slate-500 dark:text-slate-400">
                            {{ revokeDialogSession.ip_address }} ·
                            {{ revokeDialogSession.last_activity_human }}
                        </p>
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="secondary"
                            class="rounded-xl"
                            @click="closeRevokeSessionDialog"
                        >
                            {{
                                t(
                                    'settings.profile.active_sessions.actions.cancel',
                                )
                            }}
                        </Button>
                        <Button
                            type="button"
                            class="rounded-xl"
                            :disabled="revokeSessionForm.processing"
                            @click="submitSessionRevocation"
                        >
                            {{
                                t(
                                    'settings.profile.active_sessions.actions.confirm_single',
                                )
                            }}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog
                :open="revokeOthersDialogOpen"
                @update:open="revokeOthersDialogOpen = $event"
            >
                <DialogContent class="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>
                            {{
                                t(
                                    'settings.profile.active_sessions.confirmations.others_title',
                                )
                            }}
                        </DialogTitle>
                        <DialogDescription>
                            {{
                                t(
                                    'settings.profile.active_sessions.confirmations.others_description',
                                )
                            }}
                        </DialogDescription>
                    </DialogHeader>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="secondary"
                            class="rounded-xl"
                            @click="revokeOthersDialogOpen = false"
                        >
                            {{
                                t(
                                    'settings.profile.active_sessions.actions.cancel',
                                )
                            }}
                        </Button>
                        <Button
                            type="button"
                            class="rounded-xl"
                            :disabled="revokeOtherSessionsForm.processing"
                            @click="submitRevokeOtherSessions"
                        >
                            {{
                                t(
                                    'settings.profile.active_sessions.actions.confirm_others',
                                )
                            }}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </SettingsLayout>
    </AppLayout>
</template>
