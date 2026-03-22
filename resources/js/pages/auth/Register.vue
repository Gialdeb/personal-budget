<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthBase from '@/layouts/AuthLayout.vue';
import { login } from '@/routes';
import { store } from '@/routes/register';

const { t } = useI18n();

onMounted((): void => {
    document.getElementById('name')?.focus();
});
</script>

<template>
    <AuthBase :title="t('auth.register.title')" :description="t('auth.register.description')">
        <Head :title="t('auth.register.headTitle')" />

        <Form
            v-bind="store.form()"
            :reset-on-success="['password', 'password_confirmation']"
            v-slot="{ errors, processing }"
            class="flex flex-col gap-6"
        >
            <div class="grid gap-6">
                <div class="grid gap-6 md:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="name">{{ t('auth.register.fields.name') }}</Label>
                        <Input
                            id="name"
                            type="text"
                            required
                            :tabindex="1"
                            :autocomplete="'given-name'"
                            name="name"
                            :placeholder="t('auth.register.placeholders.name')"
                        />
                        <InputError :message="errors.name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="surname">{{ t('auth.register.fields.surname') }}</Label>
                        <Input
                            id="surname"
                            type="text"
                            :tabindex="2"
                            :autocomplete="'family-name'"
                            name="surname"
                            :placeholder="t('auth.register.placeholders.surname')"
                        />
                        <InputError :message="errors.surname" />
                    </div>
                </div>

                <div class="grid gap-2">
                    <Label for="email">{{ t('auth.register.fields.email') }}</Label>
                    <Input
                        id="email"
                        type="email"
                        required
                        :tabindex="3"
                        :autocomplete="'email'"
                        name="email"
                        :placeholder="t('auth.register.placeholders.email')"
                    />
                    <InputError :message="errors.email" />
                </div>

                <div class="grid gap-2">
                    <Label for="password">{{ t('auth.register.fields.password') }}</Label>
                    <PasswordInput
                        id="password"
                        required
                        :tabindex="4"
                        :autocomplete="'new-password'"
                        name="password"
                        :placeholder="t('auth.register.placeholders.password')"
                    />
                    <InputError :message="errors.password" />
                </div>

                <div class="grid gap-2">
                    <Label for="password_confirmation">{{ t('auth.register.fields.passwordConfirmation') }}</Label>
                    <PasswordInput
                        id="password_confirmation"
                        required
                        :tabindex="5"
                        :autocomplete="'new-password'"
                        name="password_confirmation"
                        :placeholder="t('auth.register.placeholders.passwordConfirmation')"
                    />
                    <InputError :message="errors.password_confirmation" />
                </div>

                <Button
                    type="submit"
                    class="mt-2 w-full"
                    tabindex="6"
                    :disabled="processing"
                    data-test="register-user-button"
                >
                    <Spinner v-if="processing" />
                    {{ t('auth.register.actions.submit') }}
                </Button>
            </div>

            <div class="text-center text-sm text-muted-foreground">
                {{ t('auth.register.footer.hasAccount') }}
                <TextLink
                    :href="login()"
                    class="underline underline-offset-4"
                    :tabindex="7"
                >{{ t('auth.register.actions.login') }}</TextLink>
            </div>
        </Form>
    </AuthBase>
</template>
