<script setup lang="ts">
import { router, useForm, usePage } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { disable as disableGlobalOverride } from '@/routes/admin/communication-templates/global-override/index';
import { update as updateGlobalOverride } from '@/routes/admin/communication-templates/global-override/index';

type EditableTemplate = {
    uuid: string;
    name: string;
    key: string;
    is_system_locked: boolean;
    flags: {
        can_edit_override: boolean;
        can_disable_override: boolean;
    };
    override: {
        subject_template: string | null;
        title_template: string | null;
        body_template: string | null;
        cta_label_template: string | null;
        cta_url_template: string | null;
        is_active: boolean;
    } | null;
};

const props = defineProps<{
    open: boolean;
    template: EditableTemplate | null;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
    saved: [];
    disabled: [];
}>();

const { t } = useI18n();
const page = usePage();
const pageErrors = computed(
    () => (page.props.errors ?? {}) as Record<string, string | undefined>,
);
const disableConfirmOpen = computed(
    () => props.template?.flags.can_disable_override ?? false,
);

const form = useForm({
    subject_template: '',
    title_template: '',
    body_template: '',
    cta_label_template: '',
    cta_url_template: '',
    is_active: true,
});

watch(
    () => [props.open, props.template] as const,
    ([open, template]) => {
        if (!open || !template) {
            return;
        }

        form.defaults({
            subject_template: template.override?.subject_template ?? '',
            title_template: template.override?.title_template ?? '',
            body_template: template.override?.body_template ?? '',
            cta_label_template: template.override?.cta_label_template ?? '',
            cta_url_template: template.override?.cta_url_template ?? '',
            is_active: template.override?.is_active ?? true,
        });

        form.reset();
        form.clearErrors();
    },
    { immediate: true },
);

function closeSheet(): void {
    emit('update:open', false);
}

function submit(): void {
    if (!props.template) {
        return;
    }

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
            onSuccess: () => {
                closeSheet();
                emit('saved');
            },
        },
    );
}

function disableOverride(): void {
    if (!props.template) {
        return;
    }

    router.post(
        disableGlobalOverride({ communicationTemplate: props.template.uuid })
            .url,
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                closeSheet();
                emit('disabled');
            },
        },
    );
}
</script>

<template>
    <Sheet :open="open" @update:open="emit('update:open', $event)">
        <SheetContent side="right" class="w-full overflow-y-auto sm:max-w-xl">
            <SheetHeader class="space-y-2">
                <SheetTitle>{{
                    t('admin.communicationTemplates.form.title')
                }}</SheetTitle>
                <SheetDescription>
                    {{
                        template?.name ??
                        t('admin.communicationTemplates.empty.title')
                    }}
                </SheetDescription>
            </SheetHeader>

            <div class="mt-6 space-y-5">
                <div
                    v-if="
                        template?.is_system_locked ||
                        !template?.flags.can_edit_override
                    "
                    class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm leading-6 text-amber-900"
                >
                    {{ t('admin.communicationTemplates.form.disabled') }}
                </div>

                <div v-else class="space-y-5">
                    <p
                        class="text-sm leading-6 text-slate-500 dark:text-slate-400"
                    >
                        {{ t('admin.communicationTemplates.form.helper') }}
                    </p>

                    <div class="space-y-2">
                        <Label for="subject_template">{{
                            t(
                                'admin.communicationTemplates.form.fields.subject',
                            )
                        }}</Label>
                        <input
                            id="subject_template"
                            v-model="form.subject_template"
                            type="text"
                            class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950"
                        />
                        <InputError :message="pageErrors.subject_template" />
                    </div>

                    <div class="space-y-2">
                        <Label for="title_template">{{
                            t('admin.communicationTemplates.form.fields.title')
                        }}</Label>
                        <input
                            id="title_template"
                            v-model="form.title_template"
                            type="text"
                            class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950"
                        />
                        <InputError :message="pageErrors.title_template" />
                    </div>

                    <div class="space-y-2">
                        <Label for="body_template">{{
                            t('admin.communicationTemplates.form.fields.body')
                        }}</Label>
                        <textarea
                            id="body_template"
                            v-model="form.body_template"
                            rows="6"
                            class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950"
                        />
                        <InputError :message="pageErrors.body_template" />
                    </div>

                    <div class="space-y-2">
                        <Label for="cta_label_template">{{
                            t(
                                'admin.communicationTemplates.form.fields.ctaLabel',
                            )
                        }}</Label>
                        <input
                            id="cta_label_template"
                            v-model="form.cta_label_template"
                            type="text"
                            class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950"
                        />
                        <InputError :message="pageErrors.cta_label_template" />
                    </div>

                    <div class="space-y-2">
                        <Label for="cta_url_template">{{
                            t('admin.communicationTemplates.form.fields.ctaUrl')
                        }}</Label>
                        <input
                            id="cta_url_template"
                            v-model="form.cta_url_template"
                            type="text"
                            class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950"
                        />
                        <InputError :message="pageErrors.cta_url_template" />
                    </div>

                    <div
                        class="flex items-center gap-3 rounded-2xl border border-slate-200 p-3 dark:border-slate-800"
                    >
                        <Checkbox
                            id="is_active"
                            :checked="form.is_active"
                            @update:checked="form.is_active = Boolean($event)"
                        />
                        <Label for="is_active">{{
                            t(
                                'admin.communicationTemplates.form.fields.isActive',
                            )
                        }}</Label>
                    </div>
                    <InputError :message="pageErrors.is_active" />
                </div>
            </div>

            <SheetFooter
                class="mt-8 flex-col gap-3 sm:flex-row sm:justify-between"
            >
                <Button
                    v-if="disableConfirmOpen"
                    variant="outline"
                    class="rounded-xl"
                    @click="disableOverride"
                >
                    {{
                        t(
                            'admin.communicationTemplates.actions.disableOverride',
                        )
                    }}
                </Button>
                <div class="flex flex-1 justify-end gap-3">
                    <Button
                        variant="ghost"
                        class="rounded-xl"
                        @click="closeSheet"
                    >
                        {{ t('admin.communicationTemplates.actions.cancel') }}
                    </Button>
                    <Button
                        class="rounded-xl"
                        :disabled="
                            !template?.flags.can_edit_override ||
                            form.processing
                        "
                        @click="submit"
                    >
                        {{
                            template?.override?.subject_template ||
                            template?.override?.title_template ||
                            template?.override?.body_template ||
                            template?.override?.cta_label_template ||
                            template?.override?.cta_url_template
                                ? t(
                                      'admin.communicationTemplates.actions.saveOverride',
                                  )
                                : t(
                                      'admin.communicationTemplates.actions.createOverride',
                                  )
                        }}
                    </Button>
                </div>
            </SheetFooter>
        </SheetContent>
    </Sheet>
</template>
