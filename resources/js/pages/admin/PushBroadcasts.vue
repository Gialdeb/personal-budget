<script setup lang="ts">
import type { AdminPushBroadcastsPageProps } from '@/types';
import PushBroadcastsIndex from './PushBroadcasts/Index.vue';

const props = defineProps<AdminPushBroadcastsPageProps>();
</script>

<template>
    <PushBroadcastsIndex v-bind="props" />
</template>
