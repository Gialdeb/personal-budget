<?php

namespace Database\Seeders;

use App\Enums\TransactionDirectionEnum;
use App\Enums\TransactionSourceTypeEnum;
use App\Enums\TransactionStatusEnum;
use App\Models\Account;
use App\Models\Category;
use App\Models\Merchant;
use App\Models\Scope;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Seeder;

class FakeTransactionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user = User::first();

        if (! $user) {
            return;
        }

        $accounts = Account::where('user_id', $user->id)->get()->keyBy('name');
        $scopes = Scope::where('user_id', $user->id)->get()->keyBy('name');
        $categories = Category::where('user_id', $user->id)->get()->keyBy('name');
        $merchants = Merchant::where('user_id', $user->id)->get()->keyBy('name');

        $rows = $this->transactionRows();

        foreach ($rows as $row) {
            Transaction::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'account_id' => $accounts[$row['account']]->id ?? null,
                    'transaction_date' => $row['date'],
                    'description' => $row['description'],
                ],
                [
                    'scope_id' => $scopes[$row['scope']]->id ?? null,
                    'category_id' => $categories[$row['category']]->id ?? null,
                    'merchant_id' => $row['merchant'] ? ($merchants[$row['merchant']]->id ?? null) : null,
                    'amount' => $row['amount'],
                    'direction' => TransactionDirectionEnum::from($row['direction']),
                    'currency' => 'EUR',
                    'source_type' => TransactionSourceTypeEnum::MANUAL,
                    'status' => TransactionStatusEnum::CONFIRMED,
                    'is_transfer' => false,
                ]
            );
        }
    }

    /**
     * @return array<int, array{
     *     date: string,
     *     account: string,
     *     scope: string,
     *     category: string,
     *     merchant: string|null,
     *     direction: string,
     *     amount: float,
     *     description: string
     * }>
     */
    private function transactionRows(): array
    {
        return [
            $this->row('2024-01-05', 'Conto Intesa Personale', 'Personale', 'Stipendio', null, 'income', 1650.00, 'Stipendio gennaio 2024'),
            $this->row('2024-01-08', 'Carta Revolut', 'Personale', 'Alimentari', 'Decò', 'expense', 68.40, 'Spesa dispensa gennaio 2024'),
            $this->row('2024-01-12', 'Carta Revolut', 'Personale', 'Auto', 'Q8', 'expense', 52.00, 'Rifornimento gennaio 2024'),
            $this->row('2024-01-16', 'Conto Intesa Personale', 'Casa 1', 'Luce', 'Enel Energia', 'expense', 84.20, 'Bolletta luce gennaio 2024'),
            $this->row('2024-01-22', 'Conto Intesa Personale', 'Casa 1', 'Condominio', 'Condominio Via Roma', 'expense', 100.00, 'Quota condominio gennaio 2024'),

            $this->row('2024-02-05', 'Conto Intesa Personale', 'Personale', 'Stipendio', null, 'income', 1650.00, 'Stipendio febbraio 2024'),
            $this->row('2024-02-09', 'Carta Revolut', 'Personale', 'Alimentari', 'Lidl', 'expense', 74.90, 'Spesa settimana febbraio 2024'),
            $this->row('2024-02-14', 'Conto Intesa Personale', 'Casa 1', 'Acqua', 'GORI', 'expense', 38.50, 'Bolletta acqua febbraio 2024'),
            $this->row('2024-02-19', 'Conto Intesa Personale', 'Casa 1', 'Internet', null, 'expense', 27.90, 'Canone internet febbraio 2024'),
            $this->row('2024-02-24', 'Carta Revolut', 'Personale', 'Extra', 'Amazon', 'expense', 44.90, 'Acquisto casa febbraio 2024'),

            $this->row('2024-03-05', 'Conto Intesa Personale', 'Personale', 'Stipendio', null, 'income', 1655.00, 'Stipendio marzo 2024'),
            $this->row('2024-03-08', 'Carta Revolut', 'Personale', 'Alimentari', 'Sole365', 'expense', 79.30, 'Spesa famiglia marzo 2024'),
            $this->row('2024-03-15', 'Carta Revolut', 'Personale', 'Salute', 'Farmacia Centrale', 'expense', 31.20, 'Farmacia marzo 2024'),
            $this->row('2024-03-21', 'Conto Intesa Personale', 'Casa 1', 'Gas', null, 'expense', 92.00, 'Bolletta gas marzo 2024'),
            $this->row('2024-03-27', 'Conto Intesa Personale', 'Personale', 'Altre entrate', null, 'income', 320.00, 'Prestazione freelance marzo 2024'),

            $this->row('2024-04-05', 'Conto Intesa Personale', 'Personale', 'Stipendio', null, 'income', 1660.00, 'Stipendio aprile 2024'),
            $this->row('2024-04-09', 'Carta Revolut', 'Personale', 'Alimentari', 'Conad', 'expense', 76.50, 'Spesa famiglia aprile 2024'),
            $this->row('2024-04-13', 'Carta Revolut', 'Personale', 'Auto', 'Q8', 'expense', 49.00, 'Rifornimento aprile 2024'),
            $this->row('2024-04-17', 'Conto Intesa Personale', 'Casa 1', 'Luce', 'Enel Energia', 'expense', 79.40, 'Bolletta luce aprile 2024'),
            $this->row('2024-04-26', 'Cassa Casa 1', 'Casa 1', 'Extra', null, 'expense', 35.00, 'Piccole spese casa aprile 2024'),

            $this->row('2024-05-05', 'Conto Intesa Personale', 'Personale', 'Stipendio', null, 'income', 1660.00, 'Stipendio maggio 2024'),
            $this->row('2024-05-10', 'Carta Revolut', 'Personale', 'Alimentari', 'Decò', 'expense', 81.10, 'Spesa famiglia maggio 2024'),
            $this->row('2024-05-16', 'Conto Intesa Personale', 'Casa 1', 'Acqua', 'GORI', 'expense', 41.00, 'Bolletta acqua maggio 2024'),
            $this->row('2024-05-19', 'Conto Intesa Personale', 'Casa 1', 'Internet', null, 'expense', 27.90, 'Canone internet maggio 2024'),
            $this->row('2024-05-25', 'Carta Revolut', 'Personale', 'Tempo libero', null, 'expense', 58.00, 'Weekend costiera maggio 2024'),

            $this->row('2024-06-05', 'Conto Intesa Personale', 'Personale', 'Stipendio', null, 'income', 1665.00, 'Stipendio giugno 2024'),
            $this->row('2024-06-09', 'Carta Revolut', 'Personale', 'Alimentari', 'Sole365', 'expense', 77.80, 'Spesa famiglia giugno 2024'),
            $this->row('2024-06-13', 'Carta Revolut', 'Personale', 'Salute', 'Farmacia Centrale', 'expense', 46.00, 'Parafarmacia giugno 2024'),
            $this->row('2024-06-18', 'Carta Revolut', 'Personale', 'Extra', 'Amazon', 'expense', 39.90, 'Ordine accessori giugno 2024'),
            $this->row('2024-06-24', 'Conto Intesa Personale', 'Personale', 'Altre entrate', null, 'income', 180.00, 'Rimborso fiscale giugno 2024'),

            $this->row('2024-07-05', 'Conto Intesa Personale', 'Personale', 'Stipendio', null, 'income', 1675.00, 'Stipendio luglio 2024'),
            $this->row('2024-07-09', 'Carta Revolut', 'Personale', 'Alimentari', 'Lidl', 'expense', 80.20, 'Spesa famiglia luglio 2024'),
            $this->row('2024-07-12', 'Carta Revolut', 'Personale', 'Auto', 'Q8', 'expense', 55.00, 'Rifornimento luglio 2024'),
            $this->row('2024-07-22', 'Conto Intesa Personale', 'Casa 1', 'Condominio', 'Condominio Via Roma', 'expense', 102.00, 'Quota condominio luglio 2024'),
            $this->row('2024-07-29', 'Conto Intesa Personale', 'Personale', 'Altre entrate', null, 'income', 450.00, 'Prestazione freelance luglio 2024'),

            $this->row('2024-08-05', 'Conto Intesa Personale', 'Personale', 'Stipendio', null, 'income', 1675.00, 'Stipendio agosto 2024'),
            $this->row('2024-08-09', 'Carta Revolut', 'Personale', 'Alimentari', 'Conad', 'expense', 73.60, 'Spesa famiglia agosto 2024'),
            $this->row('2024-08-16', 'Conto Intesa Personale', 'Casa 1', 'Luce', 'Enel Energia', 'expense', 88.90, 'Bolletta luce agosto 2024'),
            $this->row('2024-08-23', 'Carta Revolut', 'Personale', 'Tempo libero', null, 'expense', 96.00, 'Weekend mare agosto 2024'),
            $this->row('2024-08-28', 'Carta Revolut', 'Personale', 'Extra', 'Amazon', 'expense', 48.90, 'Acquisto ufficio agosto 2024'),

            $this->row('2024-09-05', 'Conto Intesa Personale', 'Personale', 'Stipendio', null, 'income', 1680.00, 'Stipendio settembre 2024'),
            $this->row('2024-09-09', 'Carta Revolut', 'Personale', 'Alimentari', 'Sole365', 'expense', 82.40, 'Spesa famiglia settembre 2024'),
            $this->row('2024-09-14', 'Conto Intesa Personale', 'Casa 1', 'Acqua', 'GORI', 'expense', 42.20, 'Bolletta acqua settembre 2024'),
            $this->row('2024-09-18', 'Carta Revolut', 'Personale', 'Salute', 'Farmacia Centrale', 'expense', 27.50, 'Farmacia settembre 2024'),
            $this->row('2024-09-26', 'Cassa Casa 1', 'Casa 1', 'Extra', null, 'expense', 42.00, 'Manutenzione casa settembre 2024'),

            $this->row('2024-10-05', 'Conto Intesa Personale', 'Personale', 'Stipendio', null, 'income', 1680.00, 'Stipendio ottobre 2024'),
            $this->row('2024-10-10', 'Carta Revolut', 'Personale', 'Alimentari', 'Decò', 'expense', 78.70, 'Spesa famiglia ottobre 2024'),
            $this->row('2024-10-14', 'Carta Revolut', 'Personale', 'Auto', 'Q8', 'expense', 53.00, 'Rifornimento ottobre 2024'),
            $this->row('2024-10-18', 'Conto Intesa Personale', 'Casa 1', 'Luce', 'Enel Energia', 'expense', 83.60, 'Bolletta luce ottobre 2024'),
            $this->row('2024-10-26', 'Carta Revolut', 'Personale', 'Extra', 'Amazon', 'expense', 54.30, 'Ordine autunno ottobre 2024'),

            $this->row('2024-11-05', 'Conto Intesa Personale', 'Personale', 'Stipendio', null, 'income', 1685.00, 'Stipendio novembre 2024'),
            $this->row('2024-11-09', 'Carta Revolut', 'Personale', 'Alimentari', 'Lidl', 'expense', 85.10, 'Spesa famiglia novembre 2024'),
            $this->row('2024-11-15', 'Conto Intesa Personale', 'Casa 1', 'Gas', null, 'expense', 104.00, 'Bolletta gas novembre 2024'),
            $this->row('2024-11-19', 'Conto Intesa Personale', 'Casa 1', 'Internet', null, 'expense', 28.90, 'Canone internet novembre 2024'),
            $this->row('2024-11-28', 'Conto Intesa Personale', 'Personale', 'Altre entrate', null, 'income', 380.00, 'Prestazione freelance novembre 2024'),

            $this->row('2024-12-05', 'Conto Intesa Personale', 'Personale', 'Stipendio', null, 'income', 1850.00, 'Stipendio dicembre 2024'),
            $this->row('2024-12-09', 'Carta Revolut', 'Personale', 'Alimentari', 'Sole365', 'expense', 94.60, 'Spesa feste dicembre 2024'),
            $this->row('2024-12-19', 'Conto Intesa Personale', 'Casa 1', 'Condominio', 'Condominio Via Roma', 'expense', 105.00, 'Quota condominio dicembre 2024'),
            $this->row('2024-12-22', 'Carta Revolut', 'Personale', 'Salute', 'Farmacia Centrale', 'expense', 62.00, 'Farmacia dicembre 2024'),
            $this->row('2024-12-27', 'Carta Revolut', 'Personale', 'Extra', 'Amazon', 'expense', 69.90, 'Regali dicembre 2024'),

            $this->row('2025-01-05', 'Conto Intesa Personale', 'Personale', 'Stipendio', null, 'income', 1880.00, 'Stipendio gennaio 2025'),
            $this->row('2025-01-09', 'Carta Revolut', 'Personale', 'Alimentari', 'Sole365', 'expense', 92.40, 'Spesa dispensa gennaio 2025'),
            $this->row('2025-01-13', 'Carta Revolut', 'Personale', 'Auto', 'Q8', 'expense', 57.00, 'Rifornimento gennaio 2025'),
            $this->row('2025-01-16', 'Conto Intesa Personale', 'Casa 1', 'Luce', 'Enel Energia', 'expense', 96.40, 'Bolletta luce gennaio 2025'),
            $this->row('2025-01-21', 'Carta Revolut', 'Personale', 'Cane', null, 'expense', 48.00, 'Veterinario gennaio 2025'),

            $this->row('2025-02-05', 'Conto Intesa Personale', 'Personale', 'Stipendio', null, 'income', 1880.00, 'Stipendio febbraio 2025'),
            $this->row('2025-02-10', 'Carta Revolut', 'Personale', 'Alimentari', 'Decò', 'expense', 86.20, 'Spesa settimana febbraio 2025'),
            $this->row('2025-02-14', 'Conto Intesa Personale', 'Casa 1', 'Acqua', 'GORI', 'expense', 45.70, 'Bolletta acqua febbraio 2025'),
            $this->row('2025-02-19', 'Conto Intesa Personale', 'Casa 1', 'Internet', null, 'expense', 29.90, 'Canone internet febbraio 2025'),
            $this->row('2025-02-24', 'Conto Intesa Personale', 'Casa 1', 'Gas', null, 'expense', 88.00, 'Bolletta gas febbraio 2025'),

            $this->row('2025-03-05', 'Conto Intesa Personale', 'Personale', 'Stipendio', null, 'income', 1890.00, 'Stipendio marzo 2025'),
            $this->row('2025-03-10', 'Carta Revolut', 'Personale', 'Alimentari', 'Lidl', 'expense', 93.10, 'Spesa famiglia marzo 2025'),
            $this->row('2025-03-15', 'Carta Revolut', 'Personale', 'Salute', 'Farmacia Centrale', 'expense', 52.40, 'Farmacia marzo 2025'),
            $this->row('2025-03-22', 'Carta Revolut', 'Personale', 'Extra', 'Amazon', 'expense', 59.90, 'Ordine accessori marzo 2025'),
            $this->row('2025-03-28', 'Conto Intesa Personale', 'Personale', 'Altre entrate', null, 'income', 420.00, 'Prestazione freelance marzo 2025'),

            $this->row('2025-04-05', 'Conto Intesa Personale', 'Personale', 'Stipendio', null, 'income', 1900.00, 'Stipendio aprile 2025'),
            $this->row('2025-04-09', 'Carta Revolut', 'Personale', 'Alimentari', 'Conad', 'expense', 89.60, 'Spesa famiglia aprile 2025'),
            $this->row('2025-04-14', 'Carta Revolut', 'Personale', 'Auto', 'Q8', 'expense', 61.00, 'Rifornimento aprile 2025'),
            $this->row('2025-04-22', 'Conto Intesa Personale', 'Casa 1', 'Condominio', 'Condominio Via Roma', 'expense', 112.00, 'Quota condominio aprile 2025'),
            $this->row('2025-04-27', 'Carta Revolut', 'Personale', 'Cane', null, 'expense', 54.00, 'Toelettatura aprile 2025'),

            $this->row('2025-05-05', 'Conto Intesa Personale', 'Personale', 'Stipendio', null, 'income', 1900.00, 'Stipendio maggio 2025'),
            $this->row('2025-05-10', 'Carta Revolut', 'Personale', 'Alimentari', 'Sole365', 'expense', 91.30, 'Spesa famiglia maggio 2025'),
            $this->row('2025-05-17', 'Carta Revolut', 'Personale', 'Tempo libero', null, 'expense', 88.00, 'Concerto maggio 2025'),
            $this->row('2025-05-21', 'Conto Intesa Personale', 'Casa 1', 'Internet', null, 'expense', 29.90, 'Canone internet maggio 2025'),
            $this->row('2025-05-26', 'Carta Revolut', 'Personale', 'Salute', 'Farmacia Centrale', 'expense', 41.20, 'Parafarmacia maggio 2025'),

            $this->row('2025-06-05', 'Conto Intesa Personale', 'Personale', 'Stipendio', null, 'income', 1910.00, 'Stipendio giugno 2025'),
            $this->row('2025-06-09', 'Carta Revolut', 'Personale', 'Alimentari', 'Decò', 'expense', 94.50, 'Spesa famiglia giugno 2025'),
            $this->row('2025-06-16', 'Conto Intesa Personale', 'Casa 1', 'Luce', 'Enel Energia', 'expense', 101.20, 'Bolletta luce giugno 2025'),
            $this->row('2025-06-24', 'Conto Intesa Personale', 'Personale', 'Altre entrate', null, 'income', 560.00, 'Prestazione freelance giugno 2025'),
            $this->row('2025-06-28', 'Cassa Casa 1', 'Casa 1', 'Extra', null, 'expense', 58.00, 'Piccoli lavori casa giugno 2025'),

            $this->row('2025-07-05', 'Conto Intesa Personale', 'Personale', 'Stipendio', null, 'income', 1910.00, 'Stipendio luglio 2025'),
            $this->row('2025-07-10', 'Carta Revolut', 'Personale', 'Alimentari', 'Lidl', 'expense', 96.80, 'Spesa famiglia luglio 2025'),
            $this->row('2025-07-14', 'Carta Revolut', 'Personale', 'Auto', 'Q8', 'expense', 63.00, 'Rifornimento luglio 2025'),
            $this->row('2025-07-19', 'Carta Revolut', 'Personale', 'Extra', 'Amazon', 'expense', 72.40, 'Ordine estate luglio 2025'),
            $this->row('2025-07-24', 'Carta Revolut', 'Personale', 'Cane', null, 'expense', 49.00, 'Scorte cane luglio 2025'),

            $this->row('2025-08-05', 'Conto Intesa Personale', 'Personale', 'Stipendio', null, 'income', 1920.00, 'Stipendio agosto 2025'),
            $this->row('2025-08-09', 'Carta Revolut', 'Personale', 'Alimentari', 'Conad', 'expense', 90.70, 'Spesa famiglia agosto 2025'),
            $this->row('2025-08-18', 'Carta Revolut', 'Personale', 'Extra', null, 'expense', 214.00, 'Prenotazione weekend agosto 2025'),
            $this->row('2025-08-22', 'Conto Intesa Personale', 'Casa 1', 'Condominio', 'Condominio Via Roma', 'expense', 118.00, 'Quota condominio agosto 2025'),
            $this->row('2025-08-27', 'Carta Revolut', 'Personale', 'Tempo libero', null, 'expense', 67.00, 'Cena fuori agosto 2025'),

            $this->row('2025-09-05', 'Conto Intesa Personale', 'Personale', 'Stipendio', null, 'income', 1930.00, 'Stipendio settembre 2025'),
            $this->row('2025-09-10', 'Carta Revolut', 'Personale', 'Alimentari', 'Sole365', 'expense', 98.40, 'Spesa famiglia settembre 2025'),
            $this->row('2025-09-14', 'Conto Intesa Personale', 'Casa 1', 'Acqua', 'GORI', 'expense', 48.30, 'Bolletta acqua settembre 2025'),
            $this->row('2025-09-18', 'Carta Revolut', 'Personale', 'Salute', 'Farmacia Centrale', 'expense', 38.90, 'Farmacia settembre 2025'),
            $this->row('2025-09-25', 'Carta Revolut', 'Personale', 'Cane', null, 'expense', 52.00, 'Visita cane settembre 2025'),

            $this->row('2025-10-05', 'Conto Intesa Personale', 'Personale', 'Stipendio', null, 'income', 1930.00, 'Stipendio ottobre 2025'),
            $this->row('2025-10-10', 'Carta Revolut', 'Personale', 'Alimentari', 'Decò', 'expense', 95.70, 'Spesa famiglia ottobre 2025'),
            $this->row('2025-10-14', 'Carta Revolut', 'Personale', 'Auto', 'Q8', 'expense', 66.00, 'Rifornimento ottobre 2025'),
            $this->row('2025-10-18', 'Conto Intesa Personale', 'Casa 1', 'Luce', 'Enel Energia', 'expense', 98.80, 'Bolletta luce ottobre 2025'),
            $this->row('2025-10-26', 'Carta Revolut', 'Personale', 'Extra', 'Amazon', 'expense', 64.50, 'Ordine autunno ottobre 2025'),

            $this->row('2025-11-05', 'Conto Intesa Personale', 'Personale', 'Stipendio', null, 'income', 1940.00, 'Stipendio novembre 2025'),
            $this->row('2025-11-10', 'Carta Revolut', 'Personale', 'Alimentari', 'Lidl', 'expense', 102.10, 'Spesa famiglia novembre 2025'),
            $this->row('2025-11-15', 'Conto Intesa Personale', 'Casa 1', 'Gas', null, 'expense', 109.00, 'Bolletta gas novembre 2025'),
            $this->row('2025-11-24', 'Conto Intesa Personale', 'Personale', 'Altre entrate', null, 'income', 610.00, 'Prestazione freelance novembre 2025'),
            $this->row('2025-11-28', 'Conto Intesa Personale', 'Casa 1', 'Internet', null, 'expense', 30.50, 'Canone internet novembre 2025'),

            $this->row('2025-12-05', 'Conto Intesa Personale', 'Personale', 'Stipendio', null, 'income', 2450.00, 'Stipendio e premio dicembre 2025'),
            $this->row('2025-12-10', 'Carta Revolut', 'Personale', 'Alimentari', 'Conad', 'expense', 109.30, 'Spesa feste dicembre 2025'),
            $this->row('2025-12-19', 'Conto Intesa Personale', 'Casa 1', 'Condominio', 'Condominio Via Roma', 'expense', 120.00, 'Quota condominio dicembre 2025'),
            $this->row('2025-12-22', 'Carta Revolut', 'Personale', 'Cane', null, 'expense', 76.00, 'Cure cane dicembre 2025'),
            $this->row('2025-12-27', 'Carta Revolut', 'Personale', 'Extra', 'Amazon', 'expense', 89.90, 'Regali dicembre 2025'),
        ];
    }

    /**
     * @return array{
     *     date: string,
     *     account: string,
     *     scope: string,
     *     category: string,
     *     merchant: string|null,
     *     direction: string,
     *     amount: float,
     *     description: string
     * }
     */
    private function row(
        string $date,
        string $account,
        string $scope,
        string $category,
        ?string $merchant,
        string $direction,
        float $amount,
        string $description
    ): array {
        return [
            'date' => $date,
            'account' => $account,
            'scope' => $scope,
            'category' => $category,
            'merchant' => $merchant,
            'direction' => $direction,
            'amount' => $amount,
            'description' => $description,
        ];
    }
}
