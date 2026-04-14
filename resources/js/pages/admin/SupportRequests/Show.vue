<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import SupportRequestDetailCard from '@/components/admin/support/SupportRequestDetailCard.vue';
import SupportRequestStatusBadge from '@/components/admin/support/SupportRequestStatusBadge.vue';
import Heading from '@/components/Heading.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
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
    update as updateSupportRequest,
} from '@/routes/admin/support-requests';
import type { BreadcrumbItem } from '@/types';
import type {
    AdminSupportRequestsShowPageProps,
    SupportRequestStatus,
} from '@/types/admin';

const props = defineProps<AdminSupportRequestsShowPageProps>();
const page = usePage();

const flash = (page.props.flash ?? {}) as {
    success?: string | null;
    error?: string | null;
};

const breadcrumbItems: BreadcrumbItem[] = [
    { title: 'Admin', href: adminIndex() },
    { title: 'Support Requests', href: supportRequestsIndex() },
    { title: props.supportRequest.subject, href: supportRequestsIndex() },
];

const form = useForm({
    status: props.supportRequest.status,
});

function submitStatus(): void {
    form.patch(
        updateSupportRequest({
            supportRequest: props.supportRequest.uuid,
        }).url,
    );
}

function formatStatusLabel(value: SupportRequestStatus): string {
    return {
        new: 'New',
        in_progress: 'In progress',
        closed: 'Closed',
    }[value];
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="props.supportRequest.subject" />

        <AdminLayout>
            <section class="space-y-6">
                <div
                    class="rounded-[2rem] border border-slate-200/80 bg-white/95 p-8 shadow-[0_30px_90px_-50px_rgba(15,23,42,0.45)]"
                >
                    <div
                        class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between"
                    >
                        <div class="space-y-3">
                            <Badge
                                class="rounded-full border border-sky-200 bg-sky-50 px-3 py-1 text-[11px] tracking-[0.2em] text-sky-800 uppercase"
                            >
                                Support Requests
                            </Badge>
                            <Heading
                                variant="small"
                                :title="props.supportRequest.subject"
                                description="Dettaglio amministrativo della richiesta supporto, con stato aggiornabile e contesto utile."
                            />
                        </div>

                        <Button
                            variant="outline"
                            class="h-11 rounded-2xl"
                            as-child
                        >
                            <Link :href="supportRequestsIndex().url"
                                >Torna alla lista</Link
                            >
                        </Button>
                    </div>
                </div>

                <Alert v-if="flash.success">
                    <AlertTitle>Stato aggiornato</AlertTitle>
                    <AlertDescription>{{ flash.success }}</AlertDescription>
                </Alert>

                <div class="grid gap-4 xl:grid-cols-[1.8fr_1fr]">
                    <SupportRequestDetailCard
                        :support-request="props.supportRequest"
                    />

                    <Card class="rounded-[1.5rem] border-slate-200/80">
                        <CardHeader>
                            <CardTitle class="text-base"
                                >Aggiorna stato</CardTitle
                            >
                            <CardDescription>
                                Workflow minimo v1: new, in progress, closed.
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div class="flex flex-wrap items-center gap-2">
                                <SupportRequestStatusBadge
                                    kind="status"
                                    :value="form.status"
                                />
                                <SupportRequestStatusBadge
                                    kind="category"
                                    :value="props.supportRequest.category"
                                />
                            </div>

                            <form
                                class="space-y-4"
                                @submit.prevent="submitStatus"
                            >
                                <label class="grid gap-2">
                                    <span
                                        class="text-sm font-medium text-slate-700"
                                    >
                                        Status
                                    </span>
                                    <select
                                        v-model="form.status"
                                        class="h-11 rounded-2xl border border-slate-200 bg-white px-4 text-sm text-slate-700 ring-0 transition outline-none focus:border-slate-300"
                                    >
                                        <option
                                            v-for="status in props.statusOptions"
                                            :key="status"
                                            :value="status"
                                        >
                                            {{ formatStatusLabel(status) }}
                                        </option>
                                    </select>
                                </label>

                                <Button
                                    type="submit"
                                    class="h-11 w-full rounded-2xl"
                                    :disabled="form.processing"
                                >
                                    Salva stato
                                </Button>
                            </form>
                        </CardContent>
                    </Card>
                </div>
            </section>
        </AdminLayout>
    </AppLayout>
</template>
