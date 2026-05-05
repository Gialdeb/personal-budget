<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->boolean('name_is_custom')->default(false)->after('name');
        });

        $this->backfillExistingCustomNames();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('name_is_custom');
        });
    }

    protected function backfillExistingCustomNames(): void
    {
        DB::table('categories')
            ->leftJoin('users', 'users.id', '=', 'categories.user_id')
            ->select([
                'categories.id',
                'categories.name',
                'categories.slug',
                'categories.foundation_key',
                'categories.is_system',
                'users.locale',
            ])
            ->orderBy('categories.id')
            ->chunkById(200, function ($categories): void {
                foreach ($categories as $category) {
                    $locale = $this->foundationLocale($category->locale ?? null);
                    $expectedDefault = $this->expectedDefaultName(
                        $category->foundation_key,
                        $category->slug,
                        $locale,
                    );

                    if ($expectedDefault === null) {
                        continue;
                    }

                    if ((string) $category->name !== $expectedDefault) {
                        DB::table('categories')
                            ->where('id', $category->id)
                            ->update(['name_is_custom' => true]);
                    }
                }
            }, 'categories.id', 'id');
    }

    protected function foundationLocale(?string $locale): string
    {
        $normalized = strtolower(str_replace('_', '-', (string) $locale));

        return str_starts_with($normalized, 'en') ? 'en' : 'it';
    }

    protected function expectedDefaultName(?string $foundationKey, ?string $slug, string $locale): ?string
    {
        if (is_string($foundationKey) && $foundationKey !== '') {
            return $this->rootDefaultNames($locale)[$foundationKey] ?? null;
        }

        if (! is_string($slug) || $slug === '') {
            return null;
        }

        return $this->childDefaultNames($locale)[$slug] ?? null;
    }

    /**
     * @return array<string, string>
     */
    protected function rootDefaultNames(string $locale): array
    {
        return $locale === 'en'
            ? [
                'income' => 'Income',
                'expense' => 'Expenses',
                'bill' => 'Bills',
                'debt' => 'Debts',
                'saving' => 'Savings',
                'internal_transfer' => 'Transfer between accounts',
                'credit_card_settlement_transfer' => 'Credit card settlement',
            ]
            : [
                'income' => 'Entrate',
                'expense' => 'Spese',
                'bill' => 'Bollette',
                'debt' => 'Debiti',
                'saving' => 'Risparmi',
                'internal_transfer' => 'Trasferimento tra conti',
                'credit_card_settlement_transfer' => 'Regolamento carta di credito',
            ];
    }

    /**
     * @return array<string, string>
     */
    protected function childDefaultNames(string $locale): array
    {
        $italian = [
            'stipendio' => 'Stipendio',
            'pensione' => 'Pensione',
            'freelance' => 'Freelance',
            'regali-ricevuti' => 'Regali ricevuti',
            'rimborso' => 'Rimborso',
            'altre-entrate' => 'Altre entrate',
            'alimentari' => 'Alimentari',
            'ristoranti-e-bar' => 'Ristoranti e bar',
            'shopping' => 'Shopping',
            'salute' => 'Salute',
            'farmacia' => 'Farmacia',
            'trasporti' => 'Trasporti',
            'auto' => 'Auto',
            'auto-assicurazione' => 'Assicurazione',
            'auto-bollo' => 'Bollo',
            'auto-manutenzioni' => 'Manutenzioni',
            'moto' => 'Moto',
            'moto-assicurazione' => 'Assicurazione',
            'moto-bollo' => 'Bollo',
            'moto-manutenzioni' => 'Manutenzioni',
            'abbonamenti' => 'Abbonamenti',
            'streaming' => 'Streaming',
            'app-e-software' => 'App e software',
            'altri-abbonamenti' => 'Altri abbonamenti',
            'tempo-libero' => 'Tempo libero',
            'viaggi' => 'Viaggi',
            'animali-domestici' => 'Animali domestici',
            'istruzione' => 'Istruzione',
            'cura-personale' => 'Cura personale',
            'casa' => 'Casa',
            'varie' => 'Varie',
            'luce' => 'Luce',
            'gas' => 'Gas',
            'acqua' => 'Acqua',
            'internet' => 'Internet',
            'telefono' => 'Telefono',
            'condominio' => 'Condominio',
            'mutuo' => 'Mutuo',
            'prestito-personale' => 'Prestito personale',
            'carta-di-credito' => 'Carta di credito',
            'finanziamento' => 'Finanziamento',
            'altri-debiti' => 'Altri debiti',
            'fondo-emergenza' => 'Fondo emergenza',
            'risparmio-casa' => 'Risparmio casa',
            'risparmio-viaggi' => 'Risparmio viaggi',
            'investimenti' => 'Investimenti',
            'pensione-integrativa' => 'Pensione integrativa',
            'obiettivi-futuri' => 'Obiettivi futuri',
        ];

        if ($locale !== 'en') {
            return $italian;
        }

        return [
            ...$italian,
            'pensione' => 'Pension',
            'regali-ricevuti' => 'Gifts received',
            'altre-entrate' => 'Other income',
            'alimentari' => 'Groceries',
            'ristoranti-e-bar' => 'Restaurants and bars',
            'salute' => 'Health',
            'farmacia' => 'Pharmacy',
            'trasporti' => 'Transport',
            'auto' => 'Car',
            'auto-assicurazione' => 'Insurance',
            'auto-bollo' => 'Road tax',
            'auto-manutenzioni' => 'Maintenance',
            'moto' => 'Motorcycle',
            'moto-assicurazione' => 'Insurance',
            'moto-bollo' => 'Road tax',
            'moto-manutenzioni' => 'Maintenance',
            'abbonamenti' => 'Subscriptions',
            'app-e-software' => 'Apps and software',
            'altri-abbonamenti' => 'Other subscriptions',
            'tempo-libero' => 'Leisure',
            'viaggi' => 'Travel',
            'animali-domestici' => 'Pets',
            'istruzione' => 'Education',
            'cura-personale' => 'Personal care',
            'casa' => 'Home',
            'varie' => 'Miscellaneous',
            'luce' => 'Electricity',
            'acqua' => 'Water',
            'telefono' => 'Phone',
            'condominio' => 'Condo fees',
            'mutuo' => 'Mortgage',
            'prestito-personale' => 'Personal loan',
            'carta-di-credito' => 'Credit card',
            'finanziamento' => 'Financing',
            'altri-debiti' => 'Other debts',
            'fondo-emergenza' => 'Emergency fund',
            'risparmio-casa' => 'Home savings',
            'risparmio-viaggi' => 'Travel savings',
            'pensione-integrativa' => 'Retirement savings',
            'obiettivi-futuri' => 'Future goals',
        ];
    }
};
