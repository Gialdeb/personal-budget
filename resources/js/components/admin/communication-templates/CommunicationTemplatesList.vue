<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    ExternalLink,
    FileText,
    Lock,
    PenSquare,
    Power,
} from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    edit as editCommunicationTemplate,
    show as showCommunicationTemplate,
} from '@/routes/admin/communication-templates';
import type {
    AdminCommunicationTemplateItem,
    PaginationMetaLink,
} from '@/types';

const props = defineProps<{
    templates: AdminCommunicationTemplateItem[];
    links: PaginationMetaLink[];
    summary: string;
    currentPage: number;
    lastPage: number;
    loading?: boolean;
}>();

const emit = defineEmits<{
    disable: [template: AdminCommunicationTemplateItem];
}>();

const { t } = useI18n();

function stateBadge(active: boolean): string {
    return active
        ? 'border-emerald-200 bg-emerald-50 text-emerald-900 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-100'
        : 'border-slate-300 bg-slate-100 text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200';
}

function paginationLabel(label: string): string {
    return label
        .replace('&laquo;', '«')
        .replace('&raquo;', '»')
        .replace(/&amp;/g, '&');
}

function isPreviousLink(link: PaginationMetaLink): boolean {
    return link.label.includes('&laquo;') || link.label.includes('Previous');
}

function isNextLink(link: PaginationMetaLink): boolean {
    return link.label.includes('&raquo;') || link.label.includes('Next');
}

function isNumericLink(link: PaginationMetaLink): boolean {
    return /^\d+$/.test(paginationLabel(link.label).trim());
}

const previousLink = computed(
    () => props.links.find((link) => isPreviousLink(link)) ?? null,
);
const nextLink = computed(
    () => props.links.find((link) => isNextLink(link)) ?? null,
);
const pageLinks = computed(() =>
    props.links.filter((link) => isNumericLink(link)),
);
</script>

