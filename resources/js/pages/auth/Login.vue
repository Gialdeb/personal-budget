<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthBase from '@/layouts/auth/AuthShowcaseLayout.vue';
import { register } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

defineProps<{
    status?: string;
    canResetPassword: boolean;
    canRegister: boolean;
}>();

const { t } = useI18n();

onMounted((): void => {
    document.getElementById('email')?.focus();
});
</script>

<template>
    <AuthBase
        :title="t('auth.login.title')"
        :description="t('auth.login.description')"
    >
        <Head :title="t('auth.login.headTitle')" />

        <div
            v-if="status"
            class="mb-4 text-center text-sm font-medium text-green-600"
        >
            {{ status }}
        </div>

        <Form
            v-bind="store.form()"
            :reset-on-success="['password']"
            v-slot="{ errors, processing }"
            class="flex flex-col gap-6"
        >
            <div class="grid gap-5">
                <div class="grid gap-2.5">
                    <Label for="email">{{
                        t('auth.login.fields.email')
                    }}</Label>
                    <Input
                        id="email"
                        type="email"
                        name="email"
                        required
                        :tabindex="1"
                        :autocomplete="'email'"
                        :placeholder="t('auth.login.placeholders.email')"
                        class="h-13 rounded-2xl border-slate-200 bg-[#fcfcfb] px-4 shadow-none"
                    />
                    <InputError :message="errors.email" />
                </div>

                <div class="grid gap-2.5">
                    <div class="flex items-center justify-between">
                        <Label for="password">{{
                            t('auth.login.fields.password')
                        }}</Label>
                        <TextLink
                            v-if="canResetPassword"
                            :href="request()"
                            class="text-sm"
                            :tabindex="5"
                        >
                            {{ t('auth.login.actions.forgotPassword') }}
                        </TextLink>
                    </div>
                    <PasswordInput
                        id="password"
                        name="password"
                        required
                        :tabindex="2"
                        :autocomplete="'current-password'"
                        :placeholder="t('auth.login.placeholders.password')"
                        class="h-13 rounded-2xl border-slate-200 bg-[#fcfcfb] px-4 shadow-none"
                    />
                    <InputError :message="errors.password" />
                </div>

                <div class="flex items-center justify-between">
                    <Label for="remember" class="flex items-center space-x-3">
                        <Checkbox id="remember" name="remember" :tabindex="3" />
                        <span>{{ t('auth.login.fields.remember') }}</span>
                    </Label>
                </div>

                <Button
                    type="submit"
                    class="mt-2 h-13 w-full rounded-2xl bg-[#ea5a47] text-base font-semibold text-white shadow-[0_16px_30px_-18px_rgba(234,90,71,0.55)] hover:bg-[#de4f3d]"
                    :tabindex="4"
                    :disabled="processing"
                    data-test="login-button"
                >
                    <Spinner v-if="processing" />
                    {{ t('auth.login.actions.submit') }}
                </Button>

                <p class="text-center text-sm leading-7 text-slate-500">
                    {{ t('auth.login.legal.prefix') }}
                    <a
                        href="/terms-of-service"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="font-semibold text-[#d55239] underline decoration-[#e7b3a7] underline-offset-4 transition hover:text-[#b8442f] hover:decoration-current"
                    >
                        {{ t('auth.login.legal.terms') }}
                    </a>
                    {{ t('auth.login.legal.connector') }}
                    <a
                        href="/privacy"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="font-semibold text-[#d55239] underline decoration-[#e7b3a7] underline-offset-4 transition hover:text-[#b8442f] hover:decoration-current"
                    >
                        {{ t('auth.login.legal.privacy') }}
                    </a>
                    {{ t('auth.login.legal.suffix') }}
                </p>
            </div>

            <div
                class="text-center text-sm text-muted-foreground"
                v-if="canRegister"
            >
                {{ t('auth.login.footer.noAccount') }}
                <TextLink :href="register()" :tabindex="5">{{
                    t('auth.login.actions.register')
                }}</TextLink>
            </div>
        </Form>
    </AuthBase>
</template>
