export type CatalogBankOption = {
    uuid: string;
    name: string;
    slug: string;
    country_code: string | null;
};

export type UserBankItem = {
    uuid: string;
    bank_uuid: string | null;
    name: string;
    slug: string;
    is_custom: boolean;
    is_active: boolean;
    source_label: string;
    catalog_bank: CatalogBankOption | null;
    accounts_count: number;
    used: boolean;
    is_deletable: boolean;
};

export type BanksPageProps = {
    banks: {
        data: UserBankItem[];
        summary: {
            total_count: number;
            active_count: number;
            custom_count: number;
            catalog_count: number;
            used_count: number;
        };
    };
    catalog: {
        available: CatalogBankOption[];
    };
};
