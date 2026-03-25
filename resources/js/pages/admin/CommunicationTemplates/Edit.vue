<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AdminLayout from '@/layouts/admin/Layout.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { index as adminIndex } from '@/routes/admin';
import {
    index as communicationTemplatesIndex,
    show as showCommunicationTemplate,
} from '@/routes/admin/communication-templates';
import {
    disable as disableGlobalOverride,
    update as updateGlobalOverride,
} from '@/routes/admin/communication-templates/global-override';
import type {
    AdminCommunicationTemplatesEditPageProps,
    BreadcrumbItem,
    CommunicationTemplateFields,
} from '@/types';

const props = defineProps<AdminCommunicationTemplatesEditPageProps>();
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

const form = useForm({
    subject_template: props.template.global_override?.subject_template ?? '',
    title_template: props.template.global_override?.title_template ?? '',
    body_template: props.template.global_override?.body_template ?? '',
    cta_label_template:
        props.template.global_override?.cta_label_template ?? '',
    cta_url_template: props.template.global_override?.cta_url_template ?? '',
    is_active: props.template.global_override?.is_active ?? true,
});

const breadcrumbItems: BreadcrumbItem[] = [
    { title: t('admin.sections.overview'), href: adminIndex() },
    {
        title: t('admin.sections.communicationTemplates'),
        href: communicationTemplatesIndex(),
    },
    {
        title: props.template.name,
        href: showCommunicationTemplate({
            communicationTemplate: props.template.uuid,
        }),
    },
    {
        title: t('admin.communicationTemplates.breadcrumbEdit'),
        href: showCommunicationTemplate({
            communicationTemplate: props.template.uuid,
        }),
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

const isLocked = computed(
    () =>
        props.template.is_system_locked ||
        !props.template.flags.can_edit_override,
);

const normalizedForm = computed<
    CommunicationTemplateFields & { is_active: boolean }
>(() => ({
    subject_template:
        form.subject_template.trim() === ''
            ? null
            : form.subject_template.trim(),
    title_template:
        form.title_template.trim() === '' ? null : form.title_template.trim(),
    body_template:
        form.body_template.trim() === '' ? null : form.body_template.trim(),
    cta_label_template:
        form.cta_label_template.trim() === ''
            ? null
            : form.cta_label_template.trim(),
    cta_url_template:
        form.cta_url_template.trim() === ''
            ? null
            : form.cta_url_template.trim(),
    is_active: form.is_active,
}));

const resolvedFields = computed<CommunicationTemplateFields>(() => ({
    subject_template:
        normalizedForm.value.subject_template ??
        props.template.base_template.subject_template,
    title_template:
        normalizedForm.value.title_template ??
        props.template.base_template.title_template,
    body_template:
        normalizedForm.value.body_template ??
        props.template.base_template.body_template,
    cta_label_template:
        normalizedForm.value.cta_label_template ??
        props.template.base_template.cta_label_template,
    cta_url_template:
        normalizedForm.value.cta_url_template ??
        props.template.base_template.cta_url_template,
}));

const livePreview = computed(() => ({
    subject:
        normalizedForm.value.subject_template ?? props.template.preview.subject,
    title: normalizedForm.value.title_template ?? props.template.preview.title,
    body: normalizedForm.value.body_template ?? props.template.preview.body,
    cta_label:
        normalizedForm.value.cta_label_template ??
        props.template.preview.cta_label,
    cta_url:
        normalizedForm.value.cta_url_template ?? props.template.preview.cta_url,
}));

const hasSavedOverride = computed(
    () => props.template.global_override !== null,
);

function displayValue(value: string | null | undefined): string {
    return value && value.trim() !== ''
        ? value
        : t('admin.communicationTemplates.empty.noValue');
}

function submit(): void {
    form.transform((data) => ({
        ...data,
        subject_template:
            data.subject_template.trim() === ''
                ? null
                : data.subject_template.trim(),
        title_template:
            data.title_template.trim() === ''
                ? null
                : data.title_template.trim(),
        body_template:
            data.body_template.trim() === '' ? null : data.body_template.trim(),
        cta_label_template:
            data.cta_label_template.trim() === ''
                ? null
                : data.cta_label_template.trim(),
        cta_url_template:
            data.cta_url_template.trim() === ''
                ? null
                : data.cta_url_template.trim(),
    })).patch(
        updateGlobalOverride({ communicationTemplate: props.template.uuid })
            .url,
        {
            preserveScroll: true,
        },
    );
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
        },
    );
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="t('admin.communicationTemplates.edit.title')" />

        <AdminLayout>
            <section class="space-y-6">
                <div
                    class="overflow-hidden rounded-[2rem] border border-slate-200/80 bg-white/95 shadow-[0_30px_90px_-50px_rgba(15,23,42,0.45)] backdrop-blur dark:border-slate-800 dark:bg-slate-950/85"
                >
                    <div
                        class="border-b border-slate-200/70 px-6 py-6 dark:border-slate-800"
                    >
                        <div
                            class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between"
                        >
                            <Heading
                                variant="small"
                                :title="props.template.name"
                                :description="
                                    props.template.description ??
                                    t(
                                        'admin.communicationTemplates.edit.description',
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
                                <Badge
                                    class="rounded-full border px-3 py-1 text-[11px] uppercase"
                                    :class="
                                        props.template.global_override
                                            ?.is_active
                                            ? 'border-emerald-200 bg-emerald-50 text-emerald-900 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-100'
                                            : 'border-slate-300 bg-slate-100 text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200'
                                    "
                                >
                                    {{
                                        props.template.global_override
                                            ? props.template.global_override
                                                  .is_active
                                                ? t(
                                                      'admin.communicationTemplates.badges.overrideActive',
                                                  )
                                                : t(
                                                      'admin.communicationTemplates.badges.overrideInactive',
                                                  )
                                            : t(
                                                  'admin.communicationTemplates.badges.overrideMissing',
                                              )
                                    }}
                                </Badge>
                                <Badge
                                    v-if="props.template.is_system_locked"
                                    class="rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-[11px] text-amber-900 uppercase dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-100"
                                >
                                    {{
                                        t(
                                            'admin.communicationTemplates.badges.locked',
                                        )
                                    }}
                                </Badge>
                            </div>
                        </div>

                        <div
                            class="mt-5 flex flex-wrap gap-2 text-sm text-slate-500 dark:text-slate-400"
                        >
                            <span class="font-mono">{{
                                props.template.key
                            }}</span>
                            <span>•</span>
                            <span>{{
                                props.template.topic?.label ??
                                t('admin.communicationTemplates.empty.noTopic')
                            }}</span>
                            <span v-if="isLocked">•</span>
                            <span v-if="isLocked">{{
                                t('admin.communicationTemplates.form.disabled')
                            }}</span>
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
                                :disabled="isLocked || form.processing"
                                @click="submit"
                            >
                                {{
                                    hasSavedOverride
                                        ? t(
                                              'admin.communicationTemplates.actions.saveOverride',
                                          )
                                        : t(
                                              'admin.communicationTemplates.actions.createOverride',
                                          )
                                }}
                            </Button>
                            <Button
                                variant="outline"
                                class="rounded-xl"
                                :disabled="
                                    !props.template.flags
                                        .can_disable_override || form.processing
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
                                <Link
                                    :href="
                                        showCommunicationTemplate({
                                            communicationTemplate:
                                                props.template.uuid,
                                        })
                                    "
                                >
                                    {{
                                        t(
                                            'admin.communicationTemplates.actions.backToDetail',
                                        )
                                    }}
                                </Link>
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

                        <div
                            class="grid gap-6 xl:grid-cols-[minmax(0,1.1fr)_minmax(0,0.9fr)]"
                        >
                            <div class="space-y-6">
                                <Card
                                    class="rounded-[1.5rem] border-slate-200/80 dark:border-slate-800"
                                >
                                    <CardHeader>
                                        <CardTitle>{{
                                            t(
                                                'admin.communicationTemplates.edit.sections.override',
                                            )
                                        }}</CardTitle>
                                    </CardHeader>
                                    <CardContent class="space-y-5">
                                        <div
                                            v-if="isLocked"
                                            class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm leading-6 text-amber-900"
                                        >
                                            {{
                                                t(
                                                    'admin.communicationTemplates.form.disabled',
                                                )
                                            }}
                                        </div>

                                        <div class="space-y-2">
                                            <Label for="subject_template">{{
                                                t(
                                                    'admin.communicationTemplates.form.fields.subject',
                                                )
                                            }}</Label>
                                            <Input
                                                id="subject_template"
                                                v-model="form.subject_template"
                                                :disabled="isLocked"
                                                class="h-11 rounded-2xl border-slate-200 dark:border-slate-800"
                                            />
                                            <p
                                                class="text-xs leading-5 text-slate-500 dark:text-slate-400"
                                            >
                                                {{
                                                    t(
                                                        'admin.communicationTemplates.form.hints.subject',
                                                    )
                                                }}
                                            </p>
                                            <InputError
                                                :message="
                                                    pageErrors.subject_template
                                                "
                                            />
                                        </div>

                                        <div class="space-y-2">
                                            <Label for="title_template">{{
                                                t(
                                                    'admin.communicationTemplates.form.fields.title',
                                                )
                                            }}</Label>
                                            <Input
                                                id="title_template"
                                                v-model="form.title_template"
                                                :disabled="isLocked"
                                                class="h-11 rounded-2xl border-slate-200 dark:border-slate-800"
                                            />
                                            <p
                                                class="text-xs leading-5 text-slate-500 dark:text-slate-400"
                                            >
                                                {{
                                                    t(
                                                        'admin.communicationTemplates.form.hints.title',
                                                    )
                                                }}
                                            </p>
                                            <InputError
                                                :message="
                                                    pageErrors.title_template
                                                "
                                            />
                                        </div>

                                        <div class="space-y-2">
                                            <Label for="body_template">{{
                                                t(
                                                    'admin.communicationTemplates.form.fields.body',
                                                )
                                            }}</Label>
                                            <textarea
                                                id="body_template"
                                                v-model="form.body_template"
                                                :disabled="isLocked"
                                                rows="12"
                                                class="min-h-56 w-full rounded-[1.5rem] border border-slate-200 bg-white px-4 py-3 text-sm leading-6 text-slate-950 transition outline-none focus:border-sky-400 focus:ring-2 focus:ring-sky-200 disabled:cursor-not-allowed disabled:bg-slate-50 dark:border-slate-800 dark:bg-slate-950 dark:text-slate-50 dark:focus:border-sky-500 dark:focus:ring-sky-500/20 dark:disabled:bg-slate-900"
                                            />
                                            <p
                                                class="text-xs leading-5 text-slate-500 dark:text-slate-400"
                                            >
                                                {{
                                                    t(
                                                        'admin.communicationTemplates.form.hints.body',
                                                    )
                                                }}
                                            </p>
                                            <InputError
                                                :message="
                                                    pageErrors.body_template
                                                "
                                            />
                                        </div>

                                        <div class="grid gap-5 lg:grid-cols-2">
                                            <div class="space-y-2">
                                                <Label
                                                    for="cta_label_template"
                                                    >{{
                                                        t(
                                                            'admin.communicationTemplates.form.fields.ctaLabel',
                                                        )
                                                    }}</Label
                                                >
                                                <Input
                                                    id="cta_label_template"
                                                    v-model="
                                                        form.cta_label_template
                                                    "
                                                    :disabled="isLocked"
                                                    class="h-11 rounded-2xl border-slate-200 dark:border-slate-800"
                                                />
                                                <p
                                                    class="text-xs leading-5 text-slate-500 dark:text-slate-400"
                                                >
                                                    {{
                                                        t(
                                                            'admin.communicationTemplates.form.hints.ctaLabel',
                                                        )
                                                    }}
                                                </p>
                                                <InputError
                                                    :message="
                                                        pageErrors.cta_label_template
                                                    "
                                                />
                                            </div>

                                            <div class="space-y-2">
                                                <Label for="cta_url_template">{{
                                                    t(
                                                        'admin.communicationTemplates.form.fields.ctaUrl',
                                                    )
                                                }}</Label>
                                                <Input
                                                    id="cta_url_template"
                                                    v-model="
                                                        form.cta_url_template
                                                    "
                                                    :disabled="isLocked"
                                                    class="h-11 rounded-2xl border-slate-200 dark:border-slate-800"
                                                />
                                                <p
                                                    class="text-xs leading-5 text-slate-500 dark:text-slate-400"
                                                >
                                                    {{
                                                        t(
                                                            'admin.communicationTemplates.form.hints.ctaUrl',
                                                        )
                                                    }}
                                                </p>
                                                <InputError
                                                    :message="
                                                        pageErrors.cta_url_template
                                                    "
                                                />
                                            </div>
                                        </div>

                                        <div
                                            class="flex items-start gap-3 rounded-[1.5rem] border border-slate-200 px-4 py-3 dark:border-slate-800"
                                        >
                                            <Checkbox
                                                id="is_active"
                                                :checked="form.is_active"
                                                :disabled="isLocked"
                                                @update:checked="
                                                    form.is_active =
                                                        Boolean($event)
                                                "
                                            />
                                            <div class="space-y-1">
                                                <Label for="is_active">{{
                                                    t(
                                                        'admin.communicationTemplates.form.fields.isActive',
                                                    )
                                                }}</Label>
                                                <p
                                                    class="text-xs leading-5 text-slate-500 dark:text-slate-400"
                                                >
                                                    {{
                                                        t(
                                                            'admin.communicationTemplates.form.hints.isActive',
                                                        )
                                                    }}
                                                </p>
                                            </div>
                                        </div>
                                        <InputError
                                            :message="pageErrors.is_active"
                                        />
                                    </CardContent>
                                </Card>

                                <Card
                                    class="rounded-[1.5rem] border-slate-200/80 dark:border-slate-800"
                                >
                                    <CardHeader>
                                        <CardTitle>{{
                                            t(
                                                'admin.communicationTemplates.edit.sections.base',
                                            )
                                        }}</CardTitle>
                                    </CardHeader>
                                    <CardContent
                                        class="grid gap-4 lg:grid-cols-2"
                                    >
                                        <div class="space-y-2 lg:col-span-2">
                                            <p
                                                class="text-xs text-slate-500 uppercase"
                                            >
                                                {{
                                                    t(
                                                        'admin.communicationTemplates.detail.labels.subject',
                                                    )
                                                }}
                                            </p>
                                            <pre
                                                class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm whitespace-pre-wrap dark:border-slate-800 dark:bg-slate-900"
                                                >{{
                                                    displayValue(
                                                        props.template
                                                            .base_template
                                                            .subject_template,
                                                    )
                                                }}</pre
                                            >
                                        </div>
                                        <div class="space-y-2">
                                            <p
                                                class="text-xs text-slate-500 uppercase"
                                            >
                                                {{
                                                    t(
                                                        'admin.communicationTemplates.detail.labels.title',
                                                    )
                                                }}
                                            </p>
                                            <pre
                                                class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm whitespace-pre-wrap dark:border-slate-800 dark:bg-slate-900"
                                                >{{
                                                    displayValue(
                                                        props.template
                                                            .base_template
                                                            .title_template,
                                                    )
                                                }}</pre
                                            >
                                        </div>
                                        <div class="space-y-2">
                                            <p
                                                class="text-xs text-slate-500 uppercase"
                                            >
                                                {{
                                                    t(
                                                        'admin.communicationTemplates.detail.labels.ctaLabel',
                                                    )
                                                }}
                                            </p>
                                            <pre
                                                class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm whitespace-pre-wrap dark:border-slate-800 dark:bg-slate-900"
                                                >{{
                                                    displayValue(
                                                        props.template
                                                            .base_template
                                                            .cta_label_template,
                                                    )
                                                }}</pre
                                            >
                                        </div>
                                        <div class="space-y-2 lg:col-span-2">
                                            <p
                                                class="text-xs text-slate-500 uppercase"
                                            >
                                                {{
                                                    t(
                                                        'admin.communicationTemplates.detail.labels.body',
                                                    )
                                                }}
                                            </p>
                                            <pre
                                                class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm whitespace-pre-wrap dark:border-slate-800 dark:bg-slate-900"
                                                >{{
                                                    displayValue(
                                                        props.template
                                                            .base_template
                                                            .body_template,
                                                    )
                                                }}</pre
                                            >
                                        </div>
                                        <div class="space-y-2 lg:col-span-2">
                                            <p
                                                class="text-xs text-slate-500 uppercase"
                                            >
                                                {{
                                                    t(
                                                        'admin.communicationTemplates.detail.labels.ctaUrl',
                                                    )
                                                }}
                                            </p>
                                            <pre
                                                class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm whitespace-pre-wrap dark:border-slate-800 dark:bg-slate-900"
                                                >{{
                                                    displayValue(
                                                        props.template
                                                            .base_template
                                                            .cta_url_template,
                                                    )
                                                }}</pre
                                            >
                                        </div>
                                    </CardContent>
                                </Card>
                            </div>

                            <div class="space-y-6">
                                <Card
                                    class="rounded-[1.5rem] border-slate-200/80 dark:border-slate-800"
                                >
                                    <CardHeader>
                                        <CardTitle>{{
                                            t(
                                                'admin.communicationTemplates.edit.sections.resolved',
                                            )
                                        }}</CardTitle>
                                    </CardHeader>
                                    <CardContent class="space-y-4">
                                        <div class="space-y-2">
                                            <p
                                                class="text-xs text-slate-500 uppercase"
                                            >
                                                {{
                                                    t(
                                                        'admin.communicationTemplates.detail.labels.subject',
                                                    )
                                                }}
                                            </p>
                                            <pre
                                                class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm whitespace-pre-wrap dark:border-slate-800 dark:bg-slate-900"
                                                >{{
                                                    displayValue(
                                                        resolvedFields.subject_template,
                                                    )
                                                }}</pre
                                            >
                                        </div>
                                        <div class="space-y-2">
                                            <p
                                                class="text-xs text-slate-500 uppercase"
                                            >
                                                {{
                                                    t(
                                                        'admin.communicationTemplates.detail.labels.title',
                                                    )
                                                }}
                                            </p>
                                            <pre
                                                class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm whitespace-pre-wrap dark:border-slate-800 dark:bg-slate-900"
                                                >{{
                                                    displayValue(
                                                        resolvedFields.title_template,
                                                    )
                                                }}</pre
                                            >
                                        </div>
                                        <div class="space-y-2">
                                            <p
                                                class="text-xs text-slate-500 uppercase"
                                            >
                                                {{
                                                    t(
                                                        'admin.communicationTemplates.detail.labels.body',
                                                    )
                                                }}
                                            </p>
                                            <pre
                                                class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm whitespace-pre-wrap dark:border-slate-800 dark:bg-slate-900"
                                                >{{
                                                    displayValue(
                                                        resolvedFields.body_template,
                                                    )
                                                }}</pre
                                            >
                                        </div>
                                        <div class="grid gap-4 lg:grid-cols-2">
                                            <div class="space-y-2">
                                                <p
                                                    class="text-xs text-slate-500 uppercase"
                                                >
                                                    {{
                                                        t(
                                                            'admin.communicationTemplates.detail.labels.ctaLabel',
                                                        )
                                                    }}
                                                </p>
                                                <pre
                                                    class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm whitespace-pre-wrap dark:border-slate-800 dark:bg-slate-900"
                                                    >{{
                                                        displayValue(
                                                            resolvedFields.cta_label_template,
                                                        )
                                                    }}</pre
                                                >
                                            </div>
                                            <div class="space-y-2">
                                                <p
                                                    class="text-xs text-slate-500 uppercase"
                                                >
                                                    {{
                                                        t(
                                                            'admin.communicationTemplates.detail.labels.ctaUrl',
                                                        )
                                                    }}
                                                </p>
                                                <pre
                                                    class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm whitespace-pre-wrap dark:border-slate-800 dark:bg-slate-900"
                                                    >{{
                                                        displayValue(
                                                            resolvedFields.cta_url_template,
                                                        )
                                                    }}</pre
                                                >
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>

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
                                                props.template
                                                    .available_variables
                                                    .length > 0
                                            "
                                            class="flex flex-wrap gap-2"
                                        >
                                            <Badge
                                                v-for="variable in props
                                                    .template
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

                                <Card
                                    class="rounded-[1.5rem] border-slate-200/80 dark:border-slate-800"
                                >
                                    <CardHeader>
                                        <CardTitle>{{
                                            t(
                                                'admin.communicationTemplates.edit.sections.preview',
                                            )
                                        }}</CardTitle>
                                    </CardHeader>
                                    <CardContent class="space-y-4">
                                        <div
                                            class="rounded-[1.75rem] border border-slate-200 bg-slate-50/70 p-5 dark:border-slate-800 dark:bg-slate-900/70"
                                        >
                                            <div class="space-y-4">
                                                <div>
                                                    <p
                                                        class="text-xs font-medium tracking-[0.16em] text-slate-500 uppercase"
                                                    >
                                                        {{
                                                            t(
                                                                'admin.communicationTemplates.edit.preview.subject',
                                                            )
                                                        }}
                                                    </p>
                                                    <p
                                                        class="mt-2 text-sm font-semibold text-slate-950 dark:text-slate-50"
                                                    >
                                                        {{
                                                            displayValue(
                                                                livePreview.subject,
                                                            )
                                                        }}
                                                    </p>
                                                </div>

                                                <div
                                                    class="rounded-[1.5rem] border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-950"
                                                >
                                                    <div class="space-y-4">
                                                        <div>
                                                            <p
                                                                class="text-lg font-semibold tracking-tight text-slate-950 dark:text-slate-50"
                                                            >
                                                                {{
                                                                    displayValue(
                                                                        livePreview.title,
                                                                    )
                                                                }}
                                                            </p>
                                                            <p
                                                                class="mt-3 text-sm leading-7 whitespace-pre-wrap text-slate-600 dark:text-slate-300"
                                                            >
                                                                {{
                                                                    displayValue(
                                                                        livePreview.body,
                                                                    )
                                                                }}
                                                            </p>
                                                        </div>

                                                        <div
                                                            v-if="
                                                                livePreview.cta_label ||
                                                                livePreview.cta_url
                                                            "
                                                            class="space-y-2"
                                                        >
                                                            <button
                                                                type="button"
                                                                class="inline-flex items-center rounded-xl bg-slate-950 px-4 py-2 text-sm font-medium text-white dark:bg-slate-100 dark:text-slate-950"
                                                            >
                                                                {{
                                                                    displayValue(
                                                                        livePreview.cta_label,
                                                                    )
                                                                }}
                                                            </button>
                                                            <p
                                                                class="text-xs break-all text-slate-500 dark:text-slate-400"
                                                            >
                                                                {{
                                                                    displayValue(
                                                                        livePreview.cta_url,
                                                                    )
                                                                }}
                                                            </p>
                                                        </div>

                                                        <div
                                                            class="border-t border-slate-200 pt-4 text-xs leading-6 text-slate-500 dark:border-slate-800 dark:text-slate-400"
                                                        >
                                                            {{
                                                                t(
                                                                    'admin.communicationTemplates.edit.preview.footer',
                                                                )
                                                            }}
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </AdminLayout>
    </AppLayout>
</template>
