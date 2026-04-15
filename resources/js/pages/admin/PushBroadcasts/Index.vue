<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { BellRing, SendHorizontal } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AdminLayout from '@/layouts/admin/Layout.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { index as adminIndex } from '@/routes/admin';
import { index as pushBroadcastsIndex, store as storePushBroadcast } from '@/routes/admin/push-broadcasts';
import type { BreadcrumbItem } from '@/types';

type Props = {
    audience: {
        eligible_users_count: number;
        target_tokens_count: number;
    };
    broadcasts: {
        data: Array<{
            uuid: string;
            status: string;
            title: string;
            body: string;
            url: string | null;
            eligible_users_count: number;
            target_tokens_count: number;
            sent_count: number;
            failed_count: number;
            invalidated_count: number;
            queued_at: string | null;
            started_at: string | null;
            finished_at: string | null;
            error_message: string | null;
            creator: { uuid: string; name: string } | null;
        }>;
    };
};

const props = defineProps<Props>();
const { t } = useI18n();

const breadcrumbItems: BreadcrumbItem[] = [
    { title: t('admin.sections.overview'), href: adminIndex() },
    { title: t('admin.sections.pushBroadcasts'), href: pushBroadcastsIndex() },
];

const form = useForm({
    title: '',
    body: '',
    url: '',
});

