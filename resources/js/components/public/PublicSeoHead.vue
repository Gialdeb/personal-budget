<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { PublicSeoSharedData } from '@/types';

const page = usePage();

const seo = computed(
    () => (page.props.publicSeo ?? null) as PublicSeoSharedData | null,
);

function schemaKey(index: number): string {
    return `public-seo-jsonld-${index}`;
}

function serializeSchema(schema: Record<string, unknown>): string {
    return JSON.stringify(schema);
}
</script>

<template>
    <Head v-if="seo !== null" :title="seo.title">
        <meta
            head-key="description"
            name="description"
            :content="seo.description"
        />
        <meta head-key="robots" name="robots" :content="seo.robots" />
        <meta head-key="og:title" property="og:title" :content="seo.title" />
        <meta
            head-key="og:description"
            property="og:description"
            :content="seo.description"
        />
        <meta head-key="og:type" property="og:type" :content="seo.og_type" />
        <meta
            head-key="og:url"
            property="og:url"
            :content="seo.canonical_url"
        />
        <meta head-key="og:locale" property="og:locale" :content="seo.locale" />
        <meta
            head-key="og:site_name"
            property="og:site_name"
            content="Soamco Budget"
        />
        <meta head-key="twitter:card" name="twitter:card" content="summary" />
        <meta
            head-key="twitter:title"
            name="twitter:title"
            :content="seo.title"
        />
        <meta
            head-key="twitter:description"
            name="twitter:description"
            :content="seo.description"
        />
        <link head-key="canonical" rel="canonical" :href="seo.canonical_url" />
        <link
            v-for="alternate in seo.alternates"
            :key="`${alternate.hreflang}-${alternate.url}`"
            :head-key="`alternate-${alternate.hreflang}`"
            rel="alternate"
            :hreflang="alternate.hreflang"
            :href="alternate.url"
        />
        <component
            :is="'script'"
            v-for="(schema, index) in seo.json_ld"
            :key="schemaKey(index)"
            :head-key="schemaKey(index)"
            type="application/ld+json"
        >
            {{ serializeSchema(schema) }}
        </component>
    </Head>
</template>
