import {
    Car,
    Coffee,
    Fuel,
    Heart,
    Home,
    ShoppingCart,
    Utensils,
    Wallet,
    Zap,
} from 'lucide-vue-next';
import {
    categoryIconOptions,
    resolveCategoryIcon,
} from '@/lib/category-appearance';

const legacyIcons = {
    Car,
    Coffee,
    Fuel,
    Heart,
    Home,
    ShoppingCart,
    Utensils,
    Wallet,
    Zap,
};
export const transactionTemplateIconChoices = [
    ...Object.entries(legacyIcons).map(([value, component]) => ({
        value,
        label: value,
        component,
    })),
    ...categoryIconOptions.map((option) => ({
        ...option,
        label: option.value,
    })),
];
export function transactionTemplateIcon(icon: string) {
    return (
        legacyIcons[icon as keyof typeof legacyIcons] ??
        resolveCategoryIcon(icon)
    );
}
export function transactionTemplateColorStyle(color: string) {
    return /^#[0-9a-f]{6}$/i.test(color)
        ? {
              color,
              backgroundColor: `color-mix(in srgb, ${color} 15%, var(--background))`,
          }
        : undefined;
}
