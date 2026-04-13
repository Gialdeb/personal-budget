<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { CalendarClock, ExternalLink } from 'lucide-vue-next';
import { computed, reactive } from 'vue';
import { useI18n } from 'vue-i18n';
import Heading from '@/components/Heading.vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { edit as editExchangeRates } from '@/routes/exchange-rates';
import type { BreadcrumbItem } from '@/types';

type ExchangeRateItem = {
    id: number;
    rate_date: string | null;
    base_currency_code: string;
    quote_currency_code: string;
    rate: string;
    source: {
        key: string;
        label: string;
        url: string | null;
    };
    fetched_at: string | null;
};

type Props = {
    filters: {
        rate_date: string | null;
        base_currency_code: string | null;
        quote_currency_code: string | null;
    };
    options: {
        currencies: Array<{
            code: string;
            label: string;
        }>;
    };
    exchange_rates: {
        data: ExchangeRateItem[];
        links: Array<{
            url: string | null;
            label: string;
            active: boolean;
        }>;
    };
};

const props = defineProps<Props>();
const { t, locale } = useI18n();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: t('settings.sections.exchangeRates'),
        href: editExchangeRates(),
    },
];

const filters = reactive({
    rate_date: props.filters.rate_date ?? '',
    base_currency_code: props.filters.base_currency_code ?? 'all',
    quote_currency_code: props.filters.quote_currency_code ?? 'all',
});

const currencyOptions = computed(() => props.options.currencies ?? []);
const exchangeRates = computed(() => props.exchange_rates.data ?? []);
const paginationLinks = computed(() =>
    (props.exchange_rates.links ?? []).filter((link) => link.url !== null),
);

