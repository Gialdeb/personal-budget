<script setup lang="ts">
import { Monitor, Moon, Sun } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import {
    DropdownMenuLabel,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import { useAppearance } from '@/composables/useAppearance';
import type { Appearance } from '@/types';

type ThemeOption = {
    value: Appearance;
    icon: typeof Sun;
    label: string;
};

type Props = {
    variant?: 'dropdown' | 'inline';
    showSeparator?: boolean;
    tone?: 'default' | 'sidebar';
};

const props = withDefaults(defineProps<Props>(), {
    variant: 'dropdown',
    showSeparator: true,
    tone: 'default',
});

const { appearance, resolvedAppearance, updateAppearance } = useAppearance();
const { t } = useI18n();

const options = computed<ThemeOption[]>(() => [
    {
        value: 'light',
        icon: Sun,
        label: t('app.appearance.light'),
    },
    {
        value: 'dark',
        icon: Moon,
        label: t('app.appearance.dark'),
    },
    {
        value: 'system',
        icon: Monitor,
        label: t('app.appearance.system'),
    },
]);

const currentAppearanceLabel = computed(() => {
    if (appearance.value === 'system') {
        return t('app.userMenu.theme.currentSystem', {
            value: t(`app.appearance.${resolvedAppearance.value}`),
        });
    }

    return t(`app.appearance.${appearance.value}`);
});

const inlineLabelClass = computed(() =>
    props.tone === 'sidebar' ? 'text-sidebar-foreground' : 'text-foreground',
);

const inlineHelperClass = computed(() =>
    props.tone === 'sidebar'
        ? 'text-sidebar-foreground/70'
        : 'text-muted-foreground',
);

function changeAppearance(nextAppearance: unknown): void {
    if (
        nextAppearance !== 'light' &&
        nextAppearance !== 'dark' &&
        nextAppearance !== 'system'
    ) {
        return;
    }

    if (nextAppearance === appearance.value) {
        return;
    }

    updateAppearance(nextAppearance);
}
</script>

<template>
    <template v-if="variant === 'dropdown'">
        <DropdownMenuSeparator v-if="showSeparator" />
        <DropdownMenuLabel
            class="px-2 py-1.5 text-xs font-medium text-muted-foreground"
        >
            {{ t('app.userMenu.theme.label') }}
        </DropdownMenuLabel>
        <DropdownMenuRadioGroup
            :model-value="appearance"
            :aria-label="t('app.userMenu.theme.ariaLabel')"
            @update:model-value="changeAppearance"
        >
            <DropdownMenuRadioItem
                v-for="option in options"
                :key="option.value"
                :value="option.value"
                class="app-touch-interactive"
                :data-test="`theme-option-${option.value}`"
            >
                <component
                    :is="option.icon"
                    class="size-4 text-muted-foreground"
                />
                {{ option.label }}
            </DropdownMenuRadioItem>
        </DropdownMenuRadioGroup>
        <DropdownMenuLabel
            class="px-2 py-1.5 text-xs font-normal text-muted-foreground"
        >
            {{
                t('app.userMenu.theme.helper', {
                    value: currentAppearanceLabel,
                })
            }}
        </DropdownMenuLabel>
    </template>

    <div v-else class="space-y-2" data-test="theme-switcher-mobile">
        <div class="space-y-1">
            <p :class="inlineLabelClass" class="text-sm font-medium">
                {{ t('app.userMenu.theme.label') }}
            </p>
            <p :class="inlineHelperClass" class="text-xs">
                {{
                    t('app.userMenu.theme.helper', {
                        value: currentAppearanceLabel,
                    })
                }}
            </p>
        </div>

        <div
            class="grid grid-cols-3 gap-2"
            role="radiogroup"
            :aria-label="t('app.userMenu.theme.ariaLabel')"
        >
            <button
                v-for="option in options"
                :key="option.value"
                type="button"
                :role="'radio'"
                :aria-checked="appearance === option.value"
                :class="[
                    'app-touch-interactive flex min-h-12 items-center justify-center gap-2 rounded-xl border px-3 py-2 text-sm font-medium transition-colors focus-visible:ring-2 focus-visible:outline-none',
                    props.tone === 'sidebar'
                        ? appearance === option.value
                            ? 'border-sidebar-foreground/15 bg-sidebar-foreground text-sidebar-primary focus-visible:ring-sidebar-ring/50'
                            : 'border-sidebar-border/70 bg-sidebar-accent/35 text-sidebar-foreground hover:bg-sidebar-accent hover:text-sidebar-accent-foreground focus-visible:bg-sidebar-accent focus-visible:text-sidebar-accent-foreground focus-visible:ring-sidebar-ring/50'
                        : appearance === option.value
                          ? 'border-foreground/10 bg-foreground text-background shadow-sm focus-visible:ring-ring/50'
                          : 'border-border bg-muted/65 text-foreground hover:bg-accent hover:text-accent-foreground focus-visible:bg-accent focus-visible:text-accent-foreground focus-visible:ring-ring/50',
                ]"
                data-app-touch-target
                :data-test="`theme-switcher-mobile-${option.value}`"
                @click="changeAppearance(option.value)"
            >
                <component :is="option.icon" class="size-4" />
                <span>{{ option.label }}</span>
            </button>
        </div>
    </div>
</template>
