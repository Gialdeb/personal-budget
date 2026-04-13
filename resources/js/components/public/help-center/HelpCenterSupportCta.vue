<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { ArrowRight } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { helpCenterContent } from '@/i18n/help-center-content';
import { dashboard, login, register } from '@/routes';
import { index as supportIndex } from '@/routes/support';

const props = defineProps<{
    canRegister: boolean;
    sourceUrl?: string | null;
    sourceRoute?: string | null;
}>();

const page = usePage();
const { locale } = useI18n();

const content = computed(() =>
    locale.value === 'it' ? helpCenterContent.it : helpCenterContent.en,
);
</script>

<template>
    <section
        class="rounded-[2.25rem] border border-[#ebddd4] bg-[linear-gradient(180deg,#fff8f4_0%,#ffffff_100%)] px-6 py-7 shadow-[0_28px_80px_-54px_rgba(15,23,42,0.22)] sm:px-8 sm:py-8"
    >
        <p
            class="text-[11px] font-semibold tracking-[0.18em] text-[#b65642] uppercase"
        >
            {{ content.support.eyebrow }}
        </p>
        <h2
            class="mt-3 max-w-3xl text-[2rem] leading-tight font-semibold tracking-[-0.03em] text-slate-950"
        >
            {{ content.support.title }}
        </h2>
        <p class="mt-3 max-w-3xl text-sm leading-7 text-slate-600 sm:text-base">
            {{ content.support.description }}
        </p>

        <div class="mt-6 flex flex-col gap-3 sm:flex-row">
            <Link
                :href="
                    page.props.auth.user
                        ? supportIndex({
                              query: {
                                  source_url: props.sourceUrl ?? undefined,
                                  source_route: props.sourceRoute ?? undefined,
                              },
                          })
                        : login()
                "
                class="inline-flex items-center justify-center gap-2 rounded-2xl bg-[#ea5a47] px-5 py-3 text-sm font-semibold text-white transition hover:bg-[#de4f3d]"
            >
                {{
                    page.props.auth.user
                        ? content.support.authPrimary
                        : content.support.guestPrimary
                }}
                <ArrowRight class="size-4" />
            </Link>
            <Link
                v-if="!page.props.auth.user && props.canRegister"
                :href="register()"
                class="inline-flex items-center justify-center rounded-2xl border border-[#e8ddd6] bg-white px-5 py-3 text-sm font-semibold text-slate-700 transition hover:border-[#d8c7bb] hover:text-slate-950"
            >
                {{ content.support.guestSecondary }}
            </Link>
            <Link
                v-else-if="page.props.auth.user"
                :href="dashboard()"
                class="inline-flex items-center justify-center rounded-2xl border border-[#e8ddd6] bg-white px-5 py-3 text-sm font-semibold text-slate-700 transition hover:border-[#d8c7bb] hover:text-slate-950"
            >
                {{ content.support.authSecondary }}
            </Link>
        </div>
    </section>
</template>
