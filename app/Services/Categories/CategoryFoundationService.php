<?php

namespace App\Services\Categories;

use App\Enums\CategoryDirectionTypeEnum;
use App\Enums\CategoryGroupTypeEnum;
use App\Models\Category;
use App\Models\User;
use Illuminate\Support\Facades\Lang;

class CategoryFoundationService
{
    public const CREDIT_CARD_SETTLEMENT_FOUNDATION_KEY = 'credit_card_settlement_transfer';

    private const SUPPORTED_LOCALES = ['it', 'en'];

    /**
     * @var list<array{
     *     foundation_key:string,
     *     name_key:string,
     *     slug:string,
     *     icon:string,
     *     color:string,
     *     direction_type:CategoryDirectionTypeEnum,
     *     group_type:CategoryGroupTypeEnum,
     *     sort_order:int
     * }>
     */
    private const ROOT_DEFINITIONS = [
        [
            'foundation_key' => 'income',
            'name_key' => 'income',
            'slug' => 'entrate',
            'icon' => 'circle-dollar-sign',
            'color' => '#15803d',
            'direction_type' => CategoryDirectionTypeEnum::INCOME,
            'group_type' => CategoryGroupTypeEnum::INCOME,
            'sort_order' => 1,
        ],
        [
            'foundation_key' => 'expense',
            'name_key' => 'expense',
            'slug' => 'spese',
            'icon' => 'credit-card',
            'color' => '#e11d48',
            'direction_type' => CategoryDirectionTypeEnum::EXPENSE,
            'group_type' => CategoryGroupTypeEnum::EXPENSE,
            'sort_order' => 2,
        ],
        [
            'foundation_key' => 'bill',
            'name_key' => 'bill',
            'slug' => 'bollette',
            'icon' => 'receipt',
            'color' => '#1d4ed8',
            'direction_type' => CategoryDirectionTypeEnum::EXPENSE,
            'group_type' => CategoryGroupTypeEnum::BILL,
            'sort_order' => 3,
        ],
        [
            'foundation_key' => 'debt',
            'name_key' => 'debt',
            'slug' => 'debiti',
            'icon' => 'hand-coins',
            'color' => '#7c3aed',
            'direction_type' => CategoryDirectionTypeEnum::EXPENSE,
            'group_type' => CategoryGroupTypeEnum::DEBT,
            'sort_order' => 4,
        ],
        [
            'foundation_key' => 'saving',
            'name_key' => 'saving',
            'slug' => 'risparmi',
            'icon' => 'piggy-bank',
            'color' => '#ca8a04',
            'direction_type' => CategoryDirectionTypeEnum::EXPENSE,
            'group_type' => CategoryGroupTypeEnum::SAVING,
            'sort_order' => 5,
        ],
    ];

