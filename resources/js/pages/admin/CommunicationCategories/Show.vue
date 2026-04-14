<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';
import { useI18n } from 'vue-i18n';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AdminLayout from '@/layouts/admin/Layout.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { index as adminIndex } from '@/routes/admin';
import {
    index as communicationCategoriesIndex,
    show as showCommunicationCategory,
} from '@/routes/admin/communication-categories';
import { update as updateCommunicationCategoryChannels } from '@/routes/admin/communication-categories/channels';
import type {
    AdminCommunicationCategoriesShowPageProps,
    AdminCommunicationCategoryChannelOption,
    BreadcrumbItem,
} from '@/types';

type ChannelFormState = {
    value: string;
    enabled: boolean;
    template_uuid: string;
};

const props = defineProps<AdminCommunicationCategoriesShowPageProps>();
const { t } = useI18n();
const page = usePage();

const flash = computed(
    () =>
        (page.props.flash ?? {}) as {
            success?: string | null;
            error?: string | null;
        },
);
const pageErrors = computed(
    () => (page.props.errors ?? {}) as Record<string, string | undefined>,
);

const breadcrumbItems: BreadcrumbItem[] = [
    { title: t('admin.sections.overview'), href: adminIndex() },
    {
        title: t('admin.sections.communicationCategories'),
        href: communicationCategoriesIndex(),
    },
    {
        title: props.category.name,
        href: showCommunicationCategory({
            communicationCategory: props.category.uuid,
        }),
    },
];

const channelForms = reactive<Record<string, ChannelFormState>>(
    Object.fromEntries(
        props.category.channels.map((channel) => [
            channel.value,
            {
                value: channel.value,
                enabled: channel.is_supported,
                template_uuid: channel.template?.uuid ?? '',
            },
        ]),
    ),
);

const feedback = computed(() => {
    if (flash.value.error) {
        return {
            variant: 'destructive' as const,
            title: t('admin.communicationCategories.feedback.errorTitle'),
            message: flash.value.error,
        };
    }

    if (flash.value.success) {
        return {
            variant: 'default' as const,
            title: t('admin.communicationCategories.feedback.successTitle'),
            message: flash.value.success,
        };
    }

    return null;
});

function channelCardClass(
    channel: AdminCommunicationCategoryChannelOption,
): string {
    if (channel.is_supported) {
        return 'border-emerald-200/80 bg-emerald-50/70 dark:border-emerald-500/20 dark:bg-emerald-500/10';
    }

    if (!channel.is_globally_available) {
        return 'border-slate-200/80 bg-slate-50/70 dark:border-slate-800 dark:bg-slate-900/60';
    }

    return 'border-amber-200/80 bg-amber-50/70 dark:border-amber-500/20 dark:bg-amber-500/10';
}

function channelStatusLabel(
    channel: AdminCommunicationCategoryChannelOption,
): string {
    if (channel.is_fixed) {
        return t('admin.communicationCategories.channelState.fixed');
    }

    if (channel.is_supported) {
        return t('admin.communicationCategories.channelState.enabled');
    }

    if (!channel.is_globally_available) {
        return t(
            'admin.communicationCategories.channelState.globallyUnavailable',
        );
    }

    return t('admin.communicationCategories.channelState.disabled');
}

