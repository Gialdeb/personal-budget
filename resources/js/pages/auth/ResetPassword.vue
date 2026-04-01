<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/auth/AuthShowcaseLayout.vue';
import { update } from '@/routes/password';

const props = defineProps<{
    token: string;
    email: string;
}>();

const inputEmail = ref(props.email);
const { t } = useI18n();

onMounted((): void => {
    document.getElementById('password')?.focus();
});
</script>

<template>
    <AuthLayout
        :title="t('auth.resetPassword.title')"
        :description="t('auth.resetPassword.description')"
        mode="reset-password"
    >
        <Head :title="t('auth.resetPassword.headTitle')" />

        <Form
            v-bind="update.form()"
            :transform="(data) => ({ ...data, token, email })"
            :reset-on-success="['password', 'password_confirmation']"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-6">
                <div class="grid gap-2.5">
                    <Label for="email">{{
                        t('auth.resetPassword.fields.email')
                    }}</Label>
                    <Input
                        id="email"
                        type="email"
                        name="email"
                        :autocomplete="'email'"
                        v-model="inputEmail"
                        class="h-13 rounded-2xl border-slate-200 bg-[#f8f7f5] px-4 text-slate-500 shadow-none dark:border-white/12 dark:bg-white/5 dark:text-slate-300"
                        readonly
                    />
                    <InputError :message="errors.email" />
                </div>

                <div class="grid gap-2.5">
                    <Label for="password">{{
                        t('auth.resetPassword.fields.password')
                    }}</Label>
                    <PasswordInput
                        id="password"
                        name="password"
                        :autocomplete="'new-password'"
                        class="h-13 rounded-2xl border-slate-200 bg-[#fcfcfb] px-4 shadow-none dark:border-white/12 dark:bg-white/6 dark:text-white dark:placeholder:text-slate-500"
                        :placeholder="
                            t('auth.resetPassword.placeholders.password')
                        "
                    />
                    <InputError :message="errors.password" />
                </div>

                <div class="grid gap-2.5">
                    <Label for="password_confirmation">
                        {{
                            t('auth.resetPassword.fields.passwordConfirmation')
                        }}
                    </Label>
                    <PasswordInput
                        id="password_confirmation"
                        name="password_confirmation"
                        :autocomplete="'new-password'"
                        class="h-13 rounded-2xl border-slate-200 bg-[#fcfcfb] px-4 shadow-none dark:border-white/12 dark:bg-white/6 dark:text-white dark:placeholder:text-slate-500"
                        :placeholder="
                            t(
                                'auth.resetPassword.placeholders.passwordConfirmation',
                            )
                        "
                    />
                    <InputError :message="errors.password_confirmation" />
                </div>

                <Button
                    type="submit"
                    class="mt-2 h-13 w-full rounded-2xl bg-[#ea5a47] text-base font-semibold text-white shadow-[0_16px_30px_-18px_rgba(234,90,71,0.55)] hover:bg-[#de4f3d] dark:bg-[#ea5a47] dark:shadow-[0_16px_30px_-18px_rgba(234,90,71,0.4)] dark:hover:bg-[#de4f3d]"
                    :disabled="processing"
                    data-test="reset-password-button"
                >
                    <Spinner v-if="processing" />
                    {{ t('auth.resetPassword.actions.submit') }}
                </Button>
            </div>
        </Form>
    </AuthLayout>
</template>