    /**
     * @var array<string, list<array{
     *     name_key:string,
     *     slug:string,
     *     icon:string,
     *     color:string,
     *     sort_order:int,
     *     is_selectable:bool,
     *     children?:list<array{name_key:string,slug:string,icon:string,color:string,sort_order:int,is_selectable:bool,children?:list<array{name_key:string,slug:string,icon:string,color:string,sort_order:int,is_selectable:bool}>}>
     * }>>
     */
    private const CHILD_DEFINITIONS = [
        'income' => [
            ['name_key' => 'income_salary', 'slug' => 'stipendio', 'icon' => 'briefcase-business', 'color' => '#15803d', 'sort_order' => 10, 'is_selectable' => true],
            ['name_key' => 'income_pension', 'slug' => 'pensione', 'icon' => 'landmark', 'color' => '#166534', 'sort_order' => 20, 'is_selectable' => true],
            ['name_key' => 'income_freelance', 'slug' => 'freelance', 'icon' => 'laptop-minimal', 'color' => '#0f766e', 'sort_order' => 30, 'is_selectable' => true],
            ['name_key' => 'income_gifts_received', 'slug' => 'regali-ricevuti', 'icon' => 'gift', 'color' => '#0891b2', 'sort_order' => 40, 'is_selectable' => true],
            ['name_key' => 'income_reimbursement', 'slug' => 'rimborso', 'icon' => 'rotate-ccw', 'color' => '#0369a1', 'sort_order' => 50, 'is_selectable' => true],
            ['name_key' => 'income_other', 'slug' => 'altre-entrate', 'icon' => 'circle-plus', 'color' => '#16a34a', 'sort_order' => 60, 'is_selectable' => true],
        ],
        'expense' => [
            ['name_key' => 'expense_groceries', 'slug' => 'alimentari', 'icon' => 'shopping-basket', 'color' => '#65a30d', 'sort_order' => 10, 'is_selectable' => true],
            ['name_key' => 'expense_restaurants', 'slug' => 'ristoranti-e-bar', 'icon' => 'utensils-crossed', 'color' => '#ea580c', 'sort_order' => 20, 'is_selectable' => true],
            ['name_key' => 'expense_shopping', 'slug' => 'shopping', 'icon' => 'shopping-bag', 'color' => '#db2777', 'sort_order' => 30, 'is_selectable' => true],
            ['name_key' => 'expense_health', 'slug' => 'salute', 'icon' => 'heart-pulse', 'color' => '#dc2626', 'sort_order' => 40, 'is_selectable' => true],
            ['name_key' => 'expense_pharmacy', 'slug' => 'farmacia', 'icon' => 'pill', 'color' => '#e11d48', 'sort_order' => 50, 'is_selectable' => true],
            ['name_key' => 'expense_transport', 'slug' => 'trasporti', 'icon' => 'bus-front', 'color' => '#2563eb', 'sort_order' => 60, 'is_selectable' => true],
            [
                'name_key' => 'expense_car',
                'slug' => 'auto',
                'icon' => 'car-front',
                'color' => '#0f766e',
                'sort_order' => 70,
                'is_selectable' => false,
                'children' => [
                    ['name_key' => 'expense_car_insurance', 'slug' => 'auto-assicurazione', 'icon' => 'shield-check', 'color' => '#0f766e', 'sort_order' => 10, 'is_selectable' => true],
                    ['name_key' => 'expense_car_tax', 'slug' => 'auto-bollo', 'icon' => 'receipt-text', 'color' => '#0d9488', 'sort_order' => 20, 'is_selectable' => true],
                    ['name_key' => 'expense_car_maintenance', 'slug' => 'auto-manutenzioni', 'icon' => 'wrench', 'color' => '#14b8a6', 'sort_order' => 30, 'is_selectable' => true],
                ],
            ],
            [
                'name_key' => 'expense_motorcycle',
                'slug' => 'moto',
                'icon' => 'bike',
                'color' => '#1d4ed8',
                'sort_order' => 80,
                'is_selectable' => false,
                'children' => [
                    ['name_key' => 'expense_motorcycle_insurance', 'slug' => 'moto-assicurazione', 'icon' => 'shield-check', 'color' => '#1d4ed8', 'sort_order' => 10, 'is_selectable' => true],
                    ['name_key' => 'expense_motorcycle_tax', 'slug' => 'moto-bollo', 'icon' => 'receipt-text', 'color' => '#2563eb', 'sort_order' => 20, 'is_selectable' => true],
                    ['name_key' => 'expense_motorcycle_maintenance', 'slug' => 'moto-manutenzioni', 'icon' => 'wrench', 'color' => '#3b82f6', 'sort_order' => 30, 'is_selectable' => true],
                ],
            ],
            [
                'name_key' => 'expense_subscriptions',
                'slug' => 'abbonamenti',
                'icon' => 'repeat',
                'color' => '#7c3aed',
                'sort_order' => 90,
                'is_selectable' => false,
                'children' => [
                    ['name_key' => 'expense_subscriptions_streaming', 'slug' => 'streaming', 'icon' => 'tv', 'color' => '#7c3aed', 'sort_order' => 10, 'is_selectable' => true],
                    ['name_key' => 'expense_subscriptions_apps', 'slug' => 'app-e-software', 'icon' => 'smartphone', 'color' => '#8b5cf6', 'sort_order' => 20, 'is_selectable' => true],
                    ['name_key' => 'expense_subscriptions_other', 'slug' => 'altri-abbonamenti', 'icon' => 'tickets', 'color' => '#a855f7', 'sort_order' => 30, 'is_selectable' => true],
                ],
            ],
            ['name_key' => 'expense_leisure', 'slug' => 'tempo-libero', 'icon' => 'party-popper', 'color' => '#9333ea', 'sort_order' => 100, 'is_selectable' => true],
            ['name_key' => 'expense_travel', 'slug' => 'viaggi', 'icon' => 'plane', 'color' => '#0284c7', 'sort_order' => 110, 'is_selectable' => true],
            ['name_key' => 'expense_pets', 'slug' => 'animali-domestici', 'icon' => 'paw-print', 'color' => '#b45309', 'sort_order' => 120, 'is_selectable' => true],
            ['name_key' => 'expense_education', 'slug' => 'istruzione', 'icon' => 'graduation-cap', 'color' => '#4f46e5', 'sort_order' => 130, 'is_selectable' => true],
            ['name_key' => 'expense_personal_care', 'slug' => 'cura-personale', 'icon' => 'sparkles', 'color' => '#c026d3', 'sort_order' => 140, 'is_selectable' => true],
            ['name_key' => 'expense_home', 'slug' => 'casa', 'icon' => 'house', 'color' => '#475569', 'sort_order' => 150, 'is_selectable' => true],
            ['name_key' => 'expense_misc', 'slug' => 'varie', 'icon' => 'package', 'color' => '#64748b', 'sort_order' => 160, 'is_selectable' => true],
        ],
        'bill' => [
            ['name_key' => 'bill_electricity', 'slug' => 'luce', 'icon' => 'lightbulb', 'color' => '#ca8a04', 'sort_order' => 10, 'is_selectable' => true],
            ['name_key' => 'bill_gas', 'slug' => 'gas', 'icon' => 'flame', 'color' => '#ea580c', 'sort_order' => 20, 'is_selectable' => true],
            ['name_key' => 'bill_water', 'slug' => 'acqua', 'icon' => 'droplets', 'color' => '#0284c7', 'sort_order' => 30, 'is_selectable' => true],
            ['name_key' => 'bill_internet', 'slug' => 'internet', 'icon' => 'wifi', 'color' => '#2563eb', 'sort_order' => 40, 'is_selectable' => true],
            ['name_key' => 'bill_phone', 'slug' => 'telefono', 'icon' => 'smartphone', 'color' => '#4f46e5', 'sort_order' => 50, 'is_selectable' => true],
            ['name_key' => 'bill_condominium', 'slug' => 'condominio', 'icon' => 'building-2', 'color' => '#475569', 'sort_order' => 60, 'is_selectable' => true],
        ],
        'debt' => [
            ['name_key' => 'debt_mortgage', 'slug' => 'mutuo', 'icon' => 'house', 'color' => '#7c2d12', 'sort_order' => 10, 'is_selectable' => true],
            ['name_key' => 'debt_personal_loan', 'slug' => 'prestito-personale', 'icon' => 'hand-coins', 'color' => '#9a3412', 'sort_order' => 20, 'is_selectable' => true],
            ['name_key' => 'debt_credit_card', 'slug' => 'carta-di-credito', 'icon' => 'credit-card', 'color' => '#b91c1c', 'sort_order' => 30, 'is_selectable' => true],
            ['name_key' => 'debt_financing', 'slug' => 'finanziamento', 'icon' => 'badge-percent', 'color' => '#dc2626', 'sort_order' => 40, 'is_selectable' => true],
            ['name_key' => 'debt_other', 'slug' => 'altri-debiti', 'icon' => 'scale', 'color' => '#991b1b', 'sort_order' => 50, 'is_selectable' => true],
        ],
        'saving' => [
            ['name_key' => 'saving_emergency_fund', 'slug' => 'fondo-emergenza', 'icon' => 'shield-plus', 'color' => '#ca8a04', 'sort_order' => 10, 'is_selectable' => true],
            ['name_key' => 'saving_home', 'slug' => 'risparmio-casa', 'icon' => 'house', 'color' => '#a16207', 'sort_order' => 20, 'is_selectable' => true],
            ['name_key' => 'saving_travel', 'slug' => 'risparmio-viaggi', 'icon' => 'plane', 'color' => '#0f766e', 'sort_order' => 30, 'is_selectable' => true],
            ['name_key' => 'saving_investments', 'slug' => 'investimenti', 'icon' => 'chart-column', 'color' => '#0369a1', 'sort_order' => 40, 'is_selectable' => true],
            ['name_key' => 'saving_retirement', 'slug' => 'pensione-integrativa', 'icon' => 'piggy-bank', 'color' => '#ca8a04', 'sort_order' => 50, 'is_selectable' => true],
            ['name_key' => 'saving_future_goals', 'slug' => 'obiettivi-futuri', 'icon' => 'target', 'color' => '#65a30d', 'sort_order' => 60, 'is_selectable' => true],
        ],
    ];

