<script setup lang="ts">
import PushBroadcastsIndex from './PushBroadcasts/Index.vue';

type Props = {
    audience: {
        eligible_users_count: number;
        target_tokens_count: number;
    };
    broadcasts: {
        data: Array<{
            uuid: string;
            status: string;
            title: string;
            body: string;
            url: string | null;
            eligible_users_count: number;
            target_tokens_count: number;
            sent_count: number;
            failed_count: number;
            invalidated_count: number;
            queued_at: string | null;
            started_at: string | null;
            finished_at: string | null;
            error_message: string | null;
            creator: { uuid: string; name: string } | null;
        }>;
    };
};

const props = defineProps<Props>();
</script>

<template>
    <PushBroadcastsIndex v-bind="props" />
</template>