<template>
    <section
        class="overflow-hidden rounded-[1.75rem] border border-slate-200/80 bg-white/95 shadow-[0_24px_80px_-48px_rgba(15,23,42,0.45)] dark:border-slate-800 dark:bg-slate-950/85"
    >
        <div
            class="flex flex-col gap-2 border-b border-slate-200/70 px-6 py-5 dark:border-slate-800"
        >
            <h2
                class="text-base font-semibold tracking-tight text-slate-950 dark:text-slate-50"
            >
                {{ summary }}
            </h2>
            <p class="text-sm text-slate-500 dark:text-slate-400">
                {{
                    loading
                        ? t('admin.communicationTemplates.list.loading')
                        : t('admin.communicationTemplates.list.description')
                }}
            </p>
        </div>

        <div class="space-y-4 px-4 py-4 sm:px-6 sm:py-6">
            <div class="grid gap-3 md:hidden">
                <article
                    v-for="template in templates"
                    :key="template.uuid"
                    class="rounded-[1.5rem] border border-slate-200/80 bg-white/95 p-4 shadow-sm dark:border-slate-800 dark:bg-slate-950/85"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p
                                class="text-sm font-semibold text-slate-950 dark:text-slate-50"
                            >
                                {{ template.name }}
                            </p>
                            <p
                                class="mt-1 truncate text-xs text-slate-500 dark:text-slate-400"
                            >
                                {{ template.key }}
                            </p>
                        </div>
                        <Badge
                            class="rounded-full border px-2.5 py-1 text-[11px] uppercase"
                            :class="stateBadge(template.is_active)"
                        >
                            {{
                                template.is_active
                                    ? t(
                                          'admin.communicationTemplates.badges.active',
                                      )
                                    : t(
                                          'admin.communicationTemplates.badges.inactive',
                                      )
                            }}
                        </Badge>
                    </div>

                    <div class="mt-4 flex flex-wrap gap-2">
                        <Badge
                            class="rounded-full border px-2.5 py-1 text-[11px] uppercase"
                        >
                            {{ template.template_mode_label }}
                        </Badge>
                        <Badge
                            class="rounded-full border px-2.5 py-1 text-[11px] uppercase"
                        >
                            {{
                                template.topic?.label ??
                                t('admin.communicationTemplates.empty.noTopic')
                            }}
                        </Badge>
                        <Badge
                            class="rounded-full border px-2.5 py-1 text-[11px] uppercase"
                            :class="stateBadge(template.override.is_active)"
                        >
                            {{
                                template.override.exists
                                    ? template.override.is_active
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
                            v-if="template.is_system_locked"
                            class="rounded-full border px-2.5 py-1 text-[11px] uppercase"
                        >
                            <Lock class="mr-1 h-3 w-3" />
                            {{
                                t('admin.communicationTemplates.badges.locked')
                            }}
                        </Badge>
                    </div>

                    <div class="mt-4 flex flex-wrap gap-2">
                        <Button
                            size="sm"
                            variant="outline"
                            class="rounded-xl"
                            as-child
                        >
                            <Link
                                :href="
                                    showCommunicationTemplate({
                                        communicationTemplate: template.uuid,
                                    })
                                "
                            >
                                <ExternalLink class="mr-2 h-4 w-4" />
                                {{
                                    t(
                                        'admin.communicationTemplates.actions.open',
                                    )
                                }}
                            </Link>
                        </Button>
                        <Button
                            size="sm"
                            class="rounded-xl"
                            :disabled="!template.flags.can_edit_override"
                            as-child
                        >
                            <Link
                                :href="
                                    editCommunicationTemplate({
                                        communicationTemplate: template.uuid,
                                    })
                                "
                            >
                                <PenSquare class="mr-2 h-4 w-4" />
                                {{
                                    t(
                                        'admin.communicationTemplates.actions.editOverride',
                                    )
                                }}
                            </Link>
                        </Button>
                        <Button
                            size="sm"
                            variant="outline"
                            class="rounded-xl"
                            :disabled="!template.flags.can_disable_override"
                            @click="emit('disable', template)"
                        >
                            <Power class="mr-2 h-4 w-4" />
                            {{
                                t(
                                    'admin.communicationTemplates.actions.disableOverride',
                                )
                            }}
                        </Button>
                    </div>
                </article>
            </div>

            <div class="hidden overflow-x-auto md:block">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>{{
                                t('admin.communicationTemplates.table.name')
                            }}</TableHead>
                            <TableHead>{{
                                t('admin.communicationTemplates.table.key')
                            }}</TableHead>
                            <TableHead>{{
                                t('admin.communicationTemplates.table.channel')
                            }}</TableHead>
                            <TableHead>{{
                                t(
                                    'admin.communicationTemplates.table.templateMode',
                                )
                            }}</TableHead>
                            <TableHead>{{
                                t('admin.communicationTemplates.table.topic')
                            }}</TableHead>
                            <TableHead>{{
                                t('admin.communicationTemplates.table.override')
                            }}</TableHead>
                            <TableHead>{{
                                t('admin.communicationTemplates.table.status')
                            }}</TableHead>
                            <TableHead class="text-right">{{
                                t('admin.communicationTemplates.table.actions')
                            }}</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow
                            v-for="template in templates"
                            :key="template.uuid"
                        >
                            <TableCell class="min-w-64">
                                <div class="space-y-1">
                                    <div class="flex items-center gap-2">
                                        <p
                                            class="font-medium text-slate-950 dark:text-slate-50"
                                        >
                                            {{ template.name }}
                                        </p>
                                        <Lock
                                            v-if="template.is_system_locked"
                                            class="h-4 w-4 text-amber-500"
                                        />
                                    </div>
                                    <p
                                        class="text-xs leading-5 text-slate-500 dark:text-slate-400"
                                    >
                                        {{ template.description }}
                                    </p>
                                </div>
                            </TableCell>
                            <TableCell class="font-mono text-xs">{{
                                template.key
                            }}</TableCell>
                            <TableCell>{{ template.channel_label }}</TableCell>
                            <TableCell>{{
                                template.template_mode_label
                            }}</TableCell>
                            <TableCell>{{
                                template.topic?.label ??
                                t('admin.communicationTemplates.empty.noTopic')
                            }}</TableCell>
                            <TableCell>
                                <Badge
                                    class="rounded-full border px-2.5 py-1 text-[11px] uppercase"
                                    :class="
                                        stateBadge(template.override.is_active)
                                    "
                                >
                                    {{
                                        template.override.exists
                                            ? template.override.is_active
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
                            </TableCell>
                            <TableCell>
                                <Badge
                                    class="rounded-full border px-2.5 py-1 text-[11px] uppercase"
                                    :class="stateBadge(template.is_active)"
                                >
                                    {{
                                        template.is_active
                                            ? t(
                                                  'admin.communicationTemplates.badges.active',
                                              )
                                            : t(
                                                  'admin.communicationTemplates.badges.inactive',
                                              )
                                    }}
                                </Badge>
                            </TableCell>
                            <TableCell class="min-w-56 text-right">
                                <div class="flex justify-end gap-2">
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        class="rounded-xl"
                                        as-child
                                    >
                                        <Link
                                            :href="
                                                showCommunicationTemplate({
                                                    communicationTemplate:
                                                        template.uuid,
                                                })
                                            "
                                        >
                                            <FileText class="mr-2 h-4 w-4" />
                                            {{
                                                t(
                                                    'admin.communicationTemplates.actions.open',
                                                )
                                            }}
                                        </Link>
                                    </Button>
                                    <Button
                                        size="sm"
                                        class="rounded-xl"
                                        :disabled="
                                            !template.flags.can_edit_override
                                        "
                                        as-child
                                    >
                                        <Link
                                            :href="
                                                editCommunicationTemplate({
                                                    communicationTemplate:
                                                        template.uuid,
                                                })
                                            "
                                        >
                                            <PenSquare class="mr-2 h-4 w-4" />
                                            {{
                                                t(
                                                    'admin.communicationTemplates.actions.editOverride',
                                                )
                                            }}
                                        </Link>
                                    </Button>
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        class="rounded-xl"
                                        :disabled="
                                            !template.flags.can_disable_override
                                        "
                                        @click="emit('disable', template)"
                                    >
                                        <Power class="mr-2 h-4 w-4" />
                                        {{
                                            t(
                                                'admin.communicationTemplates.actions.disableOverride',
                                            )
                                        }}
                                    </Button>
                                </div>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>

            <div
                v-if="lastPage > 1"
                class="flex flex-col gap-3 border-t border-slate-200/70 pt-4 sm:flex-row sm:items-center sm:justify-between dark:border-slate-800"
            >
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    {{
                        t('admin.communicationTemplates.pagination.page', {
                            current: currentPage,
                            last: lastPage,
                        })
                    }}
                </p>
                <div class="flex flex-wrap items-center gap-2">
                    <Button
                        size="sm"
                        variant="outline"
                        class="rounded-xl"
                        :disabled="!previousLink?.url"
                        as-child
                    >
                        <Link
                            :href="previousLink?.url ?? '#'"
                            preserve-scroll
                            preserve-state
                        >
                            {{
                                t(
                                    'admin.communicationTemplates.pagination.previous',
                                )
                            }}
                        </Link>
                    </Button>

                    <Button
                        v-for="link in pageLinks"
                        :key="link.label"
                        size="sm"
                        :variant="link.active ? 'default' : 'outline'"
                        class="min-w-10 rounded-xl"
                        :disabled="!link.url"
                        as-child
                    >
                        <Link
                            :href="link.url ?? '#'"
                            preserve-scroll
                            preserve-state
                        >
                            {{ paginationLabel(link.label) }}
                        </Link>
                    </Button>

                    <Button
                        size="sm"
                        variant="outline"
                        class="rounded-xl"
                        :disabled="!nextLink?.url"
                        as-child
                    >
                        <Link
                            :href="nextLink?.url ?? '#'"
                            preserve-scroll
                            preserve-state
                        >
                            {{
                                t(
                                    'admin.communicationTemplates.pagination.next',
                                )
                            }}
                        </Link>
                    </Button>
                </div>
            </div>
        </div>
    </section>
</template>
