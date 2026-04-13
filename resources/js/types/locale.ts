export type LocaleOption = {
    code: string;
    label: string;
};

export type CurrencyCatalogItem = {
    code: string;
    name: string;
    symbol: string;
    minor_unit: number;
    symbol_position: 'prefix' | 'suffix';
};

export type LocaleSharedData = {
    current: string;
    fallback: string;
    available: LocaleOption[];
    currencies: Record<string, CurrencyCatalogItem>;
};
