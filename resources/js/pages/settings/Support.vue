<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { CheckCircle2, LifeBuoy, Send } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import SupportRequestStatusBadge from '@/components/admin/support/SupportRequestStatusBadge.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import SupportTicketConversation from '@/components/SupportTicketConversation.vue';
import { Button } from '@/components/ui/button';
import { useForm } from '@/composables/useNotifiedForm';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { index as supportIndex } from '@/routes/support';
import {
    store as storeSupportRequest,
    reply as replySupportRequest,
} from '@/routes/support/requests';
import type { SupportPageProps } from '@/types/support';
const props = defineProps<SupportPageProps>();
const page = usePage();
const { t, locale } = useI18n();
const success = computed(
    () => (page.props.flash as { success?: string })?.success,
);
const form = useForm({
    category: props.supportCategories[0]?.value ?? 'general_support',
    subject: '',
    message: '',
    source_url: props.supportContext.source_url ?? '',
    source_route: props.supportContext.source_route ?? '',
});
const replyForm = useForm({ body: '' });
const deadline = (value: string) =>
    new Intl.DateTimeFormat(locale.value, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
const date = (value: string) =>
    new Intl.DateTimeFormat(locale.value, { dateStyle: 'medium' }).format(
        new Date(value),
    );
function submit(): void {
    form.post(storeSupportRequest().url, {
        preserveScroll: true,
        onSuccess: () => form.reset('subject', 'message'),
    });
}
function sendReply(): void {
    if (!props.currentTicket) {
        return;
    }

    replyForm.post(
        replySupportRequest({ supportRequest: props.currentTicket.uuid }).url,
        { preserveScroll: true, onSuccess: () => replyForm.reset() },
    );
}
const history = computed(() => props.ticketHistory);
</script>
<template>
    <AppLayout
        :breadcrumbs="[
            { title: t('supportTickets.title'), href: supportIndex() },
        ]"
    >
        <Head :title="t('supportTickets.title')" />
        <SettingsLayout>
            <div class="max-w-3xl space-y-6">
                <Heading
                    data-onboarding="support.step1"
                    :title="t('supportTickets.title')"
                    :description="t('supportTickets.intro')"
                />
                <div
                    v-if="success"
                    role="status"
                    class="flex gap-3 rounded-2xl border border-emerald-500/25 bg-emerald-500/10 p-4 text-sm"
                >
                    <CheckCircle2 class="size-5 shrink-0 text-emerald-600" />
                    <div>
                        <p class="font-semibold">
                            {{ t('supportTickets.success') }}
                        </p>
                        <p>{{ success }}</p>
                    </div>
                </div>
                <section
                    class="space-y-3"
                    :aria-label="t('supportTickets.openTickets')"
                >
                    <div>
                        <h2 class="font-semibold">
                            {{ t('supportTickets.openTickets') }}
                        </h2>
                        <p class="mt-1 text-xs leading-5 text-muted-foreground">
                            {{ t('supportTickets.openDescription') }}
                        </p>
                    </div>
                    <div class="grid grid-cols-3 gap-2 sm:gap-3">
                        <div
                            v-for="category in props.supportCategories"
                            :key="category.value"
                            class="min-w-0 rounded-2xl border border-border bg-card p-3 sm:p-4"
                        >
                            <p
                                class="text-[11px] font-medium break-words text-muted-foreground sm:text-xs"
                            >
                                {{
                                    t(`supportTickets.count_${category.value}`)
                                }}
                            </p>
                            <p class="mt-2 text-2xl font-semibold tabular-nums">
                                {{ props.openTicketCounts[category.value] }}
                            </p>
                        </div>
                    </div>
                </section>
                <details
                    :open="!props.hasActiveTicket"
                    class="space-y-5 rounded-3xl border border-border bg-card p-4 sm:p-6"
                >
                    <summary
                        class="flex cursor-pointer items-center gap-2 text-lg font-semibold"
                    >
                        <LifeBuoy class="size-5 text-sky-600" />{{
                            t('supportTickets.new')
                        }}
                    </summary>
                    <p
                        v-if="!props.ticketHistory.length"
                        class="text-sm text-muted-foreground"
                    >
                        {{ t('supportTickets.empty') }}
                    </p>
                    <form
                        data-onboarding="support.step2"
                        class="space-y-5"
                        @submit.prevent="submit"
                    >
                        <fieldset>
                            <legend class="mb-2 text-sm font-semibold">
                                {{ t('supportTickets.type') }}
                            </legend>
                            <div class="grid gap-2 sm:grid-cols-3">
                                <label
                                    v-for="category in props.supportCategories"
                                    :key="category.value"
                                    class="cursor-pointer rounded-2xl border p-3 text-sm transition"
                                    :class="
                                        form.category === category.value
                                            ? 'border-sky-500 bg-sky-500/10 ring-1 ring-sky-500'
                                            : 'border-border'
                                    "
                                    ><input
                                        v-model="form.category"
                                        type="radio"
                                        name="support-category"
                                        :value="category.value"
                                        class="mr-2 accent-sky-600"
                                    /><span class="font-semibold">{{
                                        category.label
                                    }}</span
                                    ><span
                                        class="mt-2 block text-xs leading-5 text-muted-foreground"
                                        >{{ category.description }}</span
                                    ></label
                                >
                            </div>
                            <InputError :message="form.errors.category" />
                        </fieldset>
                        <div class="space-y-2">
                            <label
                                for="support-subject"
                                class="text-sm font-semibold"
                                >{{ t('supportTickets.subject') }}</label
                            ><input
                                id="support-subject"
                                v-model="form.subject"
                                required
                                maxlength="255"
                                class="h-11 w-full rounded-2xl border border-border bg-background px-3 text-sm"
                            /><InputError :message="form.errors.subject" />
                        </div>
                        <div class="space-y-2">
                            <label
                                for="support-message"
                                class="text-sm font-semibold"
                                >{{ t('supportTickets.message') }}</label
                            ><textarea
                                id="support-message"
                                v-model="form.message"
                                required
                                minlength="20"
                                maxlength="5000"
                                rows="6"
                                class="w-full rounded-2xl border border-border bg-background p-3 text-sm"
                            /><InputError :message="form.errors.message" />
                        </div>
                        <p class="text-xs text-muted-foreground">
                            {{ t('supportTickets.detected') }}:
                            <strong>{{ props.appVersion ?? '—' }}</strong>
                        </p>
                        <Button type="submit" :disabled="form.processing"
                            ><Send class="mr-2 size-4" />{{
                                t('supportTickets.send')
                            }}</Button
                        >
                    </form>
                </details>
                <section class="space-y-3">
                    <h2
                        data-onboarding="support.step3"
                        class="text-sm font-semibold"
                    >
                        {{ t('supportTickets.allHistory') }}
                    </h2>
                    <p
                        v-if="!history.length"
                        class="text-sm text-muted-foreground"
                    >
                        {{ t('supportTickets.noHistory') }}
                    </p>
                    <Link
                        v-for="ticket in history"
                        :key="ticket.uuid"
                        :aria-current="
                            ticket.uuid === props.currentTicket?.uuid
                                ? 'page'
                                : undefined
                        "
                        :class="
                            ticket.uuid === props.currentTicket?.uuid
                                ? 'border-sky-500 bg-sky-500/5'
                                : 'border-border'
                        "
                        :href="supportIndex({ query: { ticket: ticket.uuid } })"
                        class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-border p-4 text-sm hover:bg-muted/50"
                        ><span class="min-w-0"
                            ><span class="block font-medium break-words">{{
                                ticket.subject
                            }}</span
                            ><span class="text-xs text-muted-foreground"
                                >{{ date(ticket.created_at) }} ·
                                {{
                                    ticket.app_version ??
                                    t('supportTickets.unknown')
                                }}</span
                            ></span
                        ><SupportRequestStatusBadge
                            kind="status"
                            :value="ticket.status"
                    /></Link>
                    <nav
                        v-if="props.historyPreviousUrl || props.historyNextUrl"
                        class="flex flex-wrap gap-4 text-sm"
                        :aria-label="t('supportTickets.history')"
                    >
                        <Link
                            v-if="props.historyPreviousUrl"
                            :href="props.historyPreviousUrl"
                            class="underline"
                            >{{ t('supportTickets.previous') }}</Link
                        ><Link
                            v-if="props.historyNextUrl"
                            :href="props.historyNextUrl"
                            class="underline"
                            >{{ t('supportTickets.next') }}</Link
                        >
                    </nav>
                </section>
                <section
                    v-if="props.currentTicket"
                    class="space-y-5 rounded-3xl border border-border bg-card p-4 sm:p-6"
                >
                    <div class="flex flex-wrap items-center gap-2">
                        <SupportRequestStatusBadge
                            kind="category"
                            :value="props.currentTicket.category"
                        /><SupportRequestStatusBadge
                            kind="status"
                            :value="props.currentTicket.status"
                        />
                    </div>
                    <div>
                        <h2 class="text-lg font-semibold break-words">
                            {{ props.currentTicket.subject }}
                        </h2>
                        <p class="mt-1 text-xs text-muted-foreground">
                            {{ date(props.currentTicket.created_at) }}
                        </p>
                        <p class="mt-2 text-sm text-muted-foreground">
                            {{ t('supportTickets.version') }}:
                            <strong>{{
                                props.currentTicket.app_version ??
                                t('supportTickets.unknown')
                            }}</strong>
                        </p>
                    </div>
                    <SupportTicketConversation
                        :messages="props.currentTicket.messages"
                    />
                    <form
                        v-if="props.currentTicket.status === 'waiting_user'"
                        class="space-y-3"
                        @submit.prevent="sendReply"
                    >
                        <p class="text-sm text-muted-foreground">
                            {{
                                props.currentTicket.auto_close_at
                                    ? t('supportTickets.inactivityDeadline', {
                                          date: deadline(
                                              props.currentTicket.auto_close_at,
                                          ),
                                      })
                                    : t('supportTickets.inactivityInfo')
                            }}
                        </p>
                        <label
                            for="support-reply"
                            class="text-sm font-semibold"
                            >{{ t('supportTickets.replyLabel') }}</label
                        >
                        <textarea
                            id="support-reply"
                            v-model="replyForm.body"
                            required
                            minlength="2"
                            maxlength="5000"
                            rows="4"
                            class="w-full rounded-2xl border border-border bg-background p-3 text-sm"
                        />
                        <InputError :message="replyForm.errors.body" />
                        <Button :disabled="replyForm.processing" type="submit"
                            ><Send class="mr-2 size-4" />{{
                                t('supportTickets.reply')
                            }}</Button
                        >
                    </form>
                    <p
                        v-else
                        role="status"
                        class="rounded-2xl bg-muted/60 p-4 text-sm leading-6"
                    >
                        {{
                            t(
                                props.currentTicket.status === 'closed'
                                    ? 'supportTickets.closedInfo'
                                    : 'supportTickets.wait',
                            )
                        }}
                    </p>
                </section>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