const formattedFetchedAt = (value: string | null): string => {
    if (!value) {
        return '—';
    }

    return new Intl.DateTimeFormat(locale.value, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
};

const applyFilters = (): void => {
    router.get(
        editExchangeRates(),
        {
            rate_date: filters.rate_date || undefined,
            base_currency_code:
                filters.base_currency_code !== 'all'
                    ? filters.base_currency_code
                    : undefined,
            quote_currency_code:
                filters.quote_currency_code !== 'all'
                    ? filters.quote_currency_code
                    : undefined,
        },
        {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        },
    );
};

const resetFilters = (): void => {
    filters.rate_date = '';
    filters.base_currency_code = 'all';
    filters.quote_currency_code = 'all';
    applyFilters();
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="t('settings.sections.exchangeRates')" />

        <SettingsLayout>
            <div class="space-y-6">
                <Heading
                    :title="t('settings.exchangeRatesPage.title')"
                    :description="t('settings.exchangeRatesPage.description')"
                />

                <Alert>
                    <CalendarClock class="h-4 w-4" />
                    <AlertDescription class="space-y-1">
                        <p>{{ t('settings.exchangeRatesPage.helper') }}</p>
                        <p>{{ t('settings.exchangeRatesPage.snapshotHint') }}</p>
                    </AlertDescription>
                </Alert>

                <section class="rounded-3xl border bg-card p-5 shadow-sm">
                    <div
                        class="grid gap-4 md:grid-cols-[1fr_1fr_1fr_auto_auto]"
                    >
                        <div class="space-y-2">
                            <Label for="rate-date-filter">
                                {{ t('settings.exchangeRatesPage.filters.rateDate') }}
                            </Label>
                            <Input
                                id="rate-date-filter"
                                v-model="filters.rate_date"
                                type="date"
                            />
                        </div>

                        <div class="space-y-2">
                            <Label for="base-currency-filter">
                                {{ t('settings.exchangeRatesPage.filters.baseCurrency') }}
                            </Label>
                            <Select v-model="filters.base_currency_code">
                                <SelectTrigger id="base-currency-filter">
                                    <SelectValue
                                        :placeholder="
                                            t(
                                                'settings.exchangeRatesPage.filters.allCurrencies',
                                            )
                                        "
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">
                                        {{
                                            t(
                                                'settings.exchangeRatesPage.filters.allCurrencies',
                                            )
                                        }}
                                    </SelectItem>
                                    <SelectItem
                                        v-for="option in currencyOptions"
                                        :key="`base-${option.code}`"
                                        :value="option.code"
                                    >
                                        {{ option.label }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div class="space-y-2">
                            <Label for="quote-currency-filter">
                                {{ t('settings.exchangeRatesPage.filters.quoteCurrency') }}
                            </Label>
                            <Select v-model="filters.quote_currency_code">
                                <SelectTrigger id="quote-currency-filter">
                                    <SelectValue
                                        :placeholder="
                                            t(
                                                'settings.exchangeRatesPage.filters.allCurrencies',
                                            )
                                        "
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">
                                        {{
                                            t(
                                                'settings.exchangeRatesPage.filters.allCurrencies',
                                            )
                                        }}
                                    </SelectItem>
                                    <SelectItem
                                        v-for="option in currencyOptions"
                                        :key="`quote-${option.code}`"
                                        :value="option.code"
                                    >
                                        {{ option.label }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div class="flex items-end">
                            <Button class="w-full md:w-auto" @click="applyFilters">
                                {{ t('settings.exchangeRatesPage.filters.apply') }}
                            </Button>
                        </div>

                        <div class="flex items-end">
                            <Button
                                variant="outline"
                                class="w-full md:w-auto"
                                @click="resetFilters"
                            >
                                {{ t('settings.exchangeRatesPage.filters.reset') }}
                            </Button>
                        </div>
                    </div>
                </section>

                <section class="rounded-3xl border bg-card shadow-sm">
                    <div
                        v-if="exchangeRates.length === 0"
                        class="space-y-2 px-6 py-10 text-center"
                    >
                        <p class="text-base font-semibold">
                            {{ t('settings.exchangeRatesPage.empty.title') }}
                        </p>
                        <p class="text-sm text-muted-foreground">
                            {{
                                t(
                                    'settings.exchangeRatesPage.empty.description',
                                )
                            }}
                        </p>
                    </div>

                    <div v-else class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="border-b bg-muted/40 text-left">
                                <tr>
                                    <th class="px-4 py-3 font-medium">
                                        {{
                                            t(
                                                'settings.exchangeRatesPage.table.rateDate',
                                            )
                                        }}
                                    </th>
                                    <th class="px-4 py-3 font-medium">
                                        {{
                                            t(
                                                'settings.exchangeRatesPage.table.baseCurrency',
                                            )
                                        }}
                                    </th>
                                    <th class="px-4 py-3 font-medium">
                                        {{
                                            t(
                                                'settings.exchangeRatesPage.table.quoteCurrency',
                                            )
                                        }}
                                    </th>
                                    <th class="px-4 py-3 font-medium">
                                        {{ t('settings.exchangeRatesPage.table.rate') }}
                                    </th>
                                    <th class="px-4 py-3 font-medium">
                                        {{
                                            t(
                                                'settings.exchangeRatesPage.table.source',
                                            )
                                        }}
                                    </th>
                                    <th class="px-4 py-3 font-medium">
                                        {{
                                            t(
                                                'settings.exchangeRatesPage.table.fetchedAt',
                                            )
                                        }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="item in exchangeRates"
                                    :key="item.id"
                                    class="border-b last:border-b-0"
                                >
                                    <td class="px-4 py-3">
                                        {{ item.rate_date ?? '—' }}
                                    </td>
                                    <td class="px-4 py-3 font-medium">
                                        {{ item.base_currency_code }}
                                    </td>
                                    <td class="px-4 py-3 font-medium">
                                        {{ item.quote_currency_code }}
                                    </td>
                                    <td class="px-4 py-3">
                                        {{ item.rate }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <a
                                            v-if="item.source.url"
                                            :href="item.source.url"
                                            target="_blank"
                                            rel="noreferrer noopener"
                                            class="inline-flex items-center gap-1 font-medium text-primary hover:underline"
                                        >
                                            {{ item.source.label }}
                                            <ExternalLink class="h-3.5 w-3.5" />
                                        </a>
                                        <span v-else>{{ item.source.label }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-muted-foreground">
                                        {{ formattedFetchedAt(item.fetched_at) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <nav
                    v-if="paginationLinks.length > 0"
                    class="flex flex-wrap items-center gap-2"
                >
                    <Link
                        v-for="link in paginationLinks"
                        :key="link.label"
                        :href="link.url ?? '#'"
                        class="rounded-full border px-3 py-1.5 text-sm transition"
                        :class="
                            link.active
                                ? 'border-primary bg-primary text-primary-foreground'
                                : 'bg-background hover:bg-muted'
                        "
                    >
                        <span v-html="link.label" />
                    </Link>
                </nav>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
