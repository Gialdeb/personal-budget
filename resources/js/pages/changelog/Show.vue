<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowLeft, CircleCheckBig } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import PublicChangelogReleaseCard from '@/components/public/changelog/PublicChangelogReleaseCard.vue';
import PublicChangelogSection from '@/components/public/changelog/PublicChangelogSection.vue';
import PublicRichTextRenderer from '@/components/public/changelog/PublicRichTextRenderer.vue';
import PublicCookieConsent from '@/components/public/PublicCookieConsent.vue';
import PublicPageSection from '@/components/public/PublicPageSection.vue';
import PublicSeoHead from '@/components/public/PublicSeoHead.vue';
import PublicSiteFooter from '@/components/public/PublicSiteFooter.vue';
import PublicSiteHeader from '@/components/public/PublicSiteHeader.vue';
import { changelogContent } from '@/i18n/changelog-content';
import {
    fetchPublicChangelogIndex,
    fetchPublicChangelogRelease,
} from '@/lib/public-changelog';
import { register } from '@/routes';
import { index as changelogIndex } from '@/routes/changelog';
import type { PublicChangelogRelease } from '@/types';

const props = withDefaults(
    defineProps<{
        canRegister: boolean;
        versionLabel: string;
        initialRelease?: PublicChangelogRelease | null;
        initialRelatedReleases?: PublicChangelogRelease[];
    }>(),
    {
        canRegister: true,
        initialRelease: null,
        initialRelatedReleases: () => [],
    },
);

const { locale } = useI18n();
const release = ref<PublicChangelogRelease | null>(null);
const relatedReleases = ref<PublicChangelogRelease[]>([]);
const isLoading = ref(true);
const loadError = ref<string | null>(null);

const content = computed(() =>
    locale.value === 'it' ? changelogContent.it : changelogContent.en,
);

release.value = props.initialRelease;
relatedReleases.value = [...props.initialRelatedReleases];
isLoading.value = release.value === null;

async function loadRelease(): Promise<void> {
    isLoading.value = true;
    loadError.value = null;

    try {
        const [releasePayload, indexPayload] = await Promise.all([
            fetchPublicChangelogRelease(props.versionLabel, locale.value),
            fetchPublicChangelogIndex(locale.value),
        ]);

        release.value = releasePayload;
        relatedReleases.value = indexPayload.filter(
            (item) => item.version_label !== props.versionLabel,
        );
    } catch (error) {
        loadError.value =
            error instanceof Error
                ? error.message
                : 'Unable to load changelog release.';
        release.value = null;
        relatedReleases.value = [];
    } finally {
        isLoading.value = false;
    }
}

onMounted(() => {
    if (release.value === null) {
        void loadRelease();
    }
});

watch(
    () => [locale.value, props.versionLabel],
    () => {
        void loadRelease();
    },
);
</script>

