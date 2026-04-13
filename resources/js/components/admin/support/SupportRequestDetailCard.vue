<script setup lang="ts">
import { computed } from 'vue';
import SupportRequestStatusBadge from '@/components/admin/support/SupportRequestStatusBadge.vue';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import type { AdminSupportRequestDetail } from '@/types';

const props = defineProps<{
    supportRequest: AdminSupportRequestDetail;
}>();

const metaEntries = computed(() =>
    Object.entries(props.supportRequest.meta ?? {}).filter(
        ([, value]) => value !== null && value !== '',
    ),
);
</script>

<template>
    <div class="grid gap-4 xl:grid-cols-[1.5fr_1fr]">
        <Card class="rounded-[1.5rem] border-slate-200/80">
            <CardHeader class="space-y-3">
                <div class="flex flex-wrap items-center gap-2">
                    <SupportRequestStatusBadge
                        kind="status"
                        :value="props.supportRequest.status"
                    />
                    <SupportRequestStatusBadge
                        kind="category"
                        :value="props.supportRequest.category"
                    />
                    <span class="text-xs text-slate-500">
                        {{ props.supportRequest.locale.toUpperCase() }}
                    </span>
                </div>
                <div class="space-y-1">
                    <CardTitle class="text-xl tracking-tight">
                        {{ props.supportRequest.subject }}
                    </CardTitle>
                    <CardDescription>
                        Inviata il
                        {{ new Date(props.supportRequest.created_at ?? '').toLocaleString() }}
                    </CardDescription>
                </div>
            </CardHeader>
            <CardContent class="space-y-4">
                <div class="rounded-[1.25rem] border border-slate-200 bg-slate-50/80 p-4">
                    <p class="text-xs font-semibold tracking-[0.16em] text-slate-500 uppercase">
                        Message
                    </p>
                    <p class="mt-3 whitespace-pre-wrap text-sm leading-7 text-slate-700">
                        {{ props.supportRequest.message }}
                    </p>
                </div>

                <div
                    v-if="props.supportRequest.source_url || props.supportRequest.source_route"
                    class="grid gap-3 rounded-[1.25rem] border border-slate-200 bg-white p-4 md:grid-cols-2"
                >
                    <div>
                        <p class="text-xs font-semibold tracking-[0.16em] text-slate-500 uppercase">
                            Source route
                        </p>
                        <p class="mt-2 text-sm text-slate-700">
                            {{ props.supportRequest.source_route ?? 'N/A' }}
                        </p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold tracking-[0.16em] text-slate-500 uppercase">
                            Source URL
                        </p>
                        <a
                            v-if="props.supportRequest.source_url"
                            :href="props.supportRequest.source_url"
                            class="mt-2 block break-all text-sm text-sky-700 underline underline-offset-4"
                            target="_blank"
                            rel="noreferrer"
                        >
                            {{ props.supportRequest.source_url }}
                        </a>
                        <p v-else class="mt-2 text-sm text-slate-700">N/A</p>
                    </div>
                </div>
            </CardContent>
        </Card>

        <div class="space-y-4">
            <Card class="rounded-[1.5rem] border-slate-200/80">
                <CardHeader>
                    <CardTitle class="text-base">User</CardTitle>
                </CardHeader>
                <CardContent class="space-y-3 text-sm text-slate-700">
                    <div>
                        <p class="text-xs font-semibold tracking-[0.16em] text-slate-500 uppercase">
                            Name
                        </p>
                        <p class="mt-2">
                            {{ props.supportRequest.user?.name ?? 'Unknown user' }}
                        </p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold tracking-[0.16em] text-slate-500 uppercase">
                            Email
                        </p>
                        <p class="mt-2 break-all">
                            {{ props.supportRequest.user?.email ?? 'N/A' }}
                        </p>
                    </div>
                </CardContent>
            </Card>

            <Card
                v-if="metaEntries.length > 0"
                class="rounded-[1.5rem] border-slate-200/80"
            >
                <CardHeader>
                    <CardTitle class="text-base">Meta</CardTitle>
                </CardHeader>
                <CardContent class="space-y-3 text-sm text-slate-700">
                    <div
                        v-for="[key, value] in metaEntries"
                        :key="key"
                        class="rounded-2xl border border-slate-200 bg-slate-50/80 p-3"
                    >
                        <p class="text-xs font-semibold tracking-[0.16em] text-slate-500 uppercase">
                            {{ key }}
                        </p>
                        <p class="mt-2 break-all">
                            {{ String(value) }}
                        </p>
                    </div>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
