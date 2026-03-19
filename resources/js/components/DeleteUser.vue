<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { useTemplateRef } from 'vue';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';

const passwordInput = useTemplateRef('passwordInput');
</script>

<template>
    <section
        class="overflow-hidden rounded-[2rem] border border-red-200/80 bg-white/95 shadow-[0_30px_90px_-50px_rgba(220,38,38,0.35)] backdrop-blur dark:border-red-500/20 dark:bg-slate-950/85"
    >
        <div
            class="border-b border-red-200/70 bg-gradient-to-r from-red-500/10 via-rose-500/10 to-orange-500/10 px-8 py-7 dark:border-red-500/20"
        >
            <Heading
                variant="small"
                title="Elimina account"
                description="Rimuovi definitivamente account e dati associati."
            />
        </div>
        <div class="px-8 py-8">
            <div
                class="space-y-5 rounded-[1.5rem] border border-red-200 bg-red-50/90 p-5 dark:border-red-500/20 dark:bg-red-500/10"
            >
                <div class="relative space-y-1 text-red-700 dark:text-red-100">
                    <p class="font-medium">Attenzione</p>
                    <p class="text-sm leading-6">
                        Questa azione è definitiva e non può essere annullata.
                    </p>
                </div>
                <Dialog>
                    <DialogTrigger as-child>
                        <Button
                            variant="destructive"
                            class="rounded-xl"
                            data-test="delete-user-button"
                        >
                            Elimina account
                        </Button>
                    </DialogTrigger>
                    <DialogContent>
                        <Form
                            v-bind="ProfileController.destroy.form()"
                            reset-on-success
                            @error="() => passwordInput?.focus()"
                            :options="{
                                preserveScroll: true,
                            }"
                            class="space-y-6"
                            v-slot="{ errors, processing, reset, clearErrors }"
                        >
                            <DialogHeader class="space-y-3">
                                <DialogTitle>
                                    Confermi l’eliminazione del tuo account?
                                </DialogTitle>
                                <DialogDescription>
                                    Una volta eliminato l’account, tutti i dati
                                    e le relative risorse verranno rimossi in
                                    modo permanente. Inserisci la password per
                                    confermare.
                                </DialogDescription>
                            </DialogHeader>

                            <div class="grid gap-2">
                                <Label for="password" class="sr-only">
                                    Password
                                </Label>
                                <PasswordInput
                                    id="password"
                                    name="password"
                                    ref="passwordInput"
                                    placeholder="Password"
                                />
                                <InputError :message="errors.password" />
                            </div>

                            <DialogFooter class="gap-2">
                                <DialogClose as-child>
                                    <Button
                                        variant="secondary"
                                        @click="
                                            () => {
                                                clearErrors();
                                                reset();
                                            }
                                        "
                                    >
                                        Annulla
                                    </Button>
                                </DialogClose>

                                <Button
                                    type="submit"
                                    variant="destructive"
                                    :disabled="processing"
                                    data-test="confirm-delete-user-button"
                                >
                                    Elimina account
                                </Button>
                            </DialogFooter>
                        </Form>
                    </DialogContent>
                </Dialog>
            </div>
        </div>
    </section>
</template>
