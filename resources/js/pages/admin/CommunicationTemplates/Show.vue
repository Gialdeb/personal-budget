<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import Heading from '@/components/Heading.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AdminLayout from '@/layouts/admin/Layout.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { index as adminIndex } from '@/routes/admin';
import {
    edit as editCommunicationTemplate,
    index as communicationTemplatesIndex,
} from '@/routes/admin/communication-templates';
import { disable as disableGlobalOverride } from '@/routes/admin/communication-templates/global-override';
import type {
    AdminCommunicationTemplatesShowPageProps,
    BreadcrumbItem,
} from '@/types';

const props = defineProps<AdminCommunicationTemplatesShowPageProps>();
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
        title: t('admin.sections.communicationTemplates'),
        href: communicationTemplatesIndex(),
    },
    {
        title: t('admin.communicationTemplates.breadcrumbDetail'),
        href: communicationTemplatesIndex(),
    },
];

const feedback = computed(() => {
    if (flash.value.error) {
        return {
            variant: 'destructive' as const,
            title: t('admin.communicationTemplates.feedback.errorTitle'),
            message: flash.value.error,
        };
    }

    const firstError = Object.values(pageErrors.value)[0];

    if (firstError) {
        return {
            variant: 'destructive' as const,
            title: t('admin.communicationTemplates.feedback.errorTitle'),
            message: firstError,
        };
    }

    if (flash.value.success) {
        return {
            variant: 'default' as const,
            title: t('admin.communicationTemplates.feedback.successTitle'),
            message: flash.value.success,
        };
    }

    return null;
});

const valueGroups = computed(() => [
    {
        title: t('admin.communicationTemplates.detail.sections.base'),
        values: props.template.base_template,
    },
    {
        title: t('admin.communicationTemplates.detail.sections.override'),
        values: props.template.global_override,
    },
    {
        title: t('admin.communicationTemplates.detail.sections.resolved'),
        values: props.template.resolved_content,
    },
]);

function valueLabel(key: string): string {
    const map: Record<string, string> = {
        subject_template: t(
            'admin.communicationTemplates.detail.labels.subject',
        ),
        title_template: t('admin.communicationTemplates.detail.labels.title'),
        body_template: t('admin.communicationTemplates.detail.labels.body'),
        cta_label_template: t(
            'admin.communicationTemplates.detail.labels.ctaLabel',
        ),
        cta_url_template: t(
            'admin.communicationTemplates.detail.labels.ctaUrl',
        ),
    };

    return map[key] ?? key;
}

function previewLabel(key: string): string {
    const map: Record<string, string> = {
        subject: t('admin.communicationTemplates.detail.labels.subject'),
        title: t('admin.communicationTemplates.detail.labels.title'),
        body: t('admin.communicationTemplates.detail.labels.body'),
        cta_label: t('admin.communicationTemplates.detail.labels.ctaLabel'),
        cta_url: t('admin.communicationTemplates.detail.labels.ctaUrl'),
    };

    return map[key] ?? key;
}

function displayValue(value: string | null | undefined): string {
    return value && value.trim() !== ''
        ? value
        : t('admin.communicationTemplates.empty.noValue');
}

