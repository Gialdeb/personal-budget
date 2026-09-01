<script setup lang="ts">
import Dashboard from '@/pages/Dashboard.vue';
import type { DashboardPageProps } from '@/types';

defineProps<DashboardPageProps>();
</script>

<template>
    <Dashboard v-bind="$props" />
</template>
