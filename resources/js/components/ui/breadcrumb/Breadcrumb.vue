<script lang="ts" setup>
import { useUiCopy } from '@/i18n/ui-copy';
const ui = useUiCopy();

import type { HTMLAttributes } from "vue"

const props = defineProps<{
  class?: HTMLAttributes["class"]
}>()
</script>

<template>
  <nav
    :aria-label="ui('breadcrumb')"
    :data-slot="ui('breadcrumb')"
    :class="props.class"
  >
    <slot />
  </nav>
</template>