function save(): void {
    router.patch(
        updateCommunicationCategoryChannels({
            communicationCategory: props.category.uuid,
        }).url,
        {
            channels: Object.values(channelForms).map((channel) => ({
                value: channel.value,
                enabled: channel.enabled,
                template_uuid:
                    channel.template_uuid.trim() === ''
                        ? null
                        : channel.template_uuid,
            })),
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                router.reload({ only: ['category'] });
            },
        },
    );
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="props.category.name" />

        <AdminLayout>
            <section class="space-y-6">
                <div
                    class="overflow-hidden rounded-[2rem] border border-slate-200/80 bg-white/95 shadow-[0_30px_90px_-50px_rgba(15,23,42,0.45)] backdrop-blur dark:border-slate-800 dark:bg-slate-950/85"
                >
                    <div
                        class="border-b border-slate-200/70 px-6 py-6 dark:border-slate-800"
                    >
                        <div
                            class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between"
                        >
                            <Heading
                                variant="small"
                                :title="props.category.name"
                                :description="
                                    props.category.description ??
                                    t(
                                        'admin.communicationCategories.empty.noDescription',
                                    )
                                "
                            />
                            <div class="flex flex-wrap gap-2">
                                <Badge
                                    class="rounded-full border px-3 py-1 text-[11px] uppercase"
                                >
                                    {{
                                        t(
                                            `admin.communicationCategories.deliveryModes.${props.category.delivery_mode}`,
                                        )
                                    }}
                                </Badge>
                                <Badge
                                    class="rounded-full border px-3 py-1 text-[11px] uppercase"
                                >
                                    {{
                                        t(
                                            `admin.communicationCategories.preferenceModes.${props.category.preference_mode}`,
                                        )
                                    }}
                                </Badge>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-6 px-6 py-6">
                        <Alert v-if="feedback" :variant="feedback.variant">
                            <AlertTitle>{{ feedback.title }}</AlertTitle>
                            <AlertDescription>{{
                                feedback.message
                            }}</AlertDescription>
                        </Alert>

                        <div class="grid gap-4 xl:grid-cols-3">
                            <Card
                                class="rounded-[1.5rem] border-slate-200/80 dark:border-slate-800"
                            >
                                <CardHeader>
                                    <CardTitle>{{
                                        t(
                                            'admin.communicationCategories.sections.general',
                                        )
                                    }}</CardTitle>
                                </CardHeader>
                                <CardContent
                                    class="grid gap-3 text-sm text-slate-600 dark:text-slate-300"
                                >
                                    <div>
                                        <p
                                            class="text-xs tracking-[0.16em] text-slate-400 uppercase"
                                        >
                                            {{
                                                t(
                                                    'admin.communicationCategories.labels.key',
                                                )
                                            }}
                                        </p>
                                        <p
                                            class="mt-1 font-medium text-slate-950 dark:text-slate-50"
                                        >
                                            {{ props.category.key }}
                                        </p>
                                    </div>
                                    <div>
                                        <p
                                            class="text-xs tracking-[0.16em] text-slate-400 uppercase"
                                        >
                                            {{
                                                t(
                                                    'admin.communicationCategories.labels.contextType',
                                                )
                                            }}
                                        </p>
                                        <p
                                            class="mt-1 font-medium text-slate-950 dark:text-slate-50"
                                        >
                                            {{ props.category.context_type }}
                                        </p>
                                    </div>
                                    <div>
                                        <p
                                            class="text-xs tracking-[0.16em] text-slate-400 uppercase"
                                        >
                                            {{
                                                t(
                                                    'admin.communicationCategories.labels.fixedChannel',
                                                )
                                            }}
                                        </p>
                                        <p
                                            class="mt-1 font-medium text-slate-950 dark:text-slate-50"
                                        >
                                            {{
                                                props.category.fixed_channel
                                                    ? t(
                                                          `admin.communicationCategories.channels.${props.category.fixed_channel}`,
                                                      )
                                                    : t(
                                                          'admin.communicationCategories.empty.noFixedChannel',
                                                      )
                                            }}
                                        </p>
                                    </div>
                                </CardContent>
                            </Card>

                            <Card
                                class="rounded-[1.5rem] border-slate-200/80 xl:col-span-2 dark:border-slate-800"
                            >
                                <CardHeader>
                                    <CardTitle>{{
                                        t(
                                            'admin.communicationCategories.sections.channelRules',
                                        )
                                    }}</CardTitle>
                                </CardHeader>
                                <CardContent class="grid gap-3 md:grid-cols-2">
                                    <div
                                        class="rounded-2xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-900/70"
                                    >
                                        <p
                                            class="text-sm font-semibold text-slate-950 dark:text-slate-50"
                                        >
                                            {{
                                                t(
                                                    'admin.communicationCategories.flags.manualSend',
                                                )
                                            }}
                                        </p>
                                        <p
                                            class="mt-2 text-sm text-slate-600 dark:text-slate-300"
                                        >
                                            {{
                                                props.category.flags
                                                    .available_for_manual_send
                                                    ? t(
                                                          'admin.communicationCategories.flags.enabled',
                                                      )
                                                    : t(
                                                          'admin.communicationCategories.flags.disabled',
                                                      )
                                            }}
                                        </p>
                                    </div>
                                    <div
                                        class="rounded-2xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-900/70"
                                    >
                                        <p
                                            class="text-sm font-semibold text-slate-950 dark:text-slate-50"
                                        >
                                            {{
                                                t(
                                                    'admin.communicationCategories.flags.automaticDispatch',
                                                )
                                            }}
                                        </p>
                                        <p
                                            class="mt-2 text-sm text-slate-600 dark:text-slate-300"
                                        >
                                            {{
                                                props.category.flags
                                                    .has_active_dispatch_channels
                                                    ? t(
                                                          'admin.communicationCategories.flags.enabled',
                                                      )
                                                    : t(
                                                          'admin.communicationCategories.flags.disabled',
                                                      )
                                            }}
                                        </p>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>

                        <Card
                            class="rounded-[1.5rem] border-slate-200/80 dark:border-slate-800"
                        >
                            <CardHeader class="gap-2">
                                <CardTitle>{{
                                    t(
                                        'admin.communicationCategories.sections.channels',
                                    )
                                }}</CardTitle>
                                <p
                                    class="text-sm leading-6 text-slate-600 dark:text-slate-300"
                                >
                                    {{
                                        t(
                                            'admin.communicationCategories.sections.channelsDescription',
                                        )
                                    }}
                                </p>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <div class="grid gap-4">
                                    <div
                                        v-for="channel in props.category
                                            .channels"
                                        :key="channel.value"
                                        :class="[
                                            'rounded-[1.25rem] border p-5',
                                            channelCardClass(channel),
                                        ]"
                                    >
                                        <div
                                            class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between"
                                        >
                                            <div>
                                                <div
                                                    class="flex flex-wrap items-center gap-2"
                                                >
                                                    <h3
                                                        class="text-sm font-semibold text-slate-950 dark:text-slate-50"
                                                    >
                                                        {{ channel.label }}
                                                    </h3>
                                                    <Badge
                                                        class="rounded-full border px-3 py-1 text-[11px] uppercase"
                                                    >
                                                        {{
                                                            channelStatusLabel(
                                                                channel,
                                                            )
                                                        }}
                                                    </Badge>
                                                </div>
                                                <p
                                                    class="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-300"
                                                >
                                                    {{
                                                        channel.is_globally_available
                                                            ? t(
                                                                  'admin.communicationCategories.channelHints.globallyAvailable',
                                                              )
                                                            : t(
                                                                  'admin.communicationCategories.channelHints.globallyUnavailable',
                                                              )
                                                    }}
                                                </p>
                                            </div>

                                            <div
                                                class="grid gap-4 md:grid-cols-[auto_minmax(0,280px)] md:items-end"
                                            >
                                                <div
                                                    class="flex items-center gap-3"
                                                >
                                                    <Checkbox
                                                        :id="`channel-enabled-${channel.value}`"
                                                        :model-value="
                                                            channelForms[
                                                                channel.value
                                                            ].enabled
                                                        "
                                                        :disabled="
                                                            channel.is_fixed ||
                                                            !channel.is_globally_available
                                                        "
                                                        @update:model-value="
                                                            channelForms[
                                                                channel.value
                                                            ].enabled =
                                                                Boolean($event)
                                                        "
                                                    />
                                                    <Label
                                                        :for="`channel-enabled-${channel.value}`"
                                                    >
                                                        {{
                                                            t(
                                                                'admin.communicationCategories.form.enableChannel',
                                                            )
                                                        }}
                                                    </Label>
                                                </div>

                                                <div>
                                                    <Label>{{
                                                        t(
                                                            'admin.communicationCategories.form.template',
                                                        )
                                                    }}</Label>
                                                    <Select
                                                        v-model="
                                                            channelForms[
                                                                channel.value
                                                            ].template_uuid
                                                        "
                                                        :disabled="
                                                            !channel.is_globally_available ||
                                                            channel.is_fixed ||
                                                            !channelForms[
                                                                channel.value
                                                            ].enabled
                                                        "
                                                    >
                                                        <SelectTrigger
                                                            class="mt-2"
                                                        >
                                                            <SelectValue
                                                                :placeholder="
                                                                    t(
                                                                        'admin.communicationCategories.form.templatePlaceholder',
                                                                    )
                                                                "
                                                            />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            <SelectItem
                                                                v-for="template in channel.template_options"
                                                                :key="
                                                                    template.uuid
                                                                "
                                                                :value="
                                                                    template.uuid
                                                                "
                                                            >
                                                                {{
                                                                    template.name
                                                                }}
                                                            </SelectItem>
                                                        </SelectContent>
                                                    </Select>
                                                    <InputError
                                                        :message="
                                                            pageErrors[
                                                                `channels.${props.category.channels.findIndex((entry) => entry.value === channel.value)}.template_uuid`
                                                            ]
                                                        "
                                                    />
                                                </div>
                                            </div>
                                        </div>

                                        <div
                                            class="mt-4 grid gap-3 text-sm text-slate-600 md:grid-cols-2 dark:text-slate-300"
                                        >
                                            <div>
                                                <p
                                                    class="text-xs tracking-[0.16em] text-slate-400 uppercase"
                                                >
                                                    {{
                                                        t(
                                                            'admin.communicationCategories.labels.currentTemplate',
                                                        )
                                                    }}
                                                </p>
                                                <p
                                                    class="mt-1 font-medium text-slate-950 dark:text-slate-50"
                                                >
                                                    {{
                                                        channel.template
                                                            ?.name ??
                                                        t(
                                                            'admin.communicationCategories.empty.noTemplate',
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
                                                            'admin.communicationCategories.labels.globalAvailability',
                                                        )
                                                    }}
                                                </p>
                                                <p
                                                    class="mt-1 font-medium text-slate-950 dark:text-slate-50"
                                                >
                                                    {{
                                                        channel.is_globally_available
                                                            ? t(
                                                                  'admin.communicationCategories.flags.enabled',
                                                              )
                                                            : t(
                                                                  'admin.communicationCategories.flags.disabled',
                                                              )
                                                    }}
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div
                                    class="flex flex-wrap gap-3 border-t border-slate-200 pt-4 dark:border-slate-800"
                                >
                                    <Button class="rounded-xl" @click="save">
                                        {{
                                            t(
                                                'admin.communicationCategories.actions.saveChannels',
                                            )
                                        }}
                                    </Button>
                                    <Button
                                        variant="outline"
                                        class="rounded-xl"
                                        as-child
                                    >
                                        <Link
                                            :href="
                                                communicationCategoriesIndex()
                                            "
                                        >
                                            {{
                                                t(
                                                    'admin.communicationCategories.actions.backToCategories',
                                                )
                                            }}
                                        </Link>
                                    </Button>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </section>
        </AdminLayout>
    </AppLayout>
</template>