    /**
     * @return list<array{
     *     foundation_key:string,
     *     name:string,
     *     slug:string,
     *     icon:string,
     *     color:string,
     *     direction_type:CategoryDirectionTypeEnum,
     *     group_type:CategoryGroupTypeEnum,
     *     sort_order:int
     * }>
     */
    public static function definitions(string $locale = 'it'): array
    {
        $resolvedLocale = self::resolveFoundationLocale($locale);

        return array_map(
            fn (array $definition): array => [
                'foundation_key' => $definition['foundation_key'],
                'name' => self::label("roots.{$definition['name_key']}", $resolvedLocale),
                'slug' => $definition['slug'],
                'icon' => $definition['icon'],
                'color' => $definition['color'],
                'direction_type' => $definition['direction_type'],
                'group_type' => $definition['group_type'],
                'sort_order' => $definition['sort_order'],
            ],
            self::ROOT_DEFINITIONS,
        );
    }

    /**
     * @return array<string, list<array{
     *     name:string,
     *     slug:string,
     *     icon:string,
     *     color:string,
     *     sort_order:int,
     *     is_selectable:bool,
     *     children?:list<array{name:string,slug:string,icon:string,color:string,sort_order:int,is_selectable:bool}>
     * }>>
     */
    public static function defaultChildDefinitions(string $locale = 'it'): array
    {
        $resolvedLocale = self::resolveFoundationLocale($locale);

        return collect(self::CHILD_DEFINITIONS)
            ->map(fn (array $definitions): array => self::localizeDefinitions($definitions, $resolvedLocale))
            ->all();
    }

