<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowRight } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { changelogContent } from '@/i18n/changelog-content';
import { show as showChangelogRelease } from '@/routes/changelog';
import type { PublicChangelogRelease } from '@/types';

const props = defineProps<{
    release: PublicChangelogRelease;
}>();

const { locale } = useI18n();

const content = computed(() =>
    locale.value === 'it' ? changelogContent.it : changelogContent.en,
);

const channelLabel = computed(() =>
    props.release.channel === 'beta'
        ? content.value.badges.beta
        : content.value.badges.stable,
);
</script>

<template>
    <article
        class="rounded-[2rem] border border-[#e9ddd4] bg-white p-7 shadow-[0_26px_70px_-48px_rgba(15,23,42,0.18)]"
    >
        <div class="flex flex-wrap items-center gap-2">
            <span
                class="inline-flex items-center rounded-full border border-[#f2dfd8] bg-[#fff7f4] px-3 py-1 text-[11px] font-semibold tracking-[0.16em] text-[#b65642] uppercase"
            >
                {{ release.version_label }}
            </span>
            <span
                class="inline-flex items-center rounded-full border border-[#ebe6df] bg-[#f8f5f2] px-3 py-1 text-[11px] font-semibold tracking-[0.16em] text-slate-600 uppercase"
            >
                {{ channelLabel }}
            </span>
            <span
                v-if="release.is_pinned"
                class="inline-flex items-center rounded-full border border-[#f7d7d1] bg-[#fff1ee] px-3 py-1 text-[11px] font-semibold tracking-[0.16em] text-[#de4f3d] uppercase"
            >
                {{ content.badges.pinned }}
            </span>
        </div>

        <div class="mt-5 space-y-3">
            <h2
                class="text-[1.4rem] font-semibold tracking-tight text-slate-950"
            >
                {{ release.title }}
            </h2>
            <p class="text-sm leading-7 text-slate-600 sm:text-[0.95rem]">
                {{
                    release.excerpt ?? release.summary?.replace(/<[^>]+>/g, '')
                }}
            </p>
        </div>

        <div class="mt-6 flex items-center justify-between gap-4">
            <p
                class="text-xs font-medium tracking-[0.16em] text-slate-400 uppercase"
            >
                {{ release.available_locales.join(' · ') }}
            </p>
            <Link
                :href="showChangelogRelease(release.version_label)"
                class="inline-flex items-center gap-2 rounded-2xl border border-[#e7dad1] bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:border-[#d8c7bb] hover:text-slate-950"
            >
                {{ content.list.ctaLabel }}
                <ArrowRight class="size-4" />
            </Link>
        </div>
    </article>
</template>
