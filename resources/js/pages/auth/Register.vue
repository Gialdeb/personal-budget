<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useRecaptchaV3 } from '@/composables/useRecaptchaV3';
import AuthBase from '@/layouts/auth/AuthShowcaseLayout.vue';
import { login } from '@/routes';
import { store } from '@/routes/register';

const props = defineProps<{
    recaptcha: {
        enabled: boolean;
        siteKey: string | null;
    };
}>();

const { t } = useI18n();
const form = useForm({
    name: '',
    surname: '',
    email: '',
    password: '',
    password_confirmation: '',
    recaptcha_token: '',
});
const recaptchaError = ref<string | null>(null);
const recaptchaPending = ref(false);
const submitLocked = ref(false);
const recaptcha = useRecaptchaV3(props.recaptcha);
const visibleRecaptchaError = computed(
    (): string | null => recaptchaError.value ?? form.errors.recaptcha_token,
);
const isSubmitting = computed(
    (): boolean =>
        form.processing || recaptchaPending.value || submitLocked.value,
);

onMounted((): void => {
    document.getElementById('name')?.focus();
});

async function submit(): Promise<void> {
    if (submitLocked.value) {
        return;
    }

    submitLocked.value = true;
    recaptchaError.value = null;
    form.clearErrors('recaptcha_token');
    form.recaptcha_token = '';

    let freshRecaptchaToken = '';

    if (props.recaptcha.enabled) {
        recaptchaPending.value = true;
        const token = await recaptcha.execute('register');
        recaptchaPending.value = false;

        if (token === null) {
            recaptchaError.value = t(
                recaptcha.error.value === 'recaptcha_unavailable'
                    ? 'auth.recaptcha.errors.unavailable'
                    : 'auth.recaptcha.errors.failed',
            );
            submitLocked.value = false;

            return;
        }

        freshRecaptchaToken = token;
    }

    form
        .transform((data) => ({
            ...data,
            recaptcha_token: freshRecaptchaToken,
        }))
        .post(store.url(), {
            onError: (errors) => {
                if (errors.recaptcha_token) {
                    recaptchaError.value = errors.recaptcha_token;
                }
            },
            onFinish: () => {
                submitLocked.value = false;
                form.recaptcha_token = '';
                form.transform((data) => data);
            },
            onSuccess: () => {
                form.reset('password', 'password_confirmation', 'recaptcha_token');
            },
        });
}
</script>

