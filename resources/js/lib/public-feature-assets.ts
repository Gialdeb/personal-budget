import { normalizeLocale } from '@/i18n';

export type PublicFeatureAssetName =
    | 'dashboard'
    | 'transactions'
    | 'budget-planning'
    | 'shared-accounts'
    | 'recurring-entries'
    | 'credit-cards';

export type PublicDownloadAssetName = 'android-install' | 'ios-install';

export function resolvePublicFeatureImage(
    locale: string | undefined,
    name: PublicFeatureAssetName,
): string {
    return `/images/features/${normalizeLocale(locale)}/${name}.svg`;
}

export function resolvePublicDownloadImage(
    locale: string | undefined,
    name: PublicDownloadAssetName,
): string {
    return `/images/download-app/${normalizeLocale(locale)}/${name}.svg`;
}