    public function ensureForUser(User $user): void
    {
        $locale = self::resolveFoundationLocale($user->preferredLocale());

        foreach (self::definitions($locale) as $definition) {
            $category = Category::query()->firstOrNew([
                'user_id' => $user->id,
                'foundation_key' => $definition['foundation_key'],
            ]);

            $category->user_id = $user->id;
            $category->parent_id = null;
            $category->foundation_key = $definition['foundation_key'];
            $category->direction_type = $definition['direction_type'];
            $category->group_type = $definition['group_type'];
            $category->is_active = true;
            $category->is_system = true;
            $category->is_selectable = true;

            if (! $category->exists) {
                $category->name = $definition['name'];
                $category->slug = $definition['slug'];
                $category->sort_order = $definition['sort_order'];
                $category->icon = $definition['icon'];
                $category->color = $definition['color'];
            } else {
                if (self::nameIsCanonicalRootDefault($category->foundation_key, $category->name)) {
                    $category->name = $definition['name'];
                }

                if (self::slugIsCanonicalRootDefault($category->foundation_key, $category->slug)) {
                    $category->slug = $definition['slug'];
                }

                $category->icon ??= $definition['icon'];
                $category->color ??= $definition['color'];
            }

            $category->save();

            if ($category->children()->exists()) {
                continue;
            }

            $this->seedDefaultChildren(
                $user,
                $category,
                self::defaultChildDefinitions($locale)[$definition['foundation_key']] ?? [],
            );
        }
    }

