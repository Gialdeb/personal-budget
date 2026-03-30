<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { LifeBuoy, Send } from 'lucide-vue-next';
import { computed } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, User } from '@/types';

const page = usePage();
const user = computed(() => page.props.auth.user as User | null);

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Supporto',
        href: '/support',
    },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Supporto" />

        <section class="space-y-6">
            <div
                class="overflow-hidden rounded-[2rem] border border-slate-200/80 bg-white/95 shadow-[0_30px_90px_-50px_rgba(15,23,42,0.45)] backdrop-blur"
            >
                <div class="border-b border-slate-200/70 px-6 py-6">
                    <div class="space-y-3">
                        <div
                            class="inline-flex items-center gap-2 rounded-full border border-slate-200/80 bg-white/80 px-3 py-1 text-[11px] font-semibold tracking-[0.18em] text-slate-500 uppercase"
                        >
                            <LifeBuoy class="size-3.5" />
                            Supporto
                        </div>
                        <div>
                            <h1
                                class="text-2xl font-semibold tracking-tight text-slate-950"
                            >
                                Scrivimi per supporto
                            </h1>
                            <p
                                class="mt-1 max-w-3xl text-sm leading-6 text-slate-600"
                            >
                                Usa questo spazio per spiegare il problema,
                                chiedere chiarimenti o segnalare un punto da
                                migliorare. Il canale resta interno all’account
                                per mantenere tutto più ordinato.
                            </p>
                        </div>
                    </div>
                </div>

                <div
                    class="grid gap-6 px-6 py-6 lg:grid-cols-[minmax(0,1fr)_20rem]"
                >
                    <div class="space-y-5">
                        <div class="grid gap-5 sm:grid-cols-2">
                            <div class="space-y-2">
                                <label
                                    for="support-name"
                                    class="text-sm font-medium text-slate-950"
                                >
                                    Nome
                                </label>
                                <input
                                    id="support-name"
                                    :value="user?.name ?? ''"
                                    readonly
                                    class="h-11 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm text-slate-700"
                                />
                            </div>
                            <div class="space-y-2">
                                <label
                                    for="support-email"
                                    class="text-sm font-medium text-slate-950"
                                >
                                    Email
                                </label>
                                <input
                                    id="support-email"
                                    :value="user?.email ?? ''"
                                    readonly
                                    class="h-11 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm text-slate-700"
                                />
                            </div>
                        </div>

                        <div class="space-y-2">
                            <label
                                for="support-subject"
                                class="text-sm font-medium text-slate-950"
                            >
                                Oggetto
                            </label>
                            <input
                                id="support-subject"
                                value="Supporto Soamco Budget"
                                readonly
                                class="h-11 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm text-slate-700"
                            />
                        </div>

                        <div class="space-y-2">
                            <label
                                for="support-message"
                                class="text-sm font-medium text-slate-950"
                            >
                                Messaggio
                            </label>
                            <textarea
                                id="support-message"
                                rows="7"
                                placeholder="Descrivi qui il contesto, il dubbio o il problema che hai riscontrato."
                                class="w-full rounded-[1.5rem] border border-slate-200 bg-white px-4 py-3 text-sm leading-6 text-slate-700 transition outline-none focus:border-slate-300 focus:ring-2 focus:ring-slate-200/70"
                            />
                        </div>
                    </div>

                    <aside
                        class="rounded-[1.75rem] border border-slate-200 bg-[linear-gradient(180deg,#ffffff_0%,#f8fafc_100%)] p-5"
                    >
                        <div class="space-y-4">
                            <p class="text-sm font-semibold text-slate-950">
                                Canale interno
                            </p>
                            <p class="text-sm leading-7 text-slate-600">
                                Donazioni e richieste di supporto verranno
                                gestite da qui, all’interno dell’account, senza
                                passare da contatti esterni sparsi.
                            </p>
                            <button
                                type="button"
                                disabled
                                class="inline-flex w-full items-center justify-center gap-2 rounded-2xl bg-slate-900 px-4 py-3 text-sm font-semibold text-white opacity-60"
                            >
                                <Send class="size-4" />
                                Invio disponibile a breve
                            </button>
                            <p class="text-xs leading-6 text-slate-500">
                                Il form è già pronto come punto unico di
                                riferimento per supporto e contributi.
                            </p>
                        </div>
                    </aside>
                </div>
            </div>
        </section>
    </AppLayout>
</template>
