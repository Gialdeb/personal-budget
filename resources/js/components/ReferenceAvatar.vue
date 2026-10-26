<script setup lang="ts">
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { getInitials } from '@/composables/useInitials';

defineProps<{
    name: string;
    logoUrl?: string | null;
}>();
</script>

<template>
    <Avatar
        aria-hidden="true"
        class="size-11 rounded-xl bg-background ring-1 ring-border/40"
    >
        <AvatarImage
            v-if="logoUrl"
            :src="logoUrl"
            alt=""
            class="object-contain p-0.5"
        />
        <AvatarFallback
            class="rounded-xl text-sm font-semibold text-muted-foreground"
        >
            {{ getInitials(name) || '?' }}
        </AvatarFallback>
    </Avatar>
</template>