    public function backfillLocalizedDefaultsForUser(User $user): void
    {
        $locale = self::resolveFoundationLocale($user->preferredLocale());
        $definitionsByFoundation = collect(self::definitions($locale))
            ->keyBy('foundation_key');

        foreach ($definitionsByFoundation as $foundationKey => $definition) {
            $root = Category::query()
                ->ownedBy($user->id)
                ->where('foundation_key', $foundationKey)
                ->first();

            if (! $root instanceof Category) {
                continue;
            }

            if (self::nameIsCanonicalRootDefault($foundationKey, $root->name)) {
                $root->name = $definition['name'];
                $root->save();
            }

            $this->backfillLocalizedChildren(
                $user,
                $root,
                self::defaultChildDefinitions($locale)[$foundationKey] ?? [],
            );
        }
    }

    public function ensureCreditCardSettlementCategoryForUserId(int $userId): Category
    {
        $user = User::query()->findOrFail($userId);
        $locale = self::resolveFoundationLocale($user->preferredLocale());
        $category = Category::query()->firstOrNew([
            'user_id' => $userId,
            'foundation_key' => self::CREDIT_CARD_SETTLEMENT_FOUNDATION_KEY,
        ]);
        $categoryExists = $category->exists;

        $category->user_id = $userId;
        $category->account_id = null;
        $category->parent_id = null;
        $category->foundation_key = self::CREDIT_CARD_SETTLEMENT_FOUNDATION_KEY;
        $category->direction_type = CategoryDirectionTypeEnum::TRANSFER;
        $category->group_type = CategoryGroupTypeEnum::TRANSFER;
        $category->is_active = true;
        $category->is_selectable = false;
        $category->is_system = true;
        $category->sort_order = 999;

        if (! $categoryExists || self::nameIsCanonicalRootDefault($category->foundation_key, $category->name)) {
            $category->name = self::creditCardSettlementName($locale);
        }

        if (! $categoryExists || self::slugIsCanonicalRootDefault($category->foundation_key, $category->slug)) {
            $category->slug = 'regolamento-carta-di-credito';
        }

        $category->icon ??= 'credit-card';
        $category->color ??= '#475569';
        $category->save();

        return $category;
    }

    /**
     * @param  list<array{
     *     name:string,
     *     slug:string,
     *     icon:string,
     *     color:string,
     *     sort_order:int,
     *     is_selectable:bool,
     *     children?:list<array{name:string,slug:string,icon:string,color:string,sort_order:int,is_selectable:bool}>
     * }>  $definitions
     */
    protected function seedDefaultChildren(User $user, Category $parent, array $definitions): void
    {
        foreach ($definitions as $definition) {
            $child = Category::query()->firstOrNew([
                'user_id' => $user->id,
                'account_id' => null,
                'parent_id' => $parent->id,
                'slug' => $definition['slug'],
            ]);

            if (! $child->exists) {
                $child->user_id = $user->id;
                $child->account_id = null;
                $child->parent_id = $parent->id;
                $child->name = $definition['name'];
                $child->slug = $definition['slug'];
                $child->foundation_key = null;
                $child->direction_type = $parent->direction_type;
                $child->group_type = $parent->group_type;
                $child->icon = $definition['icon'];
                $child->color = $definition['color'];
                $child->sort_order = $definition['sort_order'];
                $child->is_active = true;
                $child->is_selectable = $definition['is_selectable'];
                $child->is_system = false;
            } elseif (self::nameIsCanonicalChildDefault($definition['slug'], $child->name)) {
                $child->name = $definition['name'];
            }

            $child->icon ??= $definition['icon'];
            $child->color ??= $definition['color'];
            $child->save();

            if (($definition['children'] ?? []) !== [] && ! $child->children()->exists()) {
                $this->seedDefaultChildren($user, $child, $definition['children']);
            }
        }
    }

