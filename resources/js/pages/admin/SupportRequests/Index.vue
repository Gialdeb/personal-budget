<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';
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
const { locale, t } = useI18n();

const breadcrumbItems: BreadcrumbItem[] = [
    { title: t('admin.title'), href: adminIndex() },
    {
        title: t('admin.sections.supportRequests'),
        href: supportRequestsIndex(),
    },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="t('admin.sections.supportRequests')" />

        <AdminLayout>
            <section class="space-y-6">
                <div
                    class="rounded-[2rem] border border-slate-200/80 bg-white/95 p-8 shadow-[0_30px_90px_-50px_rgba(15,23,42,0.45)]"
                >
                    <div
                        class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between"
                    >
                        <div class="space-y-3">
                            <Badge
                                class="rounded-full border border-sky-200 bg-sky-50 px-3 py-1 text-[11px] tracking-[0.2em] text-sky-800 uppercase"
                            >
                                {{ t('admin.supportRequestsPage.badge') }}
                            </Badge>
                            <Heading
                                variant="small"
                                :title="t('admin.supportRequestsPage.title')"
                                :description="
                                    t('admin.supportRequestsPage.description')
                                "
                            />
                        </div>

                        <div
                            class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600"
                        >
                            {{
                                t('admin.supportRequestsPage.summary', {
                                    total: props.supportRequests.meta.total,
                                })
                            }}
                        </div>
                    </div>
                </div>

                <SupportRequestFilters
                    :filters="props.filters"
                    :options="props.options"
                />

                <Card class="rounded-[1.5rem] border-slate-200/80">
                    <CardHeader>
                        <CardTitle class="text-base">{{
                            t('admin.supportRequestsPage.listTitle')
                        }}</CardTitle>
                        <CardDescription>
                            {{
                                t(
                                    'admin.supportRequestsPage.listDescription',
                                )
                            }}
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <div
                            v-if="props.supportRequests.data.length === 0"
                            class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-8 text-center text-sm text-slate-600"
                        >
                            {{ t('admin.supportRequestsPage.empty') }}
                        </div>

                        <div
                            v-for="supportRequest in props.supportRequests.data"
                            :key="supportRequest.uuid"
                            class="rounded-[1.5rem] border border-slate-200 bg-slate-50/70 p-4"
                        >
                            <div
                                class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between"
                            >
                                <div class="space-y-3">
                                    <div
                                        class="flex flex-wrap items-center gap-2"
                                    >
                                        <p
                                            class="text-lg font-semibold tracking-tight text-slate-950"
                                        >
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

                                    <div
                                        class="flex flex-wrap gap-x-4 gap-y-2 text-sm text-slate-600"
                                    >
                                        <span>
                                            {{
                                                t(
                                                    'admin.supportRequestsPage.fields.user',
                                                )
                                            }}:
                                            <strong class="text-slate-900">
                                                {{
                                                    supportRequest.user?.name ??
                                                    t(
                                                        'admin.supportRequestsPage.fields.removedUser',
                                                    )
                                                }}
                                            </strong>
                                        </span>
                                        <span>
                                            {{
                                                t(
                                                    'admin.supportRequestsPage.fields.email',
                                                )
                                            }}:
                                            <strong class="text-slate-900">
                                                {{
                                                    supportRequest.user
                                                        ?.email ??
                                                    t(
                                                        'admin.supportRequestsPage.fields.unavailable',
                                                    )
                                                }}
                                            </strong>
                                        </span>
                                        <span>
                                            {{
                                                t(
                                                    'admin.supportRequestsPage.fields.locale',
                                                )
                                            }}:
                                            <strong class="text-slate-900">
                                                {{
                                                    supportRequest.locale.toUpperCase()
                                                }}
                                            </strong>
                                        </span>
                                        <span>
                                            {{
                                                t(
                                                    'admin.supportRequestsPage.fields.sentAt',
                                                )
                                            }}:
                                            <strong class="text-slate-900">
                                                {{
                                                    new Date(
                                                        supportRequest.created_at ??
                                                            '',
                                                    ).toLocaleString(
                                                        locale,
                                                    )
                                                }}
                                            </strong>
                                        </span>
                                    </div>

                                    <div
                                        class="flex flex-wrap gap-x-4 gap-y-2 text-xs text-slate-500"
                                    >
                                        <span
                                            v-if="supportRequest.source_route"
                                        >
                                            {{
                                                t(
                                                    'admin.supportRequestsPage.fields.route',
                                                )
                                            }}:
                                            {{ supportRequest.source_route }}
                                        </span>
                                        <span
                                            v-if="supportRequest.source_url"
                                            class="break-all"
                                        >
                                            {{
                                                t(
                                                    'admin.supportRequestsPage.fields.url',
                                                )
                                            }}:
                                            {{ supportRequest.source_url }}
                                        </span>
                                    </div>
                                </div>

                                <Button
                                    variant="outline"
                                    class="rounded-2xl"
                                    as-child
                                >
                                    <Link
                                        :href="
                                            showSupportRequest({
                                                supportRequest:
                                                    supportRequest.uuid,
                                            }).url
                                        "
                                    >
                                        {{
                                            t(
                                                'admin.supportRequestsPage.actions.open',
                                            )
                                        }}
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
                                {{
                                    t(
                                        'admin.supportRequestsPage.pagination.page',
                                        {
                                            current:
                                                props.supportRequests.meta
                                                    .current_page,
                                            last: props.supportRequests.meta.last_page,
                                        },
                                    )
                                }}
                            </p>
                            <div class="flex gap-3">
                                <Button
                                    variant="outline"
                                    class="rounded-2xl"
                                    :disabled="
                                        !props.supportRequests.links.prev
                                    "
                                    as-child
                                >
                                    <Link
                                        :href="
                                            props.supportRequests.links.prev ??
                                            '#'
                                        "
                                        preserve-scroll
                                    >
                                        {{
                                            t(
                                                'admin.supportRequestsPage.pagination.previous',
                                            )
                                        }}
                                    </Link>
                                </Button>
                                <Button
                                    variant="outline"
                                    class="rounded-2xl"
                                    :disabled="
                                        !props.supportRequests.links.next
                                    "
                                    as-child
                                >
                                    <Link
                                        :href="
                                            props.supportRequests.links.next ??
                                            '#'
                                        "
                                        preserve-scroll
                                    >
                                        {{
                                            t(
                                                'admin.supportRequestsPage.pagination.next',
                                            )
                                        }}
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
