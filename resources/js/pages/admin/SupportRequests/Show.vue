<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import SupportRequestDetailCard from '@/components/admin/support/SupportRequestDetailCard.vue';
import SupportRequestStatusBadge from '@/components/admin/support/SupportRequestStatusBadge.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import SupportTicketConversation from '@/components/SupportTicketConversation.vue';
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
import { reply as replySupportRequest } from '@/routes/admin/support-requests';
import {
    index as supportRequestsIndex,
    update as updateSupportRequest,
} from '@/routes/admin/support-requests';
import type { BreadcrumbItem } from '@/types';
import type { AdminSupportRequestsShowPageProps } from '@/types/admin';

const props = defineProps<AdminSupportRequestsShowPageProps>();
const page = usePage();
const { t } = useI18n();

const flash = computed(
    () =>
        (page.props.flash ?? {}) as {
            success?: string | null;
            error?: string | null;
        },
);

const breadcrumbItems = computed<BreadcrumbItem[]>(() => [
    { title: t('admin.title'), href: adminIndex() },
    {
        title: t('admin.sections.supportRequests'),
        href: supportRequestsIndex(),
    },
    { title: props.supportRequest.subject, href: supportRequestsIndex() },
]);

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

const replyForm = useForm({ body: '' });
function sendReply(): void {
    replyForm.post(
        replySupportRequest({ supportRequest: props.supportRequest.uuid }).url,
        { preserveScroll: true, onSuccess: () => replyForm.reset() },
    );
}
function toggleClosed(): void {
    form.status =
        props.supportRequest.status === 'closed' ? 'waiting_admin' : 'closed';
    submitStatus();
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="props.supportRequest.subject" />

        <AdminLayout>
            <section class="space-y-6">
                <div
                    class="rounded-[2rem] border border-border bg-card p-4 shadow-[0_30px_90px_-50px_rgba(15,23,42,0.45)] sm:p-8"
                >
                    <div
                        class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between"
                    >
                        <div class="space-y-3">
                            <Badge
                                class="rounded-full border border-sky-200 bg-sky-50 px-3 py-1 text-[11px] tracking-[0.2em] text-sky-800 uppercase"
                            >
                                {{ t('admin.supportRequestsPage.badge') }}
                            </Badge>
                            <Heading
                                variant="small"
                                :title="props.supportRequest.subject"
                                :description="
                                    t(
                                        'admin.supportRequestsPage.detail.description',
                                    )
                                "
                            />
                        </div>

                        <Button
                            variant="outline"
                            class="h-11 rounded-2xl"
                            as-child
                        >
                            <Link :href="supportRequestsIndex().url">{{
                                t(
                                    'admin.supportRequestsPage.actions.backToList',
                                )
                            }}</Link>
                        </Button>
                    </div>
                </div>

                <Alert v-if="flash.success">
                    <AlertTitle>{{ t('supportTickets.updated') }}</AlertTitle>
                    <AlertDescription>{{ flash.success }}</AlertDescription>
                </Alert>

                <section
                    class="space-y-4 rounded-3xl border border-border bg-card p-4 sm:p-6"
                >
                    <p class="text-sm text-muted-foreground">
                        {{ t('supportTickets.version') }}:
                        <strong class="text-foreground">{{
                            props.supportRequest.app_version ??
                            t('supportTickets.unknown')
                        }}</strong>
                    </p>
                    <h2 class="font-semibold">
                        {{ t('supportTickets.conversation') }}
                    </h2>
                    <SupportTicketConversation
                        :messages="props.supportRequest.messages"
                        admin
                    />
                    <form
                        v-if="props.supportRequest.status === 'waiting_admin'"
                        class="space-y-3"
                        @submit.prevent="sendReply"
                    >
                        <label
                            for="admin-support-reply"
                            class="text-sm font-semibold"
                            >{{ t('supportTickets.adminReply') }}</label
                        >
                        <p class="text-xs text-muted-foreground">
                            {{ t('supportTickets.anonymous') }}
                        </p>
                        <textarea
                            id="admin-support-reply"
                            v-model="replyForm.body"
                            required
                            minlength="2"
                            maxlength="5000"
                            rows="5"
                            class="w-full rounded-2xl border border-border bg-background p-3 text-sm"
                        />
                        <InputError :message="replyForm.errors.body" />
                        <Button
                            type="submit"
                            :disabled="replyForm.processing"
                            >{{ t('supportTickets.reply') }}</Button
                        >
                    </form>
                    <p
                        v-else-if="
                            props.supportRequest.status === 'waiting_user'
                        "
                        class="text-sm text-muted-foreground"
                    >
                        {{ t('supportTickets.waitUser') }}
                    </p>
                </section>

                <div class="grid gap-4 xl:grid-cols-[1.8fr_1fr]">
                    <SupportRequestDetailCard
                        :support-request="props.supportRequest"
                    />

                    <Card class="rounded-[1.5rem] border-slate-200/80">
                        <CardHeader>
                            <CardTitle class="text-base">{{
                                t(
                                    'admin.supportRequestsPage.detail.updateStatusTitle',
                                )
                            }}</CardTitle>
                            <CardDescription>
                                {{
                                    t(
                                        'admin.supportRequestsPage.detail.updateStatusDescription',
                                    )
                                }}
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div class="flex flex-wrap items-center gap-2">
                                <SupportRequestStatusBadge
                                    kind="status"
                                    admin
                                    :value="props.supportRequest.status"
                                />
                                <SupportRequestStatusBadge
                                    kind="category"
                                    :value="props.supportRequest.category"
                                />
                            </div>

                            <p class="text-sm text-muted-foreground">
                                {{ t('supportTickets.closeInfo') }}
                            </p>
                            <InputError :message="form.errors.status" />
                            <Button
                                type="button"
                                variant="outline"
                                class="w-full"
                                :disabled="form.processing"
                                @click="toggleClosed"
                                >{{
                                    t(
                                        props.supportRequest.status === 'closed'
                                            ? 'supportTickets.reopen'
                                            : 'supportTickets.close',
                                    )
                                }}</Button
                            >
                        </CardContent>
                    </Card>
                </div>
            </section>
        </AdminLayout>
    </AppLayout>
</template>
