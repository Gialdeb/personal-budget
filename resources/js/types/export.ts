export type ExportDatasetKey =
    | 'transactions'
    | 'accounts'
    | 'categories'
    | 'tracked_items'
    | 'recurring_entries'
    | 'budgets'
    | 'full_export';

export type ExportFormatKey = 'csv' | 'json';

export type ExportPeriodPresetKey =
    | 'all_time'
    | 'this_month'
    | 'last_month'
    | 'this_year'
    | 'custom_range';

export type ExportDatasetDefinition = {
    key: ExportDatasetKey;
    supports_period: boolean;
    formats: ExportFormatKey[];
    default_format: ExportFormatKey;
};

export type ExportPeriodPresetDefinition = {
    key: ExportPeriodPresetKey;
};

export type ExportPageProps = {
    exportPage: {
        datasets: ExportDatasetDefinition[];
        period_presets: ExportPeriodPresetDefinition[];
        defaults: {
            dataset: ExportDatasetKey;
            format: ExportFormatKey;
            period_preset: ExportPeriodPresetKey;
        };
    };
};
