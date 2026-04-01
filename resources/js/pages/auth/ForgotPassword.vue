<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/auth/AuthShowcaseLayout.vue';
import { login } from '@/routes';
import { email } from '@/routes/password';

defineProps<{
    status?: string;
}>();

const { t } = useI18n();

onMounted((): void => {
    document.getElementById('email')?.focus();
});
</script>

<template>
    <AuthLayout
        :title="t('auth.forgotPassword.title')"
        :description="t('auth.forgotPassword.description')"
        mode="forgot-password"
    >
        <Head :title="t('auth.forgotPassword.headTitle')" />

        <div
            v-if="status"
            class="mb-4 text-center text-sm font-medium text-green-600"
        >
            {{ status }}
        </div>

        <div class="space-y-6">
            <Form v-bind="email.form()" v-slot="{ errors, processing }">
                <div class="grid gap-2.5">
                    <Label for="email">{{
                        t('auth.forgotPassword.fields.email')
                    }}</Label>
                    <Input
                        id="email"
                        type="email"
                        name="email"
                        :autocomplete="'email'"
                        :placeholder="
                            t('auth.forgotPassword.placeholders.email')
                        "
                        class="h-13 rounded-2xl border-slate-200 bg-[#fcfcfb] px-4 shadow-none dark:border-white/12 dark:bg-white/6 dark:text-white dark:placeholder:text-slate-500"
                    />
                    <InputError :message="errors.email" />
                </div>

                <div class="my-6 flex items-center justify-start">
                    <Button
                        class="h-13 w-full rounded-2xl bg-[#ea5a47] text-base font-semibold text-white shadow-[0_16px_30px_-18px_rgba(234,90,71,0.55)] hover:bg-[#de4f3d] dark:bg-[#ea5a47] dark:shadow-[0_16px_30px_-18px_rgba(234,90,71,0.4)] dark:hover:bg-[#de4f3d]"
                        :disabled="processing"
                        data-test="email-password-reset-link-button"
                    >
                        <Spinner v-if="processing" />
                        {{ t('auth.forgotPassword.actions.submit') }}
                    </Button>
                </div>
            </Form>

            <div
                class="space-x-1 text-center text-sm text-muted-foreground dark:text-slate-400"
            >
                <span>{{ t('auth.forgotPassword.footer.backToLogin') }}</span>
                <TextLink :href="login()">{{
                    t('auth.forgotPassword.actions.login')
                }}</TextLink>
            </div>
        </div>
    </AuthLayout>
</template>
