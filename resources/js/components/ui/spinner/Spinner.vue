<script setup lang="ts">
import { useUiCopy } from '@/i18n/ui-copy';
const ui = useUiCopy();

import type { HTMLAttributes } from "vue"
import { Loader2Icon } from "lucide-vue-next"
import { cn } from "@/lib/utils"

const props = defineProps<{
  class?: HTMLAttributes["class"]
}>()
</script>

<template>
  <Loader2Icon
    role="status"
    :aria-label="ui('Loading')"
    :class="cn('size-4 animate-spin', props.class)"
  />
</template>
