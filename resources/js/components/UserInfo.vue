<script setup lang="ts">
import { computed } from 'vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useInitials } from '@/composables/useInitials';
import type { User } from '@/types';

type Props = {
    user: User;
    showEmail?: boolean;
    compact?: boolean;
};

const props = withDefaults(defineProps<Props>(), {
    showEmail: false,
    compact: false,
});

const { getInitials } = useInitials();

// Compute whether we should show the avatar image
const showAvatar = computed(
    () => props.user.avatar && props.user.avatar !== '',
);
const avatarSizeClass = computed(() =>
    props.compact ? 'h-7 w-7 rounded-md' : 'h-8 w-8 rounded-lg',
);
const avatarFallbackClass = computed(() =>
    props.compact
        ? 'rounded-md text-[11px] font-semibold text-black dark:text-white'
        : 'rounded-lg text-black dark:text-white',
);
</script>

<template>
    <Avatar :class="['overflow-hidden', avatarSizeClass]">
        <AvatarImage v-if="showAvatar" :src="user.avatar!" :alt="user.name" />
        <AvatarFallback :class="avatarFallbackClass">
            {{ getInitials(user.name) }}
        </AvatarFallback>
    </Avatar>

    <div class="grid flex-1 text-left text-sm leading-tight">
        <span class="truncate font-medium">{{ user.name }}</span>
        <span v-if="showEmail" class="truncate text-xs text-muted-foreground">{{
            user.email
        }}</span>
    </div>
</template>
