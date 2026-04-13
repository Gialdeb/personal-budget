<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight } from 'lucide-vue-next';
import SupportRequestFilters from '@/components/admin/support/SupportRequestFilters.vue';
import SupportRequestStatusBadge from '@/components/admin/support/SupportRequestStatusBadge.vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import AdminLayout from '@/layouts/admin/Layout.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { index as adminIndex } from '@/routes/admin';
import {
    index as supportRequestsIndex,
    show as showSupportRequest,
} from '@/routes/admin/support-requests';
import type { BreadcrumbItem } from '@/types';
import type { AdminSupportRequestsIndexPageProps } from '@/types/admin';

const props = defineProps<AdminSupportRequestsIndexPageProps>();

const breadcrumbItems: BreadcrumbItem[] = [
    { title: 'Admin', href: adminIndex() },
    { title: 'Support Requests', href: supportRequestsIndex() },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Support Requests" />

        <AdminLayout>
            <section class="space-y-6">
                <div
                    class="rounded-[2rem] border border-slate-200/80 bg-white/95 p-8 shadow-[0_30px_90px_-50px_rgba(15,23,42,0.45)]"
                >
                    <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                        <div class="space-y-3">
                            <Badge
                                class="rounded-full border border-sky-200 bg-sky-50 px-3 py-1 text-[11px] tracking-[0.2em] text-sky-800 uppercase"
                            >
                                Support Requests
                            </Badge>
                            <Heading
                                variant="small"
                                title="Richieste supporto"
                                description="Lista amministrativa essenziale delle richieste inviate dagli utenti, con filtro rapido per stato e categoria."
                            />
                        </div>

                        <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
                            Totale richieste: {{ props.supportRequests.meta.total }}
                        </div>
                    </div>
                </div>

                <SupportRequestFilters
                    :filters="props.filters"
                    :options="props.options"
                />

                <Card class="rounded-[1.5rem] border-slate-200/80">
                    <CardHeader>
                        <CardTitle class="text-base">Inbox</CardTitle>
                        <CardDescription>
                            Oggetto, stato, categoria, utente, lingua e data invio.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <div
                            v-if="props.supportRequests.data.length === 0"
                            class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-8 text-center text-sm text-slate-600"
                        >
                            Nessuna richiesta trovata con i filtri attuali.
                        </div>

                        <div
                            v-for="supportRequest in props.supportRequests.data"
                            :key="supportRequest.uuid"
                            class="rounded-[1.5rem] border border-slate-200 bg-slate-50/70 p-4"
                        >
                            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                <div class="space-y-3">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <p class="text-lg font-semibold tracking-tight text-slate-950">
                                            {{ supportRequest.subject }}
                                        </p>
                                        <SupportRequestStatusBadge
                                            kind="status"
                                            :value="supportRequest.status"
                                        />
                                        <SupportRequestStatusBadge
                                            kind="category"
                                            :value="supportRequest.category"
                                        />
                                    </div>

                                    <div class="flex flex-wrap gap-x-4 gap-y-2 text-sm text-slate-600">
                                        <span>
                                            Utente:
                                            <strong class="text-slate-900">
                                                {{ supportRequest.user?.name ?? 'Utente rimosso' }}
                                            </strong>
                                        </span>
                                        <span>
                                            Email:
                                            <strong class="text-slate-900">
                                                {{ supportRequest.user?.email ?? 'N/A' }}
                                            </strong>
                                        </span>
                                        <span>
                                            Lingua:
                                            <strong class="text-slate-900">
                                                {{ supportRequest.locale.toUpperCase() }}
                                            </strong>
                                        </span>
                                        <span>
                                            Inviata:
                                            <strong class="text-slate-900">
                                                {{ new Date(supportRequest.created_at ?? '').toLocaleString() }}
                                            </strong>
                                        </span>
                                    </div>

                                    <div class="flex flex-wrap gap-x-4 gap-y-2 text-xs text-slate-500">
                                        <span v-if="supportRequest.source_route">
                                            Route: {{ supportRequest.source_route }}
                                        </span>
                                        <span v-if="supportRequest.source_url" class="break-all">
                                            URL: {{ supportRequest.source_url }}
                                        </span>
                                    </div>
                                </div>

                                <Button variant="outline" class="rounded-2xl" as-child>
                                    <Link
                                        :href="
                                            showSupportRequest({
                                                supportRequest: supportRequest.uuid,
                                            }).url
                                        "
                                    >
                                        Apri dettaglio
                                        <ArrowRight class="ml-2 size-4" />
                                    </Link>
                                </Button>
                            </div>
                        </div>

                        <div
                            v-if="props.supportRequests.meta.last_page > 1"
                            class="flex flex-col gap-3 border-t border-slate-200 pt-4 text-sm text-slate-600 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <p>
                                Pagina {{ props.supportRequests.meta.current_page }}
                                di {{ props.supportRequests.meta.last_page }}
                            </p>
                            <div class="flex gap-3">
                                <Button
                                    variant="outline"
                                    class="rounded-2xl"
                                    :disabled="!props.supportRequests.links.prev"
                                    as-child
                                >
                                    <Link
                                        :href="props.supportRequests.links.prev ?? '#'"
                                        preserve-scroll
                                    >
                                        Precedente
                                    </Link>
                                </Button>
                                <Button
                                    variant="outline"
                                    class="rounded-2xl"
                                    :disabled="!props.supportRequests.links.next"
                                    as-child
                                >
                                    <Link
                                        :href="props.supportRequests.links.next ?? '#'"
                                        preserve-scroll
                                    >
                                        Successiva
                                    </Link>
                                </Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </section>
        </AdminLayout>
    </AppLayout>
</template>
