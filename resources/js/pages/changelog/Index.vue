<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { CircleCheckBig, Sparkles } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import PublicChangelogReleaseCard from '@/components/public/changelog/PublicChangelogReleaseCard.vue';
import PublicCookieConsent from '@/components/public/PublicCookieConsent.vue';
import PublicPageSection from '@/components/public/PublicPageSection.vue';
import PublicSiteFooter from '@/components/public/PublicSiteFooter.vue';
import PublicSiteHeader from '@/components/public/PublicSiteHeader.vue';
import { changelogContent } from '@/i18n/changelog-content';
import { fetchPublicChangelogIndex } from '@/lib/public-changelog';
import { features, register } from '@/routes';
import type { PublicChangelogRelease } from '@/types';

withDefaults(
    defineProps<{
        canRegister: boolean;
    }>(),
    {
        canRegister: true,
    },
);

const { locale } = useI18n();
const releases = ref<PublicChangelogRelease[]>([]);
const isLoading = ref(true);
const loadError = ref<string | null>(null);

const content = computed(() =>
    locale.value === 'it' ? changelogContent.it : changelogContent.en,
);

async function loadReleases(): Promise<void> {
    isLoading.value = true;
    loadError.value = null;

    try {
        releases.value = await fetchPublicChangelogIndex(locale.value);
    } catch (error) {
        loadError.value =
            error instanceof Error
                ? error.message
                : 'Unable to load changelog releases.';
        releases.value = [];
    } finally {
        isLoading.value = false;
    }
}

onMounted(() => {
    void loadReleases();
});

watch(locale, () => {
    void loadReleases();
});
</script>

<template>
    <Head :title="content.headTitle" />

    <div class="min-h-screen bg-[#fffdfb] text-slate-950">
        <PublicSiteHeader
            :can-register="canRegister"
            current-page="changelog"
        />

        <main class="pb-18">
            <section
                class="relative mx-auto w-full max-w-7xl px-6 pt-10 pb-18 sm:px-8 lg:pt-16 lg:pb-22"
            >
                <div
                    class="absolute inset-x-6 top-0 -z-10 h-full rounded-[2.5rem] bg-[radial-gradient(circle_at_top_left,rgba(245,158,11,0.08),transparent_26%),radial-gradient(circle_at_top_right,rgba(234,90,71,0.08),transparent_24%),linear-gradient(180deg,rgba(255,255,255,0.96),rgba(255,252,248,0.94))] sm:inset-x-8"
                />

                <div
                    class="grid gap-10 lg:grid-cols-[minmax(0,1.05fr)_minmax(18rem,24rem)] lg:items-end"
                >
                    <div class="max-w-4xl space-y-6">
                        <div
                            class="inline-flex items-center gap-2 rounded-full border border-[#f2dfd8] bg-[#fff7f4] px-3 py-1 text-[11px] font-semibold tracking-[0.16em] text-[#b65642] uppercase"
                        >
                            {{ content.hero.eyebrow }}
                        </div>
                        <div class="space-y-4">
                            <h1
                                class="max-w-4xl text-[2.65rem] leading-none font-semibold tracking-[-0.04em] text-slate-950 sm:text-[3.5rem] lg:text-[4.4rem]"
                            >
                                {{ content.hero.title }}
                            </h1>
                            <p
                                class="max-w-3xl text-base leading-8 text-slate-600 sm:text-lg"
                            >
                                {{ content.hero.description }}
                            </p>
                        </div>
                        <div class="flex flex-col gap-3 sm:flex-row">
                            <Link
                                v-if="canRegister && !$page.props.auth.user"
                                :href="register()"
                                class="inline-flex items-center justify-center rounded-2xl bg-[#ea5a47] px-5 py-3 text-sm font-semibold text-white transition hover:bg-[#de4f3d]"
                            >
                                {{ content.hero.primaryLabel }}
                            </Link>
                            <Link
                                :href="features()"
                                class="inline-flex items-center justify-center rounded-2xl border border-[#e7dad1] bg-white px-5 py-3 text-sm font-semibold text-slate-700 transition hover:border-[#d8c7bb] hover:text-slate-950"
                            >
                                {{ content.hero.secondaryLabel }}
                            </Link>
                        </div>
                    </div>

                    <div
                        class="grid gap-3 rounded-[2rem] border border-[#efe4db] bg-white/88 p-5 shadow-[0_26px_70px_-48px_rgba(15,23,42,0.18)] backdrop-blur"
                    >
                        <div
                            class="flex items-center gap-3 rounded-2xl border border-[#f3e7df] bg-[#fffaf6] px-4 py-3"
                        >
                            <div
                                class="flex size-10 items-center justify-center rounded-2xl bg-[#fff1ea] text-[#ea5a47]"
                            >
                                <Sparkles class="size-4.5" />
                            </div>
                            <p
                                class="text-sm leading-6 font-medium text-slate-700"
                            >
                                {{ content.list.description }}
                            </p>
                        </div>
                        <div
                            class="flex items-center gap-3 rounded-2xl border border-[#f3e7df] bg-[#fffaf6] px-4 py-3"
                        >
                            <div
                                class="flex size-10 items-center justify-center rounded-2xl bg-[#fff1ea] text-[#ea5a47]"
                            >
                                <CircleCheckBig class="size-4.5" />
                            </div>
                            <p
                                class="text-sm leading-6 font-medium text-slate-700"
                            >
                                {{ releases.length }} release
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            <div
                class="mx-auto flex w-full max-w-7xl flex-col gap-16 px-6 sm:px-8 lg:gap-18"
            >
                <PublicPageSection
                    :eyebrow="content.hero.eyebrow"
                    :title="content.list.title"
                    :description="content.list.description"
                >
                    <div v-if="isLoading" class="grid gap-5">
                        <div
                            v-for="index in 3"
                            :key="index"
                            class="h-44 animate-pulse rounded-[2rem] border border-[#e9ddd4] bg-white"
                        />
                    </div>

                    <div
                        v-else-if="loadError"
                        class="rounded-[2rem] border border-[#efd7ce] bg-[#fff6f2] px-6 py-6 text-sm leading-7 text-slate-700"
                    >
                        {{ loadError }}
                    </div>

                    <div
                        v-else-if="releases.length === 0"
                        class="rounded-[2rem] border border-dashed border-[#ddc9bc] bg-white px-6 py-8 text-center"
                    >
                        <h2
                            class="text-xl font-semibold tracking-tight text-slate-950"
                        >
                            {{ content.list.emptyTitle }}
                        </h2>
                        <p class="mt-3 text-sm leading-7 text-slate-600">
                            {{ content.list.emptyDescription }}
                        </p>
                    </div>

                    <div v-else class="grid gap-5">
                        <PublicChangelogReleaseCard
                            v-for="release in releases"
                            :key="release.uuid"
                            :release="release"
                        />
                    </div>
                </PublicPageSection>
            </div>
        </main>

        <PublicSiteFooter :can-register="canRegister" />
        <PublicCookieConsent />
    </div>
</template>
