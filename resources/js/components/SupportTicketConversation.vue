<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import type { SupportTicketMessage } from '@/types/support';
defineProps<{ messages: SupportTicketMessage[]; admin?: boolean }>();
const { t, locale } = useI18n();
const date = (value: string) =>
    new Intl.DateTimeFormat(locale.value, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
</script>
<template>
    <ol class="space-y-4" :aria-label="t('supportTickets.conversation')">
        <li
            v-for="message in messages"
            :key="message.uuid"
            class="rounded-2xl border border-border p-4"
            :class="
                message.author_type === 'admin' ? 'bg-sky-500/5' : 'bg-muted/30'
            "
        >
            <div
                class="flex flex-wrap items-center justify-between gap-2 text-xs text-muted-foreground"
            >
                <span class="font-semibold text-foreground"
                    >{{
                        t(
                            `supportTickets.${message.author_type === 'admin' ? 'support' : message.author_type === 'system' ? 'system' : admin ? 'user' : 'you'}`,
                        )
                    }}<span v-if="admin && message.admin_name">
                        · {{ message.admin_name }}</span
                    ></span
                >
                <time :datetime="message.created_at">{{
                    date(message.created_at)
                }}</time>
            </div>
            <p class="mt-2 text-sm leading-6 break-words whitespace-pre-wrap">
                {{
                    message.author_type === 'system'
                        ? t(`supportTickets.event_${message.body}`)
                        : message.body
                }}
            </p>
        </li>
    </ol>
</template>
