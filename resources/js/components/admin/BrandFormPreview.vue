<script setup lang="ts">
import { Loader2 } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { preview } from '@/actions/App/Http/Controllers/TrackedItemLogoController';
import { useUiCopy } from '@/i18n/ui-copy';

const props = defineProps<{
    name: string;
    domain: string;
    category: string;
    aliases: string;
    published: boolean;
    mode: string;
    storedLogo: string | null;
    uploadLogo: string;
    sourceLogo: string | null;
}>();
const ui = useUiCopy();
const websiteLogo = ref('');
const loading = ref(false);
const unavailable = ref(false);
const imageFailed = ref(false);
const displayName = computed(
    () => props.name.trim() || props.domain.trim() || ui('Nuovo marchio'),
);
const initials = computed(() => {
    const name =
        props.name.trim() ||
        props.domain
            .replace(/^https?:\/\/(?:www\.)?/i, '')
            .replace(/^www\./i, '')
            .split('/')[0];

    return (
        name
            .trim()
            .split(/\s+/u)
            .filter(Boolean)
            .slice(0, 2)
            .map((part) => Array.from(part)[0])
            .join('')
            .toLocaleUpperCase() || '—'
    );
});
const logo = computed(() => {
    if (props.mode === 'remove') {
        return '';
    }

    if (props.mode === 'upload') {
        return props.uploadLogo;
    }

    if (props.sourceLogo) {
        return props.sourceLogo;
    }

    return props.mode === 'website'
        ? websiteLogo.value
        : (props.storedLogo ?? '');
});
watch(logo, () => {
    imageFailed.value = false;
});
watch(
    [() => props.domain, () => props.mode, () => props.sourceLogo],
    ([domain, mode, source], _previous, onCleanup) => {
        websiteLogo.value = '';
        unavailable.value = false;
        loading.value = false;

        if (mode !== 'website' || source || !domain.trim()) {
            return;
        }

        const controller = new AbortController();
        let active = true;
        loading.value = true;
        const timer = setTimeout(async () => {
            try {
                const response = await fetch(preview.url(), {
                    method: 'POST',
                    credentials: 'same-origin',
                    signal: controller.signal,
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN':
                            document.querySelector<HTMLMetaElement>(
                                'meta[name="csrf-token"]',
                            )?.content ?? '',
                    },
                    body: JSON.stringify({ website_url: domain.trim() }),
                });
                const data = response.ok ? await response.json() : null;

                if (!active) {
                    return;
                }

                websiteLogo.value =
                    typeof data?.logo_url === 'string' ? data.logo_url : '';
                unavailable.value = !websiteLogo.value;
            } catch {
                if (active) {
                    unavailable.value = true;
                }
            } finally {
                if (active) {
                    loading.value = false;
                }
            }
        }, 250);
        onCleanup(() => {
            active = false;
            clearTimeout(timer);
            controller.abort();
        });
    },
    { immediate: true },
);
</script>
<template>
    <section
        data-brand-preview
        class="shrink-0 space-y-2 rounded-xl border bg-muted/30 p-3"
        :aria-label="ui('Anteprima marchio')"
        :aria-busy="loading"
    >
        <div class="flex items-center gap-3">
            <div
                class="flex size-16 shrink-0 items-center justify-center overflow-hidden rounded-xl border bg-background"
            >
                <img
                    v-if="logo && !imageFailed"
                    :src="logo"
                    :alt="ui('Anteprima logo')"
                    class="size-full object-contain"
                    @error="imageFailed = true"
                />
                <span
                    v-else
                    data-brand-initials
                    class="text-xl font-semibold"
                    >{{ initials }}</span
                >
            </div>
            <div class="min-w-0 flex-1">
                <p class="line-clamp-2 font-semibold break-words">
                    {{ displayName }}
                </p>
                <p class="line-clamp-2 text-sm break-all text-muted-foreground">
                    {{ domain }}
                </p>
                <p v-if="category" class="text-xs text-muted-foreground">
                    {{ category }}
                </p>
                <p
                    v-if="aliases.trim()"
                    class="line-clamp-2 text-xs break-words text-muted-foreground"
                >
                    {{ aliases.trim().split(/\n+/).join(' · ') }}
                </p>
                <p
                    class="text-xs"
                    :class="
                        published ? 'text-emerald-600' : 'text-muted-foreground'
                    "
                >
                    {{ published ? ui('Pubblicato') : ui('Bozza') }}
                </p>
            </div>
        </div>
        <p
            v-if="loading"
            role="status"
            class="flex items-center gap-2 text-xs text-muted-foreground"
        >
            <Loader2 class="size-3 animate-spin" aria-hidden="true" />{{
                ui('Caricamento anteprima logo…')
            }}
        </p>
        <p
            v-else-if="unavailable || imageFailed"
            role="status"
            class="text-xs text-muted-foreground"
        >
            {{ ui('Logo non disponibile. Verranno mostrate le iniziali.') }}
        </p>
    </section>
</template>
