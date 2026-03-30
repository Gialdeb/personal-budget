<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { legalContent } from '@/i18n/legal-content';
import PublicLegalLayout from '@/layouts/public/PublicLegalLayout.vue';

const { locale, t } = useI18n();

const content = computed(() =>
    locale.value === 'it' ? legalContent.it.privacy : legalContent.en.privacy,
);
</script>

<template>
    <PublicLegalLayout
        :page-title="content.pageTitle"
        :eyebrow="content.eyebrow"
        :title="content.title"
        :description="content.description"
    >
        <div class="space-y-10">
            <div
                class="rounded-[2rem] border border-[#ece4dc] bg-white p-6 shadow-[0_24px_60px_-40px_rgba(15,23,42,0.25)] sm:p-8"
            >
                <p class="text-base leading-8 text-slate-700">
                    {{ content.intro }}
                </p>
            </div>

            <div class="space-y-5">
                <section
                    v-for="section in content.sections"
                    :key="section.title"
                    class="rounded-[2rem] border border-[#ece4dc] bg-white p-6 shadow-[0_20px_50px_-42px_rgba(15,23,42,0.22)] sm:p-8"
                >
                    <h2
                        class="text-2xl font-semibold tracking-tight text-slate-950"
                    >
                        {{ section.title }}
                    </h2>
                    <p
                        class="mt-4 text-sm leading-8 text-slate-600 sm:text-base"
                    >
                        {{ section.body }}
                    </p>
                </section>
            </div>

            <div
                class="rounded-[2rem] border border-[#f1dfd8] bg-[#fff7f4] p-6 sm:p-8"
            >
                <p class="text-sm leading-7 text-slate-700 sm:text-base">
                    {{ content.sourceNote }}
                </p>
                <p class="mt-4 text-sm leading-7 text-slate-600">
                    {{ t('legal.common.contact') }}
                </p>
            </div>
        </div>
    </PublicLegalLayout>
</template>