function submit(): void {
    form.post(storePushBroadcast().url, {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="t('admin.pushBroadcasts.title')" />

        <AdminLayout>
            <section
                class="overflow-hidden rounded-[2rem] border border-slate-200/80 bg-white/95 shadow-[0_30px_90px_-50px_rgba(15,23,42,0.45)] backdrop-blur dark:border-slate-800 dark:bg-slate-950/85"
            >
                <div
                    class="border-b border-slate-200/70 bg-gradient-to-r from-violet-500/10 via-sky-500/10 to-emerald-500/10 px-8 py-7 dark:border-slate-800"
                >
                    <Heading
                        variant="small"
                        :title="t('admin.pushBroadcasts.title')"
                        :description="t('admin.pushBroadcasts.description')"
                    />
                </div>

                <div class="space-y-6 px-8 py-8">
                    <div class="grid gap-4 md:grid-cols-2">
                        <Card
                            class="rounded-[1.5rem] border-slate-200/80 bg-white/90 shadow-none dark:border-slate-800 dark:bg-slate-950/70"
                        >
                            <CardHeader>
                                <CardTitle class="text-base">
                                    {{
                                        t(
                                            'admin.pushBroadcasts.audience.eligibleUsers',
                                        )
                                    }}
                                </CardTitle>
                            </CardHeader>
                            <CardContent class="pt-0 text-3xl font-semibold">
                                {{ props.audience.eligible_users_count }}
                            </CardContent>
                        </Card>

                        <Card
                            class="rounded-[1.5rem] border-slate-200/80 bg-white/90 shadow-none dark:border-slate-800 dark:bg-slate-950/70"
                        >
                            <CardHeader>
                                <CardTitle class="text-base">
                                    {{
                                        t(
                                            'admin.pushBroadcasts.audience.targetTokens',
                                        )
                                    }}
                                </CardTitle>
                            </CardHeader>
                            <CardContent class="pt-0 text-3xl font-semibold">
                                {{ props.audience.target_tokens_count }}
                            </CardContent>
                        </Card>
                    </div>

                    <form class="space-y-5" @submit.prevent="submit">
                        <div class="grid gap-5 lg:grid-cols-2">
                            <div class="space-y-2">
                                <Label for="push-title">
                                    {{ t('admin.pushBroadcasts.form.title') }}
                                </Label>
                                <Input
                                    id="push-title"
                                    v-model="form.title"
                                    data-test="push-broadcast-title"
                                />
                                <InputError :message="form.errors.title" />
                            </div>

                            <div class="space-y-2">
                                <Label for="push-url">
                                    {{ t('admin.pushBroadcasts.form.url') }}
                                </Label>
                                <Input
                                    id="push-url"
                                    v-model="form.url"
                                    data-test="push-broadcast-url"
                                />
                                <InputError :message="form.errors.url" />
                            </div>
                        </div>

                        <div class="space-y-2">
                            <Label for="push-body">
                                {{ t('admin.pushBroadcasts.form.body') }}
                            </Label>
                            <textarea
                                id="push-body"
                                v-model="form.body"
                                data-test="push-broadcast-body"
                                class="min-h-28 w-full rounded-[0.75rem] border border-slate-200 bg-white px-3 py-2 text-sm text-slate-950 shadow-sm outline-none transition focus:border-slate-400 focus:ring-2 focus:ring-slate-200 dark:border-slate-800 dark:bg-slate-950 dark:text-slate-50 dark:focus:border-slate-600 dark:focus:ring-slate-800"
                            />
                            <InputError :message="form.errors.body" />
                        </div>

                        <div class="flex items-center gap-3">
                            <Button
                                :disabled="form.processing"
                                class="h-11 rounded-xl px-5"
                                data-test="push-broadcast-submit"
                            >
                                <SendHorizontal class="mr-2 h-4 w-4" />
                                {{ t('admin.pushBroadcasts.actions.queue') }}
                            </Button>
                            <p class="text-sm text-slate-500 dark:text-slate-400">
                                {{ t('admin.pushBroadcasts.form.helper') }}
                            </p>
                        </div>
                    </form>

                    <div class="space-y-4">
                        <div class="flex items-center gap-2">
                            <BellRing class="h-4 w-4 text-slate-500" />
                            <h2 class="text-base font-semibold">
                                {{ t('admin.pushBroadcasts.history.title') }}
                            </h2>
                        </div>

                        <div
                            v-if="props.broadcasts.data.length === 0"
                            class="rounded-[1.5rem] border border-dashed border-slate-300/90 bg-slate-50/80 px-5 py-8 text-center dark:border-slate-700 dark:bg-slate-900/60"
                        >
                            <p class="text-sm text-slate-500 dark:text-slate-400">
                                {{ t('admin.pushBroadcasts.history.empty') }}
                            </p>
                        </div>

                        <article
                            v-for="broadcast in props.broadcasts.data"
                            :key="broadcast.uuid"
                            class="rounded-[1.5rem] border border-slate-200/80 bg-slate-50/80 p-5 dark:border-slate-800 dark:bg-slate-900/70"
                        >
                            <div
                                class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between"
                            >
                                <div class="space-y-2">
                                    <div class="flex items-center gap-3">
                                        <h3 class="text-base font-semibold">
                                            {{ broadcast.title }}
                                        </h3>
                                        <Badge class="rounded-full px-3 py-1">
                                            {{
                                                t(
                                                    `admin.pushBroadcasts.statuses.${broadcast.status}`,
                                                )
                                            }}
                                        </Badge>
                                    </div>
                                    <p
                                        class="text-sm leading-6 text-slate-500 dark:text-slate-400"
                                    >
                                        {{ broadcast.body }}
                                    </p>
                                </div>

                                <dl
                                    class="grid gap-3 text-sm text-slate-500 dark:text-slate-400 sm:grid-cols-2"
                                >
                                    <div>
                                        <dt>
                                            {{
                                                t(
                                                    'admin.pushBroadcasts.history.eligibleUsers',
                                                )
                                            }}
                                        </dt>
                                        <dd class="font-medium text-slate-950 dark:text-slate-50">
                                            {{ broadcast.eligible_users_count }}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt>
                                            {{
                                                t(
                                                    'admin.pushBroadcasts.history.targetTokens',
                                                )
                                            }}
                                        </dt>
                                        <dd class="font-medium text-slate-950 dark:text-slate-50">
                                            {{ broadcast.target_tokens_count }}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt>
                                            {{
                                                t(
                                                    'admin.pushBroadcasts.history.sent',
                                                )
                                            }}
                                        </dt>
                                        <dd class="font-medium text-slate-950 dark:text-slate-50">
                                            {{ broadcast.sent_count }}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt>
                                            {{
                                                t(
                                                    'admin.pushBroadcasts.history.failed',
                                                )
                                            }}
                                        </dt>
                                        <dd class="font-medium text-slate-950 dark:text-slate-50">
                                            {{ broadcast.failed_count }}
                                        </dd>
                                    </div>
                                </dl>
                            </div>

                            <p
                                v-if="broadcast.error_message"
                                class="mt-3 text-sm text-rose-600 dark:text-rose-400"
                            >
                                {{ broadcast.error_message }}
                            </p>
                        </article>
                    </div>
                </div>
            </section>
        </AdminLayout>
    </AppLayout>
</template>
