<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { ShieldCheck } from 'lucide-vue-next';
import { onUnmounted, ref } from 'vue';
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

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Sicurezza',
        href: edit(),
    },
];

const { hasSetupData, clearTwoFactorAuthData } = useTwoFactorAuth();
const showSetupModal = ref<boolean>(false);

onUnmounted(() => clearTwoFactorAuthData());
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Sicurezza" />

        <h1 class="sr-only">Sicurezza</h1>

        <SettingsLayout>
            <section
                class="overflow-hidden rounded-[2rem] border border-slate-200/80 bg-white/95 shadow-[0_30px_90px_-50px_rgba(15,23,42,0.45)] backdrop-blur dark:border-slate-800 dark:bg-slate-950/85"
            >
                <div
                    class="border-b border-slate-200/70 bg-gradient-to-r from-emerald-500/10 via-teal-500/10 to-cyan-500/10 px-8 py-7 dark:border-slate-800"
                >
                    <Heading
                        variant="small"
                        title="Aggiorna password"
                        description="Mantieni l’accesso al tuo account protetto con una password robusta e aggiornata."
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
                            <Label for="current_password"
                                >Password attuale</Label
                            >
                            <PasswordInput
                                id="current_password"
                                name="current_password"
                                class="mt-1 block h-11 w-full rounded-xl border-slate-200 bg-white/90"
                                autocomplete="current-password"
                                placeholder="Inserisci la password attuale"
                            />
                            <InputError :message="errors.current_password" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="password">Nuova password</Label>
                            <PasswordInput
                                id="password"
                                name="password"
                                class="mt-1 block h-11 w-full rounded-xl border-slate-200 bg-white/90"
                                autocomplete="new-password"
                                placeholder="Inserisci la nuova password"
                            />
                            <InputError :message="errors.password" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="password_confirmation"
                                >Conferma password</Label
                            >
                            <PasswordInput
                                id="password_confirmation"
                                name="password_confirmation"
                                class="mt-1 block h-11 w-full rounded-xl border-slate-200 bg-white/90"
                                autocomplete="new-password"
                                placeholder="Ripeti la nuova password"
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
                            Salva password
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
                                Salvato.
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
                        title="Autenticazione a due fattori"
                        description="Aggiungi un livello di protezione extra al login del tuo account."
                    />
                </div>

                <div
                    v-if="!twoFactorEnabled"
                    class="flex flex-col items-start justify-start gap-5 px-8 py-8"
                >
                    <p class="text-sm leading-6 text-muted-foreground">
                        Quando attivi l’autenticazione a due fattori, durante il
                        login ti verrà richiesto un codice sicuro generato da
                        un’app compatibile TOTP sul tuo telefono.
                    </p>

                    <div>
                        <Button
                            v-if="hasSetupData"
                            class="rounded-xl"
                            @click="showSetupModal = true"
                        >
                            <ShieldCheck />Continua configurazione
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
                                Attiva 2FA
                            </Button>
                        </Form>
                    </div>
                </div>

                <div
                    v-else
                    class="flex flex-col items-start justify-start gap-5 px-8 py-8"
                >
                    <p class="text-sm leading-6 text-muted-foreground">
                        Durante il login ti verrà richiesto un codice sicuro
                        generato dall’app TOTP collegata al tuo account.
                    </p>

                    <div class="relative inline">
                        <Form v-bind="disable.form()" #default="{ processing }">
                            <Button
                                variant="destructive"
                                type="submit"
                                :disabled="processing"
                                class="rounded-xl"
                            >
                                Disattiva 2FA
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