<template>
    <PublicSeoHead />

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

                <div class="max-w-4xl space-y-6">
                    <Link
                        :href="changelogIndex()"
                        class="inline-flex items-center gap-2 rounded-2xl border border-[#e7dad1] bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:border-[#d8c7bb] hover:text-slate-950"
                    >
                        <ArrowLeft class="size-4" />
                        {{ content.detail.backLabel }}
                    </Link>

                    <div v-if="isLoading" class="space-y-4">
                        <div
                            class="h-7 w-32 animate-pulse rounded-full bg-[#fff1ea]"
                        />
                        <div
                            class="h-12 w-4/5 animate-pulse rounded-3xl bg-white"
                        />
                        <div
                            class="h-24 animate-pulse rounded-[2rem] bg-white"
                        />
                    </div>

                    <div
                        v-else-if="loadError"
                        class="rounded-[2rem] border border-[#efd7ce] bg-[#fff6f2] px-6 py-6 text-sm leading-7 text-slate-700"
                    >
                        {{ loadError }}
                    </div>

                    <div
                        v-else-if="!release"
                        class="rounded-[2rem] border border-dashed border-[#ddc9bc] bg-white px-6 py-8"
                    >
                        <h1
                            class="text-[2rem] font-semibold tracking-tight text-slate-950"
                        >
                            {{ content.detail.notFoundTitle }}
                        </h1>
                        <p
                            class="mt-3 max-w-2xl text-sm leading-7 text-slate-600"
                        >
                            {{ content.detail.notFoundDescription }}
                        </p>
                        <div class="mt-5">
                            <Link
                                :href="changelogIndex()"
                                class="inline-flex items-center justify-center rounded-2xl bg-[#ea5a47] px-5 py-3 text-sm font-semibold text-white transition hover:bg-[#de4f3d]"
                            >
                                {{ content.detail.notFoundAction }}
                            </Link>
                        </div>
                    </div>

                    <div v-else class="space-y-5">
                        <div class="flex flex-wrap items-center gap-2">
                            <span
                                class="inline-flex items-center rounded-full border border-[#f2dfd8] bg-[#fff7f4] px-3 py-1 text-[11px] font-semibold tracking-[0.16em] text-[#b65642] uppercase"
                            >
                                {{ release.version_label }}
                            </span>
                            <span
                                class="inline-flex items-center rounded-full border border-[#ebe6df] bg-[#f8f5f2] px-3 py-1 text-[11px] font-semibold tracking-[0.16em] text-slate-600 uppercase"
                            >
                                {{
                                    release.channel === 'beta'
                                        ? content.badges.beta
                                        : content.badges.stable
                                }}
                            </span>
                            <span
                                v-if="release.is_pinned"
                                class="inline-flex items-center rounded-full border border-[#f7d7d1] bg-[#fff1ee] px-3 py-1 text-[11px] font-semibold tracking-[0.16em] text-[#de4f3d] uppercase"
                            >
                                {{ content.badges.pinned }}
                            </span>
                        </div>

                        <div class="space-y-4">
                            <h1
                                class="max-w-4xl text-[2.65rem] leading-none font-semibold tracking-[-0.04em] text-slate-950 sm:text-[3.5rem] lg:text-[4.2rem]"
                            >
                                {{ release.title }}
                            </h1>
                            <PublicRichTextRenderer
                                :content="release.summary"
                            />
                        </div>
                    </div>
                </div>
            </section>

            <div
                class="mx-auto flex w-full max-w-7xl flex-col gap-16 px-6 sm:px-8 lg:gap-18"
            >
                <template v-if="release">
                    <PublicPageSection
                        :eyebrow="content.hero.eyebrow"
                        :title="release.title ?? release.version_label"
                        :description="release.excerpt ?? ''"
                    >
                        <div class="grid gap-6">
                            <PublicChangelogSection
                                v-for="section in release.sections"
                                :key="section.key"
                                :section="section"
                            />
                        </div>
                    </PublicPageSection>

                    <PublicPageSection
                        v-if="relatedReleases.length > 0"
                        :eyebrow="content.hero.eyebrow"
                        :title="content.detail.relatedLabel"
                        description=""
                    >
                        <div class="grid gap-5">
                            <PublicChangelogReleaseCard
                                v-for="relatedRelease in relatedReleases.slice(
                                    0,
                                    3,
                                )"
                                :key="relatedRelease.uuid"
                                :release="relatedRelease"
                            />
                        </div>
                    </PublicPageSection>

                    <PublicPageSection
                        v-if="canRegister && !$page.props.auth.user"
                        :eyebrow="content.hero.eyebrow"
                        title="Soamco Budget"
                        description=""
                    >
                        <div
                            class="flex flex-col gap-3 rounded-[2rem] border border-[#efe4db] bg-[linear-gradient(180deg,#ffffff_0%,#fff8f4_100%)] p-6 shadow-[0_26px_70px_-48px_rgba(15,23,42,0.18)] sm:flex-row sm:items-center sm:justify-between"
                        >
                            <div class="flex items-center gap-3">
                                <div
                                    class="flex size-11 items-center justify-center rounded-2xl bg-[#fff1ea] text-[#ea5a47]"
                                >
                                    <CircleCheckBig class="size-5" />
                                </div>
                                <p class="text-sm leading-7 text-slate-600">
                                    {{ content.hero.description }}
                                </p>
                            </div>
                            <Link
                                :href="register()"
                                class="inline-flex items-center justify-center rounded-2xl bg-[#ea5a47] px-5 py-3 text-sm font-semibold text-white transition hover:bg-[#de4f3d]"
                            >
                                {{ content.hero.primaryLabel }}
                            </Link>
                        </div>
                    </PublicPageSection>
                </template>
            </div>
        </main>

        <PublicSiteFooter :can-register="canRegister" />
        <PublicCookieConsent />
    </div>
</template>
