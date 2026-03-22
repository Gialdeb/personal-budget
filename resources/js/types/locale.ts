export type LocaleOption = {
    code: string;
    label: string;
};

export type LocaleSharedData = {
    current: string;
    fallback: string;
    available: LocaleOption[];
};
