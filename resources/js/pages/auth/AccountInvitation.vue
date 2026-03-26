<script setup lang="ts">
import { Form, Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { logout, login } from '@/routes';
import {
    acceptAuthenticated,
    register as registerFromInvitation,
} from '@/actions/App/Http/Controllers/Sharing/AccountInvitationOnboardingController';

type InvitationProps = {
    state: string;
    email: string;
    role: string;
    role_label: string;
    expires_at: string | null;
    requires_registration: boolean;
    requires_login: boolean;
    can_accept: boolean;
    account: {
        uuid: string | null;
        name: string | null;
    };
    inviter: {
        name: string | null;
    };
    invitation: {
        uuid: string;
        status: string | null;
        status_label: string | null;
    };
};

const props = defineProps<{
    invitation?: InvitationProps | null;
    token?: string | null;
    canRegister?: boolean;
}>();

const page = usePage();
const { t, locale } = useI18n();

const safeInvitation = computed<InvitationProps>(() => ({
    state: props.invitation?.state ?? 'invalid',
    email: props.invitation?.email ?? '',
    role: props.invitation?.role ?? '',
    role_label:
        props.invitation?.role_label ??
        t('auth.accountInvitation.fallbacks.notAvailable'),
    expires_at: props.invitation?.expires_at ?? null,
    requires_registration:
        props.invitation?.requires_registration ?? false,
    requires_login: props.invitation?.requires_login ?? false,
    can_accept: props.invitation?.can_accept ?? false,
    account: {
        uuid: props.invitation?.account?.uuid ?? null,
        name: props.invitation?.account?.name ?? null,
    },
    inviter: {
        name: props.invitation?.inviter?.name ?? null,
    },
    invitation: {
        uuid: props.invitation?.invitation?.uuid ?? '',
        status: props.invitation?.invitation?.status ?? null,
        status_label: props.invitation?.invitation?.status_label ?? null,
    },
}));
const safeToken = computed(() => props.token ?? '');
const canRegister = computed(() => props.canRegister ?? false);
const authenticatedEmail = computed(() => page.props.auth?.user?.email ?? null);

const formattedExpiration = computed(() => {
    if (!safeInvitation.value.expires_at) {
        return null;
    }

    const date = new Date(safeInvitation.value.expires_at);

    if (Number.isNaN(date.getTime())) {
        return safeInvitation.value.expires_at;
    }

    return new Intl.DateTimeFormat(locale.value === 'it' ? 'it-IT' : 'en-US', {
        dateStyle: 'long',
        timeStyle: 'short',
    }).format(date);
});

const title = computed(() => {
    switch (safeInvitation.value.state) {
        case 'registration_required':
            return t('auth.accountInvitation.states.registration.title');
        case 'login_required':
            return t('auth.accountInvitation.states.login.title');
        case 'ready_to_accept':
            return t('auth.accountInvitation.states.accept.title');
        case 'email_mismatch':
            return t('auth.accountInvitation.states.mismatch.title');
        case 'expired':
            return t('auth.accountInvitation.states.expired.title');
        case 'already_processed':
            return t('auth.accountInvitation.states.processed.title');
        default:
            return t('auth.accountInvitation.states.invalid.title');
    }
});

const description = computed(() => {
    switch (safeInvitation.value.state) {
        case 'registration_required':
            return t('auth.accountInvitation.states.registration.description', {
                inviter: safeInvitation.value.inviter.name ?? t('auth.accountInvitation.fallbacks.inviter'),
                account: safeInvitation.value.account.name ?? t('auth.accountInvitation.fallbacks.account'),
            });
        case 'login_required':
            return t('auth.accountInvitation.states.login.description', {
                email: safeInvitation.value.email || t('auth.accountInvitation.fallbacks.email'),
            });
        case 'ready_to_accept':
            return t('auth.accountInvitation.states.accept.description', {
                account: safeInvitation.value.account.name ?? t('auth.accountInvitation.fallbacks.account'),
            });
        case 'email_mismatch':
            return t('auth.accountInvitation.states.mismatch.description');
        case 'expired':
            return t('auth.accountInvitation.states.expired.description');
        case 'already_processed':
            return t('auth.accountInvitation.states.processed.description');
        default:
            return t('auth.accountInvitation.states.invalid.description');
    }
});
</script>

<template>
    <AuthLayout
        :title="title"
        :description="description"
        size="wide"
    >
        <Head :title="t('auth.accountInvitation.headTitle')" />

        <div class="mx-auto w-full max-w-3xl space-y-6">
            <section class="rounded-3xl border border-border/70 bg-card/80 p-6 shadow-sm">
                <div class="space-y-4">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="rounded-2xl bg-muted/60 p-4">
                            <p class="text-xs font-medium uppercase tracking-[0.24em] text-muted-foreground">
                                {{ t('auth.accountInvitation.summary.inviter') }}
                            </p>
                            <p class="mt-2 text-sm font-semibold text-foreground">
                                {{ safeInvitation.inviter.name ?? t('auth.accountInvitation.fallbacks.inviter') }}
                            </p>
                        </div>
                        <div class="rounded-2xl bg-muted/60 p-4">
                            <p class="text-xs font-medium uppercase tracking-[0.24em] text-muted-foreground">
                                {{ t('auth.accountInvitation.summary.account') }}
                            </p>
                            <p class="mt-2 text-sm font-semibold text-foreground">
                                {{ safeInvitation.account.name ?? t('auth.accountInvitation.fallbacks.account') }}
                            </p>
                        </div>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="rounded-2xl border border-border/60 p-4">
                            <p class="text-xs font-medium uppercase tracking-[0.24em] text-muted-foreground">
                                {{ t('auth.accountInvitation.summary.role') }}
                            </p>
                            <p class="mt-2 text-sm font-semibold text-foreground">
                                {{ safeInvitation.role_label }}
                            </p>
                        </div>
                        <div class="rounded-2xl border border-border/60 p-4">
                            <p class="text-xs font-medium uppercase tracking-[0.24em] text-muted-foreground">
                                {{ t('auth.accountInvitation.summary.email') }}
                            </p>
                            <p class="mt-2 text-sm font-semibold text-foreground">
                                {{ safeInvitation.email || t('auth.accountInvitation.fallbacks.email') }}
                            </p>
                        </div>
                    </div>

                    <p
                        v-if="formattedExpiration"
                        class="text-sm text-muted-foreground"
                    >
                        {{ t('auth.accountInvitation.summary.expiresAt', { date: formattedExpiration }) }}
                    </p>
                </div>
            </section>

            <Form
                v-if="safeInvitation.state === 'registration_required' && canRegister"
                v-bind="registerFromInvitation.form({ accountInvitation: safeInvitation.invitation.uuid })"
                v-slot="{ errors, processing }"
                class="space-y-6"
            >
                <input type="hidden" name="token" :value="safeToken" />

                <div class="grid gap-4 md:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="first_name">{{ t('auth.accountInvitation.form.firstName') }}</Label>
                        <Input
                            id="first_name"
                            name="first_name"
                            type="text"
                            required
                            autocomplete="given-name"
                            :placeholder="t('auth.accountInvitation.form.firstName')"
                        />
                        <InputError :message="errors.first_name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="last_name">{{ t('auth.accountInvitation.form.lastName') }}</Label>
                        <Input
                            id="last_name"
                            name="last_name"
                            type="text"
                            required
                            autocomplete="family-name"
                            :placeholder="t('auth.accountInvitation.form.lastName')"
                        />
                        <InputError :message="errors.last_name" />
                    </div>
                </div>

                <div class="grid gap-2">
                    <Label for="invitation_email">{{ t('auth.accountInvitation.form.email') }}</Label>
                    <Input
                        id="invitation_email"
                        type="email"
                        :model-value="safeInvitation.email"
                        disabled
                        readonly
                    />
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="password">{{ t('auth.accountInvitation.form.password') }}</Label>
                        <PasswordInput
                            id="password"
                            name="password"
                            required
                            autocomplete="new-password"
                            :placeholder="t('auth.accountInvitation.form.password')"
                        />
                        <InputError :message="errors.password" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="password_confirmation">{{ t('auth.accountInvitation.form.passwordConfirmation') }}</Label>
                        <PasswordInput
                            id="password_confirmation"
                            name="password_confirmation"
                            required
                            autocomplete="new-password"
                            :placeholder="t('auth.accountInvitation.form.passwordConfirmation')"
                        />
                        <InputError :message="errors.password_confirmation" />
                    </div>
                </div>

                <Button type="submit" class="w-full" :disabled="processing">
                    <Spinner v-if="processing" />
                    {{ t('auth.accountInvitation.form.submitRegister') }}
                </Button>
            </Form>

            <div
                v-else-if="safeInvitation.state === 'login_required'"
                class="space-y-4"
            >
                <Alert>
                    <AlertTitle>{{ t('auth.accountInvitation.states.login.alertTitle') }}</AlertTitle>
                    <AlertDescription>
                        {{ t('auth.accountInvitation.states.login.alertDescription', { email: safeInvitation.email || t('auth.accountInvitation.fallbacks.email') }) }}
                    </AlertDescription>
                </Alert>

                <Link
                    :href="login()"
                    class="inline-flex w-full items-center justify-center rounded-xl bg-primary px-4 py-3 text-sm font-medium text-primary-foreground transition hover:opacity-90"
                >
                    {{ t('auth.accountInvitation.actions.goToLogin') }}
                </Link>
            </div>

            <Form
                v-else-if="safeInvitation.state === 'ready_to_accept' && safeInvitation.can_accept"
                v-bind="acceptAuthenticated.form({ accountInvitation: safeInvitation.invitation.uuid })"
                v-slot="{ processing }"
                class="space-y-4"
            >
                <input type="hidden" name="token" :value="safeToken" />

                <Alert>
                    <AlertTitle>{{ t('auth.accountInvitation.states.accept.alertTitle') }}</AlertTitle>
                    <AlertDescription>
                        {{ t('auth.accountInvitation.states.accept.alertDescription') }}
                    </AlertDescription>
                </Alert>

                <Button type="submit" class="w-full" :disabled="processing">
                    <Spinner v-if="processing" />
                    {{ t('auth.accountInvitation.actions.accept') }}
                </Button>
            </Form>

            <div
                v-else-if="safeInvitation.state === 'email_mismatch'"
                class="space-y-4"
            >
                <Alert variant="destructive">
                    <AlertTitle>{{ t('auth.accountInvitation.states.mismatch.alertTitle') }}</AlertTitle>
                    <AlertDescription>
                        {{ t('auth.accountInvitation.states.mismatch.alertDescription', {
                            currentEmail: authenticatedEmail ?? t('auth.accountInvitation.fallbacks.email'),
                            inviteeEmail: safeInvitation.email || t('auth.accountInvitation.fallbacks.email'),
                        }) }}
                    </AlertDescription>
                </Alert>

                <Link
                    :href="logout()"
                    method="post"
                    as="button"
                    class="inline-flex w-full items-center justify-center rounded-xl border border-border px-4 py-3 text-sm font-medium text-foreground transition hover:bg-muted"
                >
                    {{ t('auth.accountInvitation.actions.logoutAndSwitch') }}
                </Link>
            </div>

            <Alert
                v-else-if="safeInvitation.state === 'registration_required' && !canRegister"
                variant="destructive"
            >
                <AlertTitle>{{ title }}</AlertTitle>
                <AlertDescription>{{ description }}</AlertDescription>
            </Alert>

            <Alert
                v-else
                variant="destructive"
            >
                <AlertTitle>{{ title }}</AlertTitle>
                <AlertDescription>{{ description }}</AlertDescription>
            </Alert>
        </div>
    </AuthLayout>
</template>
