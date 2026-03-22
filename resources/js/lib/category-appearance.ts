import {
    Armchair,
    BadgeEuro,
    Beef,
    BookOpen,
    BriefcaseBusiness,
    BusFront,
    CarFront,
    Cat,
    ChartColumn,
    CircleDollarSign,
    Coins,
    Dumbbell,
    Factory,
    Film,
    CreditCard,
    FerrisWheel,
    Flower2,
    Gamepad2,
    Gift,
    GraduationCap,
    HandCoins,
    HeartPulse,
    House,
    LampDesk,
    Landmark,
    Palette,
    PawPrint,
    PiggyBank,
    Plane,
    Scissors,
    School,
    Shirt,
    ShoppingCart,
    Sofa,
    Stethoscope,
    Trees,
    Tv,
    Receipt,
    ShieldCheck,
    ShoppingBag,
    Smartphone,
    Sparkles,
    Theater,
    UtensilsCrossed,
    Wallet,
    Wrench,
} from 'lucide-vue-next';
import type { Component } from 'vue';

export type CategoryIconDefinition = {
    value: string;
    key: string;
    component: Component;
};

export type CategoryColorDefinition = {
    value: string;
    key: string;
};

export const categoryIconOptions: CategoryIconDefinition[] = [
    { value: 'shopping-bag', key: 'shoppingBag', component: ShoppingBag },
    { value: 'shopping-cart', key: 'shoppingCart', component: ShoppingCart },
    { value: 'shirt', key: 'shirt', component: Shirt },
    { value: 'house', key: 'house', component: House },
    { value: 'sofa', key: 'sofa', component: Sofa },
    { value: 'armchair', key: 'armchair', component: Armchair },
    { value: 'car-front', key: 'carFront', component: CarFront },
    { value: 'bus-front', key: 'busFront', component: BusFront },
    { value: 'utensils-crossed', key: 'utensilsCrossed', component: UtensilsCrossed },
    { value: 'cat', key: 'cat', component: Cat },
    { value: 'paw-print', key: 'pawPrint', component: PawPrint },
    { value: 'wallet', key: 'wallet', component: Wallet },
    { value: 'credit-card', key: 'creditCard', component: CreditCard },
    { value: 'coins', key: 'coins', component: Coins },
    { value: 'circle-dollar-sign', key: 'circleDollarSign', component: CircleDollarSign },
    { value: 'piggy-bank', key: 'piggyBank', component: PiggyBank },
    { value: 'chart-column', key: 'chartColumn', component: ChartColumn },
    { value: 'landmark', key: 'landmark', component: Landmark },
    { value: 'receipt', key: 'receipt', component: Receipt },
    { value: 'smartphone', key: 'smartphone', component: Smartphone },
    { value: 'tv', key: 'tv', component: Tv },
    { value: 'briefcase-business', key: 'briefcaseBusiness', component: BriefcaseBusiness },
    { value: 'factory', key: 'factory', component: Factory },
    { value: 'graduation-cap', key: 'graduationCap', component: GraduationCap },
    { value: 'school', key: 'school', component: School },
    { value: 'heart-pulse', key: 'heartPulse', component: HeartPulse },
    { value: 'stethoscope', key: 'stethoscope', component: Stethoscope },
    { value: 'shield-check', key: 'shieldCheck', component: ShieldCheck },
    { value: 'plane', key: 'plane', component: Plane },
    { value: 'theater', key: 'theater', component: Theater },
    { value: 'film', key: 'film', component: Film },
    { value: 'gamepad-2', key: 'gamepad2', component: Gamepad2 },
    { value: 'ferris-wheel', key: 'ferrisWheel', component: FerrisWheel },
    { value: 'beef', key: 'beef', component: Beef },
    { value: 'flower-2', key: 'flower2', component: Flower2 },
    { value: 'palette', key: 'palette', component: Palette },
    { value: 'scissors', key: 'scissors', component: Scissors },
    { value: 'dumbbell', key: 'dumbbell', component: Dumbbell },
    { value: 'gift', key: 'gift', component: Gift },
    { value: 'hand-coins', key: 'handCoins', component: HandCoins },
    { value: 'badge-euro', key: 'badgeEuro', component: BadgeEuro },
    { value: 'wrench', key: 'wrench', component: Wrench },
    { value: 'book-open', key: 'bookOpen', component: BookOpen },
    { value: 'lamp-desk', key: 'lampDesk', component: LampDesk },
    { value: 'trees', key: 'trees', component: Trees },
    { value: 'sparkles', key: 'sparkles', component: Sparkles },
];

export const categoryColorOptions: CategoryColorDefinition[] = [
    { value: '#0f766e', key: 'deepTeal' },
    { value: '#047857', key: 'forestGreen' },
    { value: '#0369a1', key: 'oceanBlue' },
    { value: '#1d4ed8', key: 'cobaltBlue' },
    { value: '#6d28d9', key: 'indigo' },
    { value: '#7c3aed', key: 'deepViolet' },
    { value: '#c026d3', key: 'magenta' },
    { value: '#db2777', key: 'raspberryPink' },
    { value: '#e11d48', key: 'rubyRed' },
    { value: '#dc2626', key: 'brightRed' },
    { value: '#ea580c', key: 'burntOrange' },
    { value: '#d97706', key: 'amber' },
    { value: '#ca8a04', key: 'gold' },
    { value: '#4d7c0f', key: 'olive' },
    { value: '#15803d', key: 'grassGreen' },
    { value: '#0891b2', key: 'cyan' },
    { value: '#334155', key: 'slate' },
    { value: '#111827', key: 'graphite' },
];

export const categoryIconMap = Object.fromEntries(
    categoryIconOptions.map((item) => [item.value, item]),
) as Record<string, CategoryIconDefinition>;

export function resolveCategoryIcon(icon: string | null | undefined): Component {
    return categoryIconMap[icon ?? '']?.component ?? FolderFallback;
}

const FolderFallback = Wallet;