function disableOverride(): void {
    if (
        !confirm(
            t('admin.communicationTemplates.dialogs.disableDescription', {
                template: props.template.name,
            }),
        )
    ) {
        return;
    }

    router.post(
        disableGlobalOverride({ communicationTemplate: props.template.uuid })
            .url,
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                router.reload({ only: ['template'] });
            },
        },
    );
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="props.template.name" />

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
                                :title="props.template.name"
                                :description="
                                    props.template.description ??
                                    t(
                                        'admin.communicationTemplates.description',
                                    )
                                "
                            />
                            <div class="flex flex-wrap gap-2">
                                <Badge
                                    class="rounded-full border px-3 py-1 text-[11px] uppercase"
                                >
                                    {{ props.template.template_mode_label }}
                                </Badge>
                                <Badge
                                    class="rounded-full border px-3 py-1 text-[11px] uppercase"
                                >
                                    {{ props.template.channel_label }}
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

                        <div class="flex flex-wrap gap-3">
                            <Button
                                class="rounded-xl"
                                :disabled="
                                    !props.template.flags.can_edit_override
                                "
                                as-child
                            >
                                <Link
                                    :href="
                                        editCommunicationTemplate({
                                            communicationTemplate:
                                                props.template.uuid,
                                        })
                                    "
                                >
                                    {{
                                        t(
                                            'admin.communicationTemplates.actions.editOverride',
                                        )
                                    }}
                                </Link>
                            </Button>
                            <Button
                                variant="outline"
                                class="rounded-xl"
                                :disabled="
                                    !props.template.flags.can_disable_override
                                "
                                @click="disableOverride"
                            >
                                {{
                                    t(
                                        'admin.communicationTemplates.actions.disableOverride',
                                    )
                                }}
                            </Button>
                            <Button variant="ghost" class="rounded-xl" as-child>
                                <Link :href="communicationTemplatesIndex()">
                                    {{
                                        t(
                                            'admin.communicationTemplates.actions.backToTemplates',
                                        )
                                    }}
                                </Link>
                            </Button>
                        </div>

                        <div class="grid gap-4 xl:grid-cols-2">
                            <Card
                                class="rounded-[1.5rem] border-slate-200/80 dark:border-slate-800"
                            >
                                <CardHeader>
                                    <CardTitle>{{
                                        t(
                                            'admin.communicationTemplates.detail.sections.general',
                                        )
                                    }}</CardTitle>
                                </CardHeader>
                                <CardContent class="grid gap-4 sm:grid-cols-2">
                                    <div>
                                        <p
                                            class="text-xs text-slate-500 uppercase"
                                        >
                                            {{
                                                t(
                                                    'admin.communicationTemplates.detail.labels.name',
                                                )
                                            }}
                                        </p>
                                        <p class="mt-1 text-sm font-medium">
                                            {{ props.template.name }}
                                        </p>
                                    </div>
                                    <div>
                                        <p
                                            class="text-xs text-slate-500 uppercase"
                                        >
                                            {{
                                                t(
                                                    'admin.communicationTemplates.detail.labels.key',
                                                )
                                            }}
                                        </p>
                                        <p class="mt-1 font-mono text-sm">
                                            {{ props.template.key }}
                                        </p>
                                    </div>
                                    <div>
                                        <p
                                            class="text-xs text-slate-500 uppercase"
                                        >
                                            {{
                                                t(
                                                    'admin.communicationTemplates.detail.labels.channel',
                                                )
                                            }}
                                        </p>
                                        <p class="mt-1 text-sm font-medium">
                                            {{ props.template.channel_label }}
                                        </p>
                                    </div>
                                    <div>
                                        <p
                                            class="text-xs text-slate-500 uppercase"
                                        >
                                            {{
                                                t(
                                                    'admin.communicationTemplates.detail.labels.templateMode',
                                                )
                                            }}
                                        </p>
                                        <p class="mt-1 text-sm font-medium">
                                            {{
                                                props.template
                                                    .template_mode_label
                                            }}
                                        </p>
                                    </div>
                                    <div>
                                        <p
                                            class="text-xs text-slate-500 uppercase"
                                        >
                                            {{
                                                t(
                                                    'admin.communicationTemplates.detail.labels.topic',
                                                )
                                            }}
                                        </p>
                                        <p class="mt-1 text-sm font-medium">
                                            {{
                                                props.template.topic?.label ??
                                                t(
                                                    'admin.communicationTemplates.empty.noTopic',
                                                )
                                            }}
                                        </p>
                                    </div>
                                    <div>
                                        <p
                                            class="text-xs text-slate-500 uppercase"
                                        >
                                            {{
                                                t(
                                                    'admin.communicationTemplates.detail.labels.lockState',
                                                )
                                            }}
                                        </p>
                                        <p class="mt-1 text-sm font-medium">
                                            {{
                                                props.template.is_system_locked
                                                    ? t(
                                                          'admin.communicationTemplates.badges.locked',
                                                      )
                                                    : t(
                                                          'admin.communicationTemplates.badges.editable',
                                                      )
                                            }}
                                        </p>
                                    </div>
                                </CardContent>
                            </Card>

                            <Card
                                class="rounded-[1.5rem] border-slate-200/80 dark:border-slate-800"
                            >
                                <CardHeader>
                                    <CardTitle>{{
                                        t(
                                            'admin.communicationTemplates.detail.sections.preview',
                                        )
                                    }}</CardTitle>
                                </CardHeader>
                                <CardContent class="space-y-4">
                                    <div
                                        v-for="(value, key) in props.template
                                            .preview"
                                        :key="key"
                                    >
                                        <p
                                            class="text-xs text-slate-500 uppercase"
                                        >
                                            {{ previewLabel(key) }}
                                        </p>
                                        <pre
                                            class="mt-1 rounded-2xl border border-slate-200 bg-slate-50 p-3 text-sm whitespace-pre-wrap dark:border-slate-800 dark:bg-slate-900"
                                            >{{
                                                displayValue(
                                                    String(value ?? ''),
                                                )
                                            }}</pre
                                        >
                                    </div>
                                </CardContent>
                            </Card>
                        </div>

                        <div class="grid gap-4 xl:grid-cols-3">
                            <Card
                                v-for="group in valueGroups"
                                :key="group.title"
                                class="rounded-[1.5rem] border-slate-200/80 dark:border-slate-800"
                            >
                                <CardHeader>
                                    <CardTitle>{{ group.title }}</CardTitle>
                                </CardHeader>
                                <CardContent class="space-y-4">
                                    <template v-if="group.values">
                                        <div
                                            v-for="(value, key) in group.values"
                                            :key="key"
                                        >
                                            <p
                                                v-if="
                                                    ![
                                                        'uuid',
                                                        'scope',
                                                        'is_active',
                                                    ].includes(key)
                                                "
                                                class="text-xs text-slate-500 uppercase"
                                            >
                                                {{ valueLabel(key) }}
                                            </p>
                                            <pre
                                                v-if="
                                                    ![
                                                        'uuid',
                                                        'scope',
                                                        'is_active',
                                                    ].includes(key)
                                                "
                                                class="mt-1 rounded-2xl border border-slate-200 bg-slate-50 p-3 text-sm whitespace-pre-wrap dark:border-slate-800 dark:bg-slate-900"
                                                >{{
                                                    displayValue(
                                                        String(value ?? ''),
                                                    )
                                                }}</pre
                                            >
                                        </div>
                                        <div v-if="'is_active' in group.values">
                                            <p
                                                class="text-xs text-slate-500 uppercase"
                                            >
                                                {{
                                                    t(
                                                        'admin.communicationTemplates.detail.labels.overrideState',
                                                    )
                                                }}
                                            </p>
                                            <p class="mt-1 text-sm font-medium">
                                                {{
                                                    group.values.is_active
                                                        ? t(
                                                              'admin.communicationTemplates.badges.overrideActive',
                                                          )
                                                        : t(
                                                              'admin.communicationTemplates.badges.overrideInactive',
                                                          )
                                                }}
                                            </p>
                                        </div>
                                    </template>
                                    <p
                                        v-else
                                        class="text-sm text-slate-500 dark:text-slate-400"
                                    >
                                        {{
                                            t(
                                                'admin.communicationTemplates.empty.noOverride',
                                            )
                                        }}
                                    </p>
                                </CardContent>
                            </Card>
                        </div>

                        <Card
                            class="rounded-[1.5rem] border-slate-200/80 dark:border-slate-800"
                        >
                            <CardHeader>
                                <CardTitle>{{
                                    t(
                                        'admin.communicationTemplates.edit.sections.variables',
                                    )
                                }}</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div
                                    v-if="
                                        props.template.available_variables
                                            .length > 0
                                    "
                                    class="flex flex-wrap gap-2"
                                >
                                    <Badge
                                        v-for="variable in props.template
                                            .available_variables"
                                        :key="variable"
                                        class="rounded-full border border-sky-200 bg-sky-50 px-3 py-1 text-[11px] text-sky-900 uppercase dark:border-sky-500/20 dark:bg-sky-500/10 dark:text-sky-100"
                                    >
                                        {{ variable }}
                                    </Badge>
                                </div>
                                <p
                                    v-else
                                    class="text-sm text-slate-500 dark:text-slate-400"
                                >
                                    {{
                                        t(
                                            'admin.communicationTemplates.edit.variablesEmpty',
                                        )
                                    }}
                                </p>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </section>
        </AdminLayout>
    </AppLayout>
</template>
