<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { ShieldCheck } from 'lucide-vue-next';
import { onUnmounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import SecurityController from '@/actions/App/Http/Controllers/Settings/SecurityController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TwoFactorRecoveryCodes from '@/components/TwoFactorRecoveryCodes.vue';
import TwoFactorSetupModal from '@/components/TwoFactorSetupModal.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { useTwoFactorAuth } from '@/composables/useTwoFactorAuth';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { edit } from '@/routes/security';
import { disable, enable } from '@/routes/two-factor';
import type { BreadcrumbItem } from '@/types';

type Props = {
    canManageTwoFactor?: boolean;
    requiresConfirmation?: boolean;
    twoFactorEnabled?: boolean;
};

withDefaults(defineProps<Props>(), {
    canManageTwoFactor: false,
    requiresConfirmation: false,
    twoFactorEnabled: false,
});
const { t } = useI18n();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: t('settings.sections.security'),
        href: edit(),
    },
];

const { hasSetupData, clearTwoFactorAuthData } = useTwoFactorAuth();
const showSetupModal = ref<boolean>(false);

onUnmounted(() => clearTwoFactorAuthData());
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="t('settings.sections.security')" />

        <h1 class="sr-only">{{ t('settings.sections.security') }}</h1>

        <SettingsLayout>
            <section
                class="overflow-hidden rounded-[2rem] border border-slate-200/80 bg-white/95 shadow-[0_30px_90px_-50px_rgba(15,23,42,0.45)] backdrop-blur dark:border-slate-800 dark:bg-slate-950/85"
            >
                <div
                    class="border-b border-slate-200/70 bg-gradient-to-r from-emerald-500/10 via-teal-500/10 to-cyan-500/10 px-8 py-7 dark:border-slate-800"
                >
                    <Heading
                        variant="small"
                        :title="t('settings.security.password.title')"
                        :description="t('settings.security.password.description')"
                    />
                </div>

                <Form
                    v-bind="SecurityController.update.form()"
                    :options="{
                        preserveScroll: true,
                    }"
                    reset-on-success
                    :reset-on-error="[
                        'password',
                        'password_confirmation',
                        'current_password',
                    ]"
                    class="space-y-8 px-8 py-8"
                    v-slot="{ errors, processing, recentlySuccessful }"
                >
                    <div class="grid gap-5 md:grid-cols-2">
                        <div class="grid gap-2 md:col-span-2">
                            <Label for="current_password">{{ t('settings.security.password.current') }}</Label>
                            <PasswordInput
                                id="current_password"
                                name="current_password"
                                class="mt-1 block h-11 w-full rounded-xl border-slate-200 bg-white/90"
                                autocomplete="current-password"
                                :placeholder="t('settings.security.password.currentPlaceholder')"
                            />
                            <InputError :message="errors.current_password" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="password">{{ t('settings.security.password.next') }}</Label>
                            <PasswordInput
                                id="password"
                                name="password"
                                class="mt-1 block h-11 w-full rounded-xl border-slate-200 bg-white/90"
                                autocomplete="new-password"
                                :placeholder="t('settings.security.password.nextPlaceholder')"
                            />
                            <InputError :message="errors.password" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="password_confirmation">{{ t('settings.security.password.confirmation') }}</Label>
                            <PasswordInput
                                id="password_confirmation"
                                name="password_confirmation"
                                class="mt-1 block h-11 w-full rounded-xl border-slate-200 bg-white/90"
                                autocomplete="new-password"
                                :placeholder="t('settings.security.password.confirmationPlaceholder')"
                            />
                            <InputError
                                :message="errors.password_confirmation"
                            />
                        </div>
                    </div>

                    <div
                        class="flex flex-col gap-3 border-t border-slate-200/80 pt-6 sm:flex-row sm:items-center dark:border-slate-800"
                    >
                        <Button
                            :disabled="processing"
                            class="h-11 rounded-xl px-5"
                            data-test="update-password-button"
                        >
                            {{ t('settings.security.password.save') }}
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
                v-if="canManageTwoFactor"
                class="overflow-hidden rounded-[2rem] border border-slate-200/80 bg-white/95 shadow-[0_30px_90px_-50px_rgba(15,23,42,0.45)] backdrop-blur dark:border-slate-800 dark:bg-slate-950/85"
            >
                <div
                    class="border-b border-slate-200/70 bg-gradient-to-r from-amber-500/10 via-orange-500/10 to-rose-500/10 px-8 py-7 dark:border-slate-800"
                >
                    <Heading
                        variant="small"
                        :title="t('settings.security.twoFactor.title')"
                        :description="t('settings.security.twoFactor.description')"
                    />
                </div>

                <div
                    v-if="!twoFactorEnabled"
                    class="flex flex-col items-start justify-start gap-5 px-8 py-8"
                >
                    <p class="text-sm leading-6 text-muted-foreground">
                        {{ t('settings.security.twoFactor.enableDescription') }}
                    </p>

                    <div>
                        <Button
                            v-if="hasSetupData"
                            class="rounded-xl"
                            @click="showSetupModal = true"
                        >
                            <ShieldCheck />{{ t('settings.security.twoFactor.continue') }}
                        </Button>
                        <Form
                            v-else
                            v-bind="enable.form()"
                            @success="showSetupModal = true"
                            #default="{ processing }"
                        >
                            <Button
                                type="submit"
                                :disabled="processing"
                                class="rounded-xl"
                            >
                                {{ t('settings.security.twoFactor.enable') }}
                            </Button>
                        </Form>
                    </div>
                </div>

                <div
                    v-else
                    class="flex flex-col items-start justify-start gap-5 px-8 py-8"
                >
                    <p class="text-sm leading-6 text-muted-foreground">
                        {{ t('settings.security.twoFactor.enabledDescription') }}
                    </p>

                    <div class="relative inline">
                        <Form v-bind="disable.form()" #default="{ processing }">
                            <Button
                                variant="destructive"
                                type="submit"
                                :disabled="processing"
                                class="rounded-xl"
                            >
                                {{ t('settings.security.twoFactor.disable') }}
                            </Button>
                        </Form>
                    </div>

                    <TwoFactorRecoveryCodes />
                </div>

                <TwoFactorSetupModal
                    v-model:isOpen="showSetupModal"
                    :requiresConfirmation="requiresConfirmation"
                    :twoFactorEnabled="twoFactorEnabled"
                />
            </section>
        </SettingsLayout>
    </AppLayout>
</template>
