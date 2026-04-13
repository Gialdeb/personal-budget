<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronRight } from 'lucide-vue-next';

defineProps<{
    items: Array<{
        label: string;
        href?: string | Record<string, unknown> | null;
    }>;
}>();
</script>

<template>
    <nav
        class="flex flex-wrap items-center gap-2 text-sm text-slate-500"
        aria-label="Breadcrumb"
    >
        <template v-for="(item, index) in items" :key="`${item.label}-${index}`">
            <ChevronRight
                v-if="index > 0"
                class="size-4 text-slate-300"
            />
            <Link
                v-if="item.href"
                :href="item.href"
                class="transition hover:text-slate-900"
            >
                {{ item.label }}
            </Link>
            <span v-else class="font-medium text-slate-700">
                {{ item.label }}
            </span>
        </template>
    </nav>
</template>
