<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowRight, Search, Settings2 } from 'lucide-vue-next';
import { computed, onUnmounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AdminLayout from '@/layouts/admin/Layout.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { index as adminIndex } from '@/routes/admin';
import {
    index as communicationCategoriesIndex,
    show as showCommunicationCategory,
} from '@/routes/admin/communication-categories';
import type {
    AdminCommunicationCategoriesIndexPageProps,
    AdminCommunicationCategoryChannelOption,
    AdminCommunicationCategoryItem,
    BreadcrumbItem,
} from '@/types';

const props = defineProps<AdminCommunicationCategoriesIndexPageProps>();
const { t } = useI18n();

const breadcrumbItems: BreadcrumbItem[] = [
    { title: t('admin.sections.overview'), href: adminIndex() },
    {
        title: t('admin.sections.communicationCategories'),
        href: communicationCategoriesIndex(),
    },
];

const search = ref(props.filters.search);
let filterTimeout: ReturnType<typeof setTimeout> | null = null;

const listSummary = computed(() => {
    if (props.categories.meta.total === 0) {
        return t('admin.communicationCategories.list.emptySummary');
    }

    return t('admin.communicationCategories.list.summary', {
        from: props.categories.meta.from ?? 0,
        to: props.categories.meta.to ?? 0,
        total: props.categories.meta.total,
    });
});

watch(search, () => {
    if (filterTimeout) {
        clearTimeout(filterTimeout);
    }

    filterTimeout = setTimeout(() => {
        router.get(
            communicationCategoriesIndex.url({
                query: {
                    search:
                        search.value.trim() === '' ? null : search.value.trim(),
                },
            }),
            {},
            {
                preserveScroll: true,
                preserveState: true,
                replace: true,
            },
        );
    }, 250);
});

onUnmounted(() => {
    if (filterTimeout) {
        clearTimeout(filterTimeout);
    }
});

function resetFilters(): void {
    search.value = '';
}

function channelBadgeClass(
    channel: AdminCommunicationCategoryChannelOption,
): string {
    if (channel.is_supported) {
        return 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-300';
    }

    if (!channel.is_globally_available) {
        return 'border-slate-200 bg-slate-100 text-slate-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-400';
    }

    return 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-300';
}

function categoryStatus(item: AdminCommunicationCategoryItem): string {
    const activeChannels = item.channels.filter(
        (channel) => channel.is_supported,
    ).length;

    if (activeChannels === 0) {
        return t('admin.communicationCategories.status.noActiveChannels');
    }

    return t('admin.communicationCategories.status.activeChannels', {
        count: activeChannels,
    });
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="t('admin.communicationCategories.title')" />

        <AdminLayout>
            <section class="space-y-6">
                <div
                    class="overflow-hidden rounded-[2rem] border border-slate-200/80 bg-white/95 shadow-[0_30px_90px_-50px_rgba(15,23,42,0.45)] backdrop-blur dark:border-slate-800 dark:bg-slate-950/85"
                >
                    <div
                        class="border-b border-slate-200/70 bg-gradient-to-r from-sky-500/10 via-emerald-500/10 to-amber-500/10 px-6 py-6 dark:border-slate-800"
                    >
                        <div
                            class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between"
                        >
                            <Heading
                                variant="small"
                                :title="
                                    t('admin.communicationCategories.title')
                                "
                                :description="
                                    t(
                                        'admin.communicationCategories.description',
                                    )
                                "
                            />
                            <Badge
                                class="rounded-full border border-slate-200 bg-white/90 px-3 py-1 text-[11px] tracking-[0.18em] uppercase dark:border-slate-700 dark:bg-slate-900/80"
                            >
                                {{ listSummary }}
                            </Badge>
                        </div>
                    </div>

                    <div class="space-y-6 px-6 py-6">
                        <Card
                            class="rounded-[1.5rem] border-slate-200/80 bg-slate-50/70 shadow-none dark:border-slate-800 dark:bg-slate-900/50"
                        >
                            <CardHeader class="gap-2">
                                <CardTitle class="text-base">
                                    {{
                                        t(
                                            'admin.communicationCategories.filters.title',
                                        )
                                    }}
                                </CardTitle>
                                <p
                                    class="text-sm leading-6 text-slate-600 dark:text-slate-300"
                                >
                                    {{
                                        t(
                                            'admin.communicationCategories.filters.description',
                                        )
                                    }}
                                </p>
                            </CardHeader>
                            <CardContent
                                class="grid gap-4 md:grid-cols-[minmax(0,1fr)_auto]"
                            >
                                <div>
                                    <Label for="category-search">
                                        {{
                                            t(
                                                'admin.communicationCategories.filters.searchLabel',
                                            )
                                        }}
                                    </Label>
                                    <div class="relative mt-2">
                                        <Search
                                            class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-slate-400"
                                        />
                                        <Input
                                            id="category-search"
                                            v-model="search"
                                            class="pl-9"
                                            :placeholder="
                                                t(
                                                    'admin.communicationCategories.filters.searchPlaceholder',
                                                )
                                            "
                                        />
                                    </div>
                                </div>

                                <div class="flex items-end">
                                    <Button
                                        variant="outline"
                                        class="w-full rounded-xl"
                                        @click="resetFilters"
                                    >
                                        {{
                                            t(
                                                'admin.communicationCategories.filters.reset',
                                            )
                                        }}
                                    </Button>
                                </div>
                            </CardContent>
                        </Card>

                        <div
                            v-if="props.categories.data.length === 0"
                            class="rounded-[1.5rem] border border-dashed border-slate-300/90 bg-slate-50/80 p-8 text-center dark:border-slate-700 dark:bg-slate-900/60"
                        >
                            <Settings2 class="mx-auto h-8 w-8 text-slate-400" />
                            <h3
                                class="mt-3 text-sm font-semibold text-slate-950 dark:text-slate-50"
                            >
                                {{
                                    t(
                                        'admin.communicationCategories.empty.title',
                                    )
                                }}
                            </h3>
                            <p
                                class="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-300"
                            >
                                {{
                                    t(
                                        'admin.communicationCategories.empty.description',
                                    )
                                }}
                            </p>
                        </div>

                        <div v-else class="grid gap-4 xl:grid-cols-2">
                            <Card
                                v-for="category in props.categories.data"
                                :key="category.uuid"
                                class="rounded-[1.5rem] border-slate-200/80 shadow-none dark:border-slate-800"
                            >
                                <CardHeader class="space-y-4">
                                    <div
                                        class="flex items-start justify-between gap-3"
                                    >
                                        <div>
                                            <CardTitle class="text-base">
                                                {{ category.name }}
                                            </CardTitle>
                                            <p
                                                class="mt-1 text-xs text-slate-500 dark:text-slate-400"
                                            >
                                                {{ category.key }}
                                            </p>
                                        </div>
                                        <Badge
                                            class="rounded-full border px-3 py-1 text-[11px] uppercase"
                                        >
                                            {{ categoryStatus(category) }}
                                        </Badge>
                                    </div>
                                    <p
                                        class="text-sm leading-6 text-slate-600 dark:text-slate-300"
                                    >
                                        {{
                                            category.description ??
                                            t(
                                                'admin.communicationCategories.empty.noDescription',
                                            )
                                        }}
                                    </p>
                                </CardHeader>
                                <CardContent class="space-y-4">
                                    <div
                                        class="grid gap-3 text-sm text-slate-600 sm:grid-cols-2 dark:text-slate-300"
                                    >
                                        <div>
                                            <p
                                                class="text-xs tracking-[0.16em] text-slate-400 uppercase"
                                            >
                                                {{
                                                    t(
                                                        'admin.communicationCategories.labels.deliveryMode',
                                                    )
                                                }}
                                            </p>
                                            <p
                                                class="mt-1 font-medium text-slate-950 dark:text-slate-50"
                                            >
                                                {{
                                                    t(
                                                        `admin.communicationCategories.deliveryModes.${category.delivery_mode}`,
                                                    )
                                                }}
                                            </p>
                                        </div>
                                        <div>
                                            <p
                                                class="text-xs tracking-[0.16em] text-slate-400 uppercase"
                                            >
                                                {{
                                                    t(
                                                        'admin.communicationCategories.labels.preferenceMode',
                                                    )
                                                }}
                                            </p>
                                            <p
                                                class="mt-1 font-medium text-slate-950 dark:text-slate-50"
                                            >
                                                {{
                                                    t(
                                                        `admin.communicationCategories.preferenceModes.${category.preference_mode}`,
                                                    )
                                                }}
                                            </p>
                                        </div>
                                    </div>

                                    <div class="flex flex-wrap gap-2">
                                        <Badge
                                            v-for="channel in category.channels"
                                            :key="`${category.uuid}-${channel.value}`"
                                            :class="[
                                                'rounded-full border px-3 py-1 text-[11px] uppercase',
                                                channelBadgeClass(channel),
                                            ]"
                                        >
                                            {{ channel.label }}
                                        </Badge>
                                    </div>

                                    <Button
                                        variant="outline"
                                        class="w-full rounded-xl"
                                        as-child
                                    >
                                        <Link
                                            :href="
                                                showCommunicationCategory({
                                                    communicationCategory:
                                                        category.uuid,
                                                })
                                            "
                                        >
                                            {{
                                                t(
                                                    'admin.communicationCategories.actions.manageChannels',
                                                )
                                            }}
                                            <ArrowRight class="ml-2 h-4 w-4" />
                                        </Link>
                                    </Button>
                                </CardContent>
                            </Card>
                        </div>

                        <div
                            v-if="props.categories.meta.last_page > 1"
                            class="flex flex-col gap-3 border-t border-slate-200 pt-4 md:flex-row md:items-center md:justify-between dark:border-slate-800"
                        >
                            <p
                                class="text-sm text-slate-600 dark:text-slate-300"
                            >
                                {{
                                    t(
                                        'admin.communicationCategories.pagination.page',
                                        {
                                            current:
                                                props.categories.meta
                                                    .current_page,
                                            last: props.categories.meta
                                                .last_page,
                                        },
                                    )
                                }}
                            </p>
                            <div class="flex items-center gap-2">
                                <Button
                                    variant="outline"
                                    class="rounded-xl"
                                    :disabled="!props.categories.links.prev"
                                    @click="
                                        props.categories.links.prev &&
                                        router.visit(
                                            props.categories.links.prev,
                                            {
                                                preserveScroll: true,
                                                preserveState: true,
                                            },
                                        )
                                    "
                                >
                                    {{
                                        t(
                                            'admin.communicationCategories.pagination.previous',
                                        )
                                    }}
                                </Button>
                                <Button
                                    variant="outline"
                                    class="rounded-xl"
                                    :disabled="!props.categories.links.next"
                                    @click="
                                        props.categories.links.next &&
                                        router.visit(
                                            props.categories.links.next,
                                            {
                                                preserveScroll: true,
                                                preserveState: true,
                                            },
                                        )
                                    "
                                >
                                    {{
                                        t(
                                            'admin.communicationCategories.pagination.next',
                                        )
                                    }}
                                </Button>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </AdminLayout>
    </AppLayout>
</template>
