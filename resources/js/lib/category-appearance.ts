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
    label: string;
    component: Component;
};

export type CategoryColorDefinition = {
    value: string;
    label: string;
};

export const categoryIconOptions: CategoryIconDefinition[] = [
    { value: 'shopping-bag', label: 'Shopping', component: ShoppingBag },
    { value: 'shopping-cart', label: 'Carrello', component: ShoppingCart },
    { value: 'shirt', label: 'Abbigliamento', component: Shirt },
    { value: 'house', label: 'Casa', component: House },
    { value: 'sofa', label: 'Arredo', component: Sofa },
    { value: 'armchair', label: 'Relax', component: Armchair },
    { value: 'car-front', label: 'Auto', component: CarFront },
    { value: 'bus-front', label: 'Trasporti', component: BusFront },
    { value: 'utensils-crossed', label: 'Cibo', component: UtensilsCrossed },
    { value: 'cat', label: 'Animali', component: Cat },
    { value: 'paw-print', label: 'Pet care', component: PawPrint },
    { value: 'wallet', label: 'Portafoglio', component: Wallet },
    { value: 'credit-card', label: 'Carta', component: CreditCard },
    { value: 'coins', label: 'Liquidità', component: Coins },
    { value: 'circle-dollar-sign', label: 'Entrate', component: CircleDollarSign },
    { value: 'piggy-bank', label: 'Risparmio', component: PiggyBank },
    { value: 'chart-column', label: 'Investimenti', component: ChartColumn },
    { value: 'landmark', label: 'Tasse', component: Landmark },
    { value: 'receipt', label: 'Bollette', component: Receipt },
    { value: 'smartphone', label: 'Digitale', component: Smartphone },
    { value: 'tv', label: 'Streaming', component: Tv },
    { value: 'briefcase-business', label: 'Lavoro', component: BriefcaseBusiness },
    { value: 'factory', label: 'Azienda', component: Factory },
    { value: 'graduation-cap', label: 'Formazione', component: GraduationCap },
    { value: 'school', label: 'Scuola', component: School },
    { value: 'heart-pulse', label: 'Salute', component: HeartPulse },
    { value: 'stethoscope', label: 'Visite', component: Stethoscope },
    { value: 'shield-check', label: 'Assicurazione', component: ShieldCheck },
    { value: 'plane', label: 'Viaggi', component: Plane },
    { value: 'theater', label: 'Tempo libero', component: Theater },
    { value: 'film', label: 'Cinema', component: Film },
    { value: 'gamepad-2', label: 'Gaming', component: Gamepad2 },
    { value: 'ferris-wheel', label: 'Svago', component: FerrisWheel },
    { value: 'beef', label: 'Spesa alimentare', component: Beef },
    { value: 'flower-2', label: 'Benessere', component: Flower2 },
    { value: 'palette', label: 'Beauty', component: Palette },
    { value: 'scissors', label: 'Parrucchiere', component: Scissors },
    { value: 'dumbbell', label: 'Sport', component: Dumbbell },
    { value: 'gift', label: 'Regali', component: Gift },
    { value: 'hand-coins', label: 'Debiti', component: HandCoins },
    { value: 'badge-euro', label: 'Stipendio', component: BadgeEuro },
    { value: 'wrench', label: 'Manutenzione', component: Wrench },
    { value: 'book-open', label: 'Libri', component: BookOpen },
    { value: 'lamp-desk', label: 'Ufficio', component: LampDesk },
    { value: 'trees', label: 'Outdoor', component: Trees },
    { value: 'sparkles', label: 'Varie', component: Sparkles },
];

export const categoryColorOptions: CategoryColorDefinition[] = [
    { value: '#0f766e', label: 'Teal profondo' },
    { value: '#047857', label: 'Verde bosco' },
    { value: '#0369a1', label: 'Blu oceano' },
    { value: '#1d4ed8', label: 'Blu cobalto' },
    { value: '#6d28d9', label: 'Indaco' },
    { value: '#7c3aed', label: 'Viola intenso' },
    { value: '#c026d3', label: 'Magenta' },
    { value: '#db2777', label: 'Rosa lampone' },
    { value: '#e11d48', label: 'Rosso rubino' },
    { value: '#dc2626', label: 'Rosso vivo' },
    { value: '#ea580c', label: 'Arancio bruciato' },
    { value: '#d97706', label: 'Ambra' },
    { value: '#ca8a04', label: 'Oro' },
    { value: '#4d7c0f', label: 'Oliva' },
    { value: '#15803d', label: 'Verde prato' },
    { value: '#0891b2', label: 'Ciano' },
    { value: '#334155', label: 'Ardesia' },
    { value: '#111827', label: 'Grafite' },
];

export const categoryIconMap = Object.fromEntries(
    categoryIconOptions.map((item) => [item.value, item]),
) as Record<string, CategoryIconDefinition>;

export function resolveCategoryIcon(icon: string | null | undefined): Component {
    return categoryIconMap[icon ?? '']?.component ?? FolderFallback;
}

const FolderFallback = Wallet;
