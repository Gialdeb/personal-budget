<?php

namespace App\Services\Categories;

use App\Enums\CategoryDirectionTypeEnum;
use App\Enums\CategoryGroupTypeEnum;
use App\Models\Category;
use App\Models\User;

class CategoryFoundationService
{
    public const CREDIT_CARD_SETTLEMENT_FOUNDATION_KEY = 'credit_card_settlement_transfer';

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
    public static function definitions(): array
    {
        return [
            [
                'foundation_key' => 'income',
                'name' => 'Entrate',
                'slug' => 'entrate',
                'icon' => 'circle-dollar-sign',
                'color' => '#15803d',
                'direction_type' => CategoryDirectionTypeEnum::INCOME,
                'group_type' => CategoryGroupTypeEnum::INCOME,
                'sort_order' => 1,
            ],
            [
                'foundation_key' => 'expense',
                'name' => 'Spese',
                'slug' => 'spese',
                'icon' => 'credit-card',
                'color' => '#e11d48',
                'direction_type' => CategoryDirectionTypeEnum::EXPENSE,
                'group_type' => CategoryGroupTypeEnum::EXPENSE,
                'sort_order' => 2,
            ],
            [
                'foundation_key' => 'bill',
                'name' => 'Bollette',
                'slug' => 'bollette',
                'icon' => 'receipt',
                'color' => '#1d4ed8',
                'direction_type' => CategoryDirectionTypeEnum::EXPENSE,
                'group_type' => CategoryGroupTypeEnum::BILL,
                'sort_order' => 3,
            ],
            [
                'foundation_key' => 'debt',
                'name' => 'Debiti',
                'slug' => 'debiti',
                'icon' => 'hand-coins',
                'color' => '#7c3aed',
                'direction_type' => CategoryDirectionTypeEnum::EXPENSE,
                'group_type' => CategoryGroupTypeEnum::DEBT,
                'sort_order' => 4,
            ],
            [
                'foundation_key' => 'saving',
                'name' => 'Risparmi',
                'slug' => 'risparmi',
                'icon' => 'piggy-bank',
                'color' => '#ca8a04',
                'direction_type' => CategoryDirectionTypeEnum::EXPENSE,
                'group_type' => CategoryGroupTypeEnum::SAVING,
                'sort_order' => 5,
            ],
        ];
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
    public static function defaultChildDefinitions(): array
    {
        return [
            'income' => [
                ['name' => 'Stipendio', 'slug' => 'stipendio', 'icon' => 'briefcase-business', 'color' => '#15803d', 'sort_order' => 10, 'is_selectable' => true],
                ['name' => 'Pensione', 'slug' => 'pensione', 'icon' => 'landmark', 'color' => '#166534', 'sort_order' => 20, 'is_selectable' => true],
                ['name' => 'Freelance', 'slug' => 'freelance', 'icon' => 'laptop-minimal', 'color' => '#0f766e', 'sort_order' => 30, 'is_selectable' => true],
                ['name' => 'Regali ricevuti', 'slug' => 'regali-ricevuti', 'icon' => 'gift', 'color' => '#0891b2', 'sort_order' => 40, 'is_selectable' => true],
                ['name' => 'Rimborso', 'slug' => 'rimborso', 'icon' => 'rotate-ccw', 'color' => '#0369a1', 'sort_order' => 50, 'is_selectable' => true],
                ['name' => 'Altre entrate', 'slug' => 'altre-entrate', 'icon' => 'circle-plus', 'color' => '#16a34a', 'sort_order' => 60, 'is_selectable' => true],
            ],
            'expense' => [
                ['name' => 'Alimentari', 'slug' => 'alimentari', 'icon' => 'shopping-basket', 'color' => '#65a30d', 'sort_order' => 10, 'is_selectable' => true],
                ['name' => 'Ristoranti e bar', 'slug' => 'ristoranti-e-bar', 'icon' => 'utensils-crossed', 'color' => '#ea580c', 'sort_order' => 20, 'is_selectable' => true],
                ['name' => 'Shopping', 'slug' => 'shopping', 'icon' => 'shopping-bag', 'color' => '#db2777', 'sort_order' => 30, 'is_selectable' => true],
                ['name' => 'Salute', 'slug' => 'salute', 'icon' => 'heart-pulse', 'color' => '#dc2626', 'sort_order' => 40, 'is_selectable' => true],
                ['name' => 'Farmacia', 'slug' => 'farmacia', 'icon' => 'pill', 'color' => '#e11d48', 'sort_order' => 50, 'is_selectable' => true],
                ['name' => 'Trasporti', 'slug' => 'trasporti', 'icon' => 'bus-front', 'color' => '#2563eb', 'sort_order' => 60, 'is_selectable' => true],
                [
                    'name' => 'Auto',
                    'slug' => 'auto',
                    'icon' => 'car-front',
                    'color' => '#0f766e',
                    'sort_order' => 70,
                    'is_selectable' => false,
                    'children' => [
                        ['name' => 'Assicurazione', 'slug' => 'auto-assicurazione', 'icon' => 'shield-check', 'color' => '#0f766e', 'sort_order' => 10, 'is_selectable' => true],
                        ['name' => 'Bollo', 'slug' => 'auto-bollo', 'icon' => 'receipt-text', 'color' => '#0d9488', 'sort_order' => 20, 'is_selectable' => true],
                        ['name' => 'Manutenzioni', 'slug' => 'auto-manutenzioni', 'icon' => 'wrench', 'color' => '#14b8a6', 'sort_order' => 30, 'is_selectable' => true],
                    ],
                ],
                [
                    'name' => 'Moto',
                    'slug' => 'moto',
                    'icon' => 'bike',
                    'color' => '#1d4ed8',
                    'sort_order' => 80,
                    'is_selectable' => false,
                    'children' => [
                        ['name' => 'Assicurazione', 'slug' => 'moto-assicurazione', 'icon' => 'shield-check', 'color' => '#1d4ed8', 'sort_order' => 10, 'is_selectable' => true],
                        ['name' => 'Bollo', 'slug' => 'moto-bollo', 'icon' => 'receipt-text', 'color' => '#2563eb', 'sort_order' => 20, 'is_selectable' => true],
                        ['name' => 'Manutenzioni', 'slug' => 'moto-manutenzioni', 'icon' => 'wrench', 'color' => '#3b82f6', 'sort_order' => 30, 'is_selectable' => true],
                    ],
                ],
                [
                    'name' => 'Abbonamenti',
                    'slug' => 'abbonamenti',
                    'icon' => 'repeat',
                    'color' => '#7c3aed',
                    'sort_order' => 90,
                    'is_selectable' => false,
                    'children' => [
                        ['name' => 'Streaming', 'slug' => 'streaming', 'icon' => 'tv', 'color' => '#7c3aed', 'sort_order' => 10, 'is_selectable' => true],
                        ['name' => 'App e software', 'slug' => 'app-e-software', 'icon' => 'smartphone', 'color' => '#8b5cf6', 'sort_order' => 20, 'is_selectable' => true],
                        ['name' => 'Altri abbonamenti', 'slug' => 'altri-abbonamenti', 'icon' => 'tickets', 'color' => '#a855f7', 'sort_order' => 30, 'is_selectable' => true],
                    ],
                ],
                ['name' => 'Tempo libero', 'slug' => 'tempo-libero', 'icon' => 'party-popper', 'color' => '#9333ea', 'sort_order' => 100, 'is_selectable' => true],
                ['name' => 'Viaggi', 'slug' => 'viaggi', 'icon' => 'plane', 'color' => '#0284c7', 'sort_order' => 110, 'is_selectable' => true],
                ['name' => 'Animali domestici', 'slug' => 'animali-domestici', 'icon' => 'paw-print', 'color' => '#b45309', 'sort_order' => 120, 'is_selectable' => true],
                ['name' => 'Istruzione', 'slug' => 'istruzione', 'icon' => 'graduation-cap', 'color' => '#4f46e5', 'sort_order' => 130, 'is_selectable' => true],
                ['name' => 'Cura personale', 'slug' => 'cura-personale', 'icon' => 'sparkles', 'color' => '#c026d3', 'sort_order' => 140, 'is_selectable' => true],
                ['name' => 'Casa', 'slug' => 'casa', 'icon' => 'house', 'color' => '#475569', 'sort_order' => 150, 'is_selectable' => true],
                ['name' => 'Varie', 'slug' => 'varie', 'icon' => 'package', 'color' => '#64748b', 'sort_order' => 160, 'is_selectable' => true],
            ],
            'bill' => [
                ['name' => 'Luce', 'slug' => 'luce', 'icon' => 'lightbulb', 'color' => '#ca8a04', 'sort_order' => 10, 'is_selectable' => true],
                ['name' => 'Gas', 'slug' => 'gas', 'icon' => 'flame', 'color' => '#ea580c', 'sort_order' => 20, 'is_selectable' => true],
                ['name' => 'Acqua', 'slug' => 'acqua', 'icon' => 'droplets', 'color' => '#0284c7', 'sort_order' => 30, 'is_selectable' => true],
                ['name' => 'Internet', 'slug' => 'internet', 'icon' => 'wifi', 'color' => '#2563eb', 'sort_order' => 40, 'is_selectable' => true],
                ['name' => 'Telefono', 'slug' => 'telefono', 'icon' => 'smartphone', 'color' => '#4f46e5', 'sort_order' => 50, 'is_selectable' => true],
                ['name' => 'Condominio', 'slug' => 'condominio', 'icon' => 'building-2', 'color' => '#475569', 'sort_order' => 60, 'is_selectable' => true],
            ],
            'debt' => [
                ['name' => 'Mutuo', 'slug' => 'mutuo', 'icon' => 'house', 'color' => '#7c2d12', 'sort_order' => 10, 'is_selectable' => true],
                ['name' => 'Prestito personale', 'slug' => 'prestito-personale', 'icon' => 'hand-coins', 'color' => '#9a3412', 'sort_order' => 20, 'is_selectable' => true],
                ['name' => 'Carta di credito', 'slug' => 'carta-di-credito', 'icon' => 'credit-card', 'color' => '#b91c1c', 'sort_order' => 30, 'is_selectable' => true],
                ['name' => 'Finanziamento', 'slug' => 'finanziamento', 'icon' => 'badge-percent', 'color' => '#dc2626', 'sort_order' => 40, 'is_selectable' => true],
                ['name' => 'Altri debiti', 'slug' => 'altri-debiti', 'icon' => 'scale', 'color' => '#991b1b', 'sort_order' => 50, 'is_selectable' => true],
            ],
            'saving' => [
                ['name' => 'Fondo emergenza', 'slug' => 'fondo-emergenza', 'icon' => 'shield-plus', 'color' => '#ca8a04', 'sort_order' => 10, 'is_selectable' => true],
                ['name' => 'Risparmio casa', 'slug' => 'risparmio-casa', 'icon' => 'house', 'color' => '#a16207', 'sort_order' => 20, 'is_selectable' => true],
                ['name' => 'Risparmio viaggi', 'slug' => 'risparmio-viaggi', 'icon' => 'plane', 'color' => '#0f766e', 'sort_order' => 30, 'is_selectable' => true],
                ['name' => 'Investimenti', 'slug' => 'investimenti', 'icon' => 'chart-column', 'color' => '#0369a1', 'sort_order' => 40, 'is_selectable' => true],
                ['name' => 'Pensione integrativa', 'slug' => 'pensione-integrativa', 'icon' => 'piggy-bank', 'color' => '#ca8a04', 'sort_order' => 50, 'is_selectable' => true],
                ['name' => 'Obiettivi futuri', 'slug' => 'obiettivi-futuri', 'icon' => 'target', 'color' => '#65a30d', 'sort_order' => 60, 'is_selectable' => true],
            ],
        ];
    }

    public function ensureForUser(User $user): void
    {
        foreach (self::definitions() as $definition) {
            $category = Category::query()->firstOrNew([
                'user_id' => $user->id,
                'foundation_key' => $definition['foundation_key'],
            ]);

            $category->user_id = $user->id;
            $category->parent_id = null;
            $category->foundation_key = $definition['foundation_key'];
            $category->name = $definition['name'];
            $category->slug = $definition['slug'];
            $category->direction_type = $definition['direction_type'];
            $category->group_type = $definition['group_type'];
            $category->is_active = true;
            $category->is_system = true;

            if (! $category->exists) {
                $category->sort_order = $definition['sort_order'];
                $category->icon = $definition['icon'];
                $category->color = $definition['color'];
                $category->is_selectable = true;
            } else {
                $category->icon ??= $definition['icon'];
                $category->color ??= $definition['color'];
            }

            $category->save();

            if ($category->children()->exists()) {
                continue;
            }

            $this->seedDefaultChildren($user, $category, self::defaultChildDefinitions()[$definition['foundation_key']] ?? []);
        }
    }

    public function ensureCreditCardSettlementCategoryForUserId(int $userId): Category
    {
        $category = Category::query()->firstOrNew([
            'user_id' => $userId,
            'foundation_key' => self::CREDIT_CARD_SETTLEMENT_FOUNDATION_KEY,
        ]);

        $category->user_id = $userId;
        $category->account_id = null;
        $category->parent_id = null;
        $category->foundation_key = self::CREDIT_CARD_SETTLEMENT_FOUNDATION_KEY;
        $category->name = 'Regolamento carta di credito';
        $category->slug = 'regolamento-carta-di-credito';
        $category->direction_type = CategoryDirectionTypeEnum::TRANSFER;
        $category->group_type = CategoryGroupTypeEnum::TRANSFER;
        $category->is_active = true;
        $category->is_selectable = false;
        $category->is_system = true;
        $category->sort_order = 999;
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
            $child->save();

            if (($definition['children'] ?? []) !== [] && ! $child->children()->exists()) {
                $this->seedDefaultChildren($user, $child, $definition['children']);
            }
        }
    }
}