<template>
    <AuthBase
        :title="t('auth.register.title')"
        :description="t('auth.register.description')"
        mode="register"
    >
        <Head :title="t('auth.register.headTitle')" />

        <form class="flex flex-col gap-6" @submit.prevent="submit">
            <div class="grid gap-5">
                <div class="grid gap-5 md:grid-cols-2">
                    <div class="grid gap-2.5">
                        <Label for="name">{{
                            t('auth.register.fields.name')
                        }}</Label>
                        <Input
                            v-model="form.name"
                            id="name"
                            type="text"
                            required
                            :tabindex="1"
                            :autocomplete="'given-name'"
                            :placeholder="t('auth.register.placeholders.name')"
                            class="h-13 rounded-2xl border-slate-200 bg-[#fcfcfb] px-4 shadow-none dark:border-white/12 dark:bg-white/6 dark:text-white dark:placeholder:text-slate-500"
                        />
                        <InputError :message="form.errors.name" />
                    </div>

                    <div class="grid gap-2.5">
                        <Label for="surname">{{
                            t('auth.register.fields.surname')
                        }}</Label>
                        <Input
                            v-model="form.surname"
                            id="surname"
                            type="text"
                            :tabindex="2"
                            :autocomplete="'family-name'"
                            :placeholder="
                                t('auth.register.placeholders.surname')
                            "
                            class="h-13 rounded-2xl border-slate-200 bg-[#fcfcfb] px-4 shadow-none dark:border-white/12 dark:bg-white/6 dark:text-white dark:placeholder:text-slate-500"
                        />
                        <InputError :message="form.errors.surname" />
                    </div>
                </div>

                <div class="grid gap-2.5">
                    <Label for="email">{{
                        t('auth.register.fields.email')
                    }}</Label>
                    <Input
                        v-model="form.email"
                        id="email"
                        type="email"
                        required
                        :tabindex="3"
                        :autocomplete="'email'"
                        :placeholder="t('auth.register.placeholders.email')"
                        class="h-13 rounded-2xl border-slate-200 bg-[#fcfcfb] px-4 shadow-none dark:border-white/12 dark:bg-white/6 dark:text-white dark:placeholder:text-slate-500"
                    />
                    <InputError :message="form.errors.email" />
                </div>

                <div class="grid gap-2.5">
                    <Label for="password">{{
                        t('auth.register.fields.password')
                    }}</Label>
                    <PasswordInput
                        v-model="form.password"
                        id="password"
                        required
                        :tabindex="4"
                        :autocomplete="'new-password'"
                        :placeholder="t('auth.register.placeholders.password')"
                        class="h-13 rounded-2xl border-slate-200 bg-[#fcfcfb] px-4 shadow-none dark:border-white/12 dark:bg-white/6 dark:text-white dark:placeholder:text-slate-500"
                    />
                    <InputError :message="form.errors.password" />
                </div>

                <div class="grid gap-2.5">
                    <Label for="password_confirmation">{{
                        t('auth.register.fields.passwordConfirmation')
                    }}</Label>
                    <PasswordInput
                        v-model="form.password_confirmation"
                        id="password_confirmation"
                        required
                        :tabindex="5"
                        :autocomplete="'new-password'"
                        :placeholder="
                            t('auth.register.placeholders.passwordConfirmation')
                        "
                        class="h-13 rounded-2xl border-slate-200 bg-[#fcfcfb] px-4 shadow-none dark:border-white/12 dark:bg-white/6 dark:text-white dark:placeholder:text-slate-500"
                    />
                    <InputError :message="form.errors.password_confirmation" />
                </div>

                <InputError :message="visibleRecaptchaError" />

                <Button
                    type="submit"
                    class="mt-2 h-13 w-full rounded-2xl bg-[#ea5a47] text-base font-semibold text-white shadow-[0_16px_30px_-18px_rgba(234,90,71,0.55)] hover:bg-[#de4f3d] dark:bg-[#ea5a47] dark:shadow-[0_16px_30px_-18px_rgba(234,90,71,0.4)] dark:hover:bg-[#de4f3d]"
                    tabindex="6"
                    :disabled="isSubmitting"
                    data-test="register-user-button"
                >
                    <Spinner v-if="isSubmitting" />
                    {{ t('auth.register.actions.submit') }}
                </Button>

                <p
                    class="text-center text-sm leading-7 text-slate-500 dark:text-slate-400"
                >
                    {{ t('auth.register.legal.prefix') }}
                    <a
                        href="/terms-of-service"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="font-semibold text-[#d55239] underline decoration-[#e7b3a7] underline-offset-4 transition hover:text-[#b8442f] hover:decoration-current dark:text-slate-200 dark:decoration-slate-600 dark:hover:text-white"
                    >
                        {{ t('auth.register.legal.terms') }}
                    </a>
                    {{ t('auth.register.legal.connector') }}
                    <a
                        href="/privacy"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="font-semibold text-[#d55239] underline decoration-[#e7b3a7] underline-offset-4 transition hover:text-[#b8442f] hover:decoration-current dark:text-slate-200 dark:decoration-slate-600 dark:hover:text-white"
                    >
                        {{ t('auth.register.legal.privacy') }}
                    </a>
                    {{ t('auth.register.legal.suffix') }}
                </p>
            </div>

            <div
                class="text-center text-sm text-muted-foreground dark:text-slate-400"
            >
                {{ t('auth.register.footer.hasAccount') }}
                <TextLink :href="login()" :tabindex="7">{{
                    t('auth.register.actions.login')
                }}</TextLink>
            </div>
        </form>
    </AuthBase>
</template>