    protected function backfillLocalizedChildren(User $user, Category $parent, array $definitions): void
    {
        if ($definitions === []) {
            return;
        }

        $children = Category::query()
            ->ownedBy($user->id)
            ->where('parent_id', $parent->id)
            ->get()
            ->keyBy('slug');

        foreach ($definitions as $definition) {
            $child = $children->get($definition['slug']);

            if (! $child instanceof Category) {
                continue;
            }

            if (self::nameIsCanonicalChildDefault($definition['slug'], $child->name)) {
                $child->name = $definition['name'];
                $child->save();
            }

            $this->backfillLocalizedChildren($user, $child, $definition['children'] ?? []);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $definitions
     * @return list<array<string, mixed>>
     */
    protected static function localizeDefinitions(array $definitions, string $locale): array
    {
        return array_map(function (array $definition) use ($locale): array {
            $localized = [
                ...$definition,
                'name' => self::label("children.{$definition['name_key']}", $locale),
            ];

            if (($definition['children'] ?? []) !== []) {
                $localized['children'] = self::localizeDefinitions(
                    $definition['children'],
                    $locale,
                );
            }

            unset($localized['name_key']);

            return $localized;
        }, $definitions);
    }

    public static function resolveFoundationLocale(?string $locale): string
    {
        $normalized = strtolower(str_replace('_', '-', (string) $locale));

        return str_starts_with($normalized, 'en') ? 'en' : 'it';
    }

    public static function localizedRootName(string $foundationKey, string $locale): string
    {
        if ($foundationKey === self::CREDIT_CARD_SETTLEMENT_FOUNDATION_KEY) {
            return self::creditCardSettlementName($locale);
        }

        foreach (self::definitions($locale) as $definition) {
            if ($definition['foundation_key'] === $foundationKey) {
                return $definition['name'];
            }
        }

        return $foundationKey;
    }

    protected static function label(string $key, string $locale): string
    {
        return (string) Lang::get("category-foundation.{$key}", [], $locale);
    }

    protected static function creditCardSettlementName(string $locale): string
    {
        return self::label(
            'roots.'.self::CREDIT_CARD_SETTLEMENT_FOUNDATION_KEY,
            $locale,
        );
    }

    public static function nameIsCanonicalRootDefault(?string $foundationKey, string $name): bool
    {
        if (! is_string($foundationKey) || $foundationKey === '') {
            return false;
        }

        foreach (self::SUPPORTED_LOCALES as $locale) {
            if ($name === self::localizedRootName($foundationKey, $locale)) {
                return true;
            }
        }

        return false;
    }

    protected static function slugIsCanonicalRootDefault(?string $foundationKey, string $slug): bool
    {
        if (! is_string($foundationKey) || $foundationKey === '') {
            return false;
        }

        foreach (self::ROOT_DEFINITIONS as $definition) {
            if ($definition['foundation_key'] === $foundationKey) {
                return $slug === $definition['slug'];
            }
        }

        return $foundationKey === self::CREDIT_CARD_SETTLEMENT_FOUNDATION_KEY
            && $slug === 'regolamento-carta-di-credito';
    }

    public static function nameIsCanonicalChildDefault(string $slug, string $name): bool
    {
        foreach (self::SUPPORTED_LOCALES as $locale) {
            foreach (self::defaultChildDefinitions($locale) as $definitions) {
                foreach (self::flattenChildDefinitions($definitions) as $definition) {
                    if ($definition['slug'] === $slug && $definition['name'] === $name) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * @param  list<array<string, mixed>>  $definitions
     * @return list<array<string, mixed>>
     */
    protected static function flattenChildDefinitions(array $definitions): array
    {
        $flat = [];

        foreach ($definitions as $definition) {
            $flat[] = $definition;

            if (($definition['children'] ?? []) !== []) {
                $flat = [
                    ...$flat,
                    ...self::flattenChildDefinitions($definition['children']),
                ];
            }
        }

        return $flat;
    }
}
