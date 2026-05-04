<?php

namespace App\Models;

use App\Enums\ImportFormatStatusEnum;
use App\Enums\ImportFormatTypeEnum;
use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImportFormat extends Model
{
    use HasPublicUuid;

    protected $fillable = [
        'bank_id',
        'code',
        'name',
        'version',
        'type',
        'status',
        'is_generic',
        'notes',
        'settings',
    ];

    protected $casts = [
        'type' => ImportFormatTypeEnum::class,
        'status' => ImportFormatStatusEnum::class,
        'is_generic' => 'boolean',
        'settings' => 'array',
    ];

    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }

    public function imports(): HasMany
    {
        return $this->hasMany(Import::class);
    }

    public static function ensureGenericCsvV1(): self
    {
        /** @var self $format */
        $format = self::query()->firstOrCreate(
            ['code' => 'generic_csv_v1'],
            [
                'name' => 'Template XLSX guidato v1',
                'version' => 'v1',
                'type' => ImportFormatTypeEnum::GENERIC_CSV,
                'status' => ImportFormatStatusEnum::ACTIVE,
                'is_generic' => true,
                'notes' => 'Formato guidato basato sul template XLSX ufficiale generato dall’app.',
            ]
        );

        if (
            $format->type !== ImportFormatTypeEnum::GENERIC_CSV
            || $format->status !== ImportFormatStatusEnum::ACTIVE
            || ! $format->is_generic
            || $format->name !== 'Template XLSX guidato v1'
            || $format->notes !== 'Formato guidato basato sul template XLSX ufficiale generato dall’app.'
        ) {
            $format->forceFill([
                'type' => ImportFormatTypeEnum::GENERIC_CSV,
                'status' => ImportFormatStatusEnum::ACTIVE,
                'is_generic' => true,
                'name' => 'Template XLSX guidato v1',
                'notes' => 'Formato guidato basato sul template XLSX ufficiale generato dall’app.',
            ])->save();
        }

        return $format->refresh();
    }

    public static function ensureMediobancaXlsx(): self
    {
        $bank = Bank::query()->firstOrCreate(
            ['slug' => 'mediobanca'],
            [
                'name' => 'Mediobanca',
                'display_name' => 'Mediobanca',
                'country_code' => 'IT',
                'is_active' => true,
            ]
        );

        /** @var self $format */
        $format = self::query()->firstOrCreate(
            ['code' => 'mediobanca_xlsx_v1'],
            [
                'bank_id' => $bank->id,
                'name' => 'Mediobanca XLSX',
                'version' => 'v1',
                'type' => ImportFormatTypeEnum::BANK_CSV,
                'status' => ImportFormatStatusEnum::ACTIVE,
                'is_generic' => false,
                'notes' => 'Profilo XLSX Mediobanca con intestazioni movimenti alla riga 15.',
                'settings' => self::mediobancaXlsxSettings(),
            ]
        );

        $expectedSettings = self::mediobancaXlsxSettings();

        if (
            (int) $format->bank_id !== (int) $bank->id
            || $format->type !== ImportFormatTypeEnum::BANK_CSV
            || $format->status !== ImportFormatStatusEnum::ACTIVE
            || $format->is_generic
            || $format->name !== 'Mediobanca XLSX'
            || $format->settings !== $expectedSettings
        ) {
            $format->forceFill([
                'bank_id' => $bank->id,
                'name' => 'Mediobanca XLSX',
                'version' => 'v1',
                'type' => ImportFormatTypeEnum::BANK_CSV,
                'status' => ImportFormatStatusEnum::ACTIVE,
                'is_generic' => false,
                'notes' => 'Profilo XLSX Mediobanca con intestazioni movimenti alla riga 15.',
                'settings' => $expectedSettings,
            ])->save();
        }

        return $format->refresh();
    }

    public static function ensureRevolutCsv(): self
    {
        $bank = Bank::query()->firstOrCreate(
            ['slug' => 'revolut'],
            [
                'name' => 'Revolut',
                'display_name' => 'Revolut',
                'country_code' => 'LT',
                'is_active' => true,
            ]
        );

        /** @var self $format */
        $format = self::query()->firstOrCreate(
            ['code' => 'revolut_csv_v1'],
            [
                'bank_id' => $bank->id,
                'name' => 'Revolut CSV',
                'version' => 'v1',
                'type' => ImportFormatTypeEnum::BANK_CSV,
                'status' => ImportFormatStatusEnum::ACTIVE,
                'is_generic' => false,
                'notes' => 'Profilo CSV Revolut con importo signed e fallback sulle fee.',
                'settings' => self::revolutCsvSettings(),
            ]
        );

        $expectedSettings = self::revolutCsvSettings();

        if (
            (int) $format->bank_id !== (int) $bank->id
            || $format->type !== ImportFormatTypeEnum::BANK_CSV
            || $format->status !== ImportFormatStatusEnum::ACTIVE
            || $format->is_generic
            || $format->name !== 'Revolut CSV'
            || $format->settings !== $expectedSettings
        ) {
            $format->forceFill([
                'bank_id' => $bank->id,
                'name' => 'Revolut CSV',
                'version' => 'v1',
                'type' => ImportFormatTypeEnum::BANK_CSV,
                'status' => ImportFormatStatusEnum::ACTIVE,
                'is_generic' => false,
                'notes' => 'Profilo CSV Revolut con importo signed e fallback sulle fee.',
                'settings' => $expectedSettings,
            ])->save();
        }

        return $format->refresh();
    }

    public static function ensureHypeXlsx(): self
    {
        $bank = Bank::query()->firstOrCreate(
            ['slug' => 'hype'],
            [
                'name' => 'Hype',
                'display_name' => 'Hype',
                'country_code' => 'IT',
                'is_active' => true,
            ]
        );

        /** @var self $format */
        $format = self::query()->firstOrCreate(
            ['code' => 'hype_xlsx_v1'],
            [
                'bank_id' => $bank->id,
                'name' => 'Hype XLSX',
                'version' => 'v1',
                'type' => ImportFormatTypeEnum::BANK_CSV,
                'status' => ImportFormatStatusEnum::ACTIVE,
                'is_generic' => false,
                'notes' => 'Profilo XLSX Hype con movimenti nel foglio Movimenti.',
                'settings' => self::hypeXlsxSettings(),
            ]
        );

        $expectedSettings = self::hypeXlsxSettings();

        if (
            (int) $format->bank_id !== (int) $bank->id
            || $format->type !== ImportFormatTypeEnum::BANK_CSV
            || $format->status !== ImportFormatStatusEnum::ACTIVE
            || $format->is_generic
            || $format->name !== 'Hype XLSX'
            || $format->settings !== $expectedSettings
        ) {
            $format->forceFill([
                'bank_id' => $bank->id,
                'name' => 'Hype XLSX',
                'version' => 'v1',
                'type' => ImportFormatTypeEnum::BANK_CSV,
                'status' => ImportFormatStatusEnum::ACTIVE,
                'is_generic' => false,
                'notes' => 'Profilo XLSX Hype con movimenti nel foglio Movimenti.',
                'settings' => $expectedSettings,
            ])->save();
        }

        return $format->refresh();
    }

    public static function ensureN26Csv(): self
    {
        $bank = Bank::query()->firstOrCreate(
            ['slug' => 'n26'],
            [
                'name' => 'N26',
                'display_name' => 'N26',
                'country_code' => 'DE',
                'is_active' => true,
            ]
        );

        /** @var self $format */
        $format = self::query()->firstOrCreate(
            ['code' => 'n26_csv_v1'],
            [
                'bank_id' => $bank->id,
                'name' => 'N26 CSV',
                'version' => 'v1',
                'type' => ImportFormatTypeEnum::BANK_CSV,
                'status' => ImportFormatStatusEnum::ACTIVE,
                'is_generic' => false,
                'notes' => 'Profilo CSV N26 con importo EUR signed e metadati valuta originale.',
                'settings' => self::n26CsvSettings(),
            ]
        );

        $expectedSettings = self::n26CsvSettings();

        if (
            (int) $format->bank_id !== (int) $bank->id
            || $format->type !== ImportFormatTypeEnum::BANK_CSV
            || $format->status !== ImportFormatStatusEnum::ACTIVE
            || $format->is_generic
            || $format->name !== 'N26 CSV'
            || $format->settings !== $expectedSettings
        ) {
            $format->forceFill([
                'bank_id' => $bank->id,
                'name' => 'N26 CSV',
                'version' => 'v1',
                'type' => ImportFormatTypeEnum::BANK_CSV,
                'status' => ImportFormatStatusEnum::ACTIVE,
                'is_generic' => false,
                'notes' => 'Profilo CSV N26 con importo EUR signed e metadati valuta originale.',
                'settings' => $expectedSettings,
            ])->save();
        }

        return $format->refresh();
    }

    public static function ensurePayPalCsv(): self
    {
        $bank = Bank::query()->firstOrCreate(
            ['slug' => 'paypal'],
            [
                'name' => 'PayPal',
                'display_name' => 'PayPal',
                'country_code' => 'US',
                'is_active' => true,
            ]
        );

        /** @var self $format */
        $format = self::query()->firstOrCreate(
            ['code' => 'paypal_csv_v1'],
            [
                'bank_id' => $bank->id,
                'name' => 'PayPal CSV',
                'version' => 'v1',
                'type' => ImportFormatTypeEnum::BANK_CSV,
                'status' => ImportFormatStatusEnum::ACTIVE,
                'is_generic' => false,
                'notes' => 'Profilo CSV PayPal prudente: funding e trasferimenti restano in review.',
                'settings' => self::paypalCsvSettings(),
            ]
        );

        $expectedSettings = self::paypalCsvSettings();

        if (
            (int) $format->bank_id !== (int) $bank->id
            || $format->type !== ImportFormatTypeEnum::BANK_CSV
            || $format->status !== ImportFormatStatusEnum::ACTIVE
            || $format->is_generic
            || $format->name !== 'PayPal CSV'
            || $format->settings !== $expectedSettings
        ) {
            $format->forceFill([
                'bank_id' => $bank->id,
                'name' => 'PayPal CSV',
                'version' => 'v1',
                'type' => ImportFormatTypeEnum::BANK_CSV,
                'status' => ImportFormatStatusEnum::ACTIVE,
                'is_generic' => false,
                'notes' => 'Profilo CSV PayPal prudente: funding e trasferimenti restano in review.',
                'settings' => $expectedSettings,
            ])->save();
        }

        return $format->refresh();
    }

    public static function ensureSatispayXlsx(): self
    {
        $bank = Bank::query()->firstOrCreate(
            ['slug' => 'satispay'],
            [
                'name' => 'Satispay',
                'display_name' => 'Satispay',
                'country_code' => 'IT',
                'is_active' => true,
            ]
        );

        /** @var self $format */
        $format = self::query()->firstOrCreate(
            ['code' => 'satispay_xlsx_v1'],
            [
                'bank_id' => $bank->id,
                'name' => 'Satispay XLSX',
                'version' => 'v1',
                'type' => ImportFormatTypeEnum::BANK_CSV,
                'status' => ImportFormatStatusEnum::ACTIVE,
                'is_generic' => false,
                'notes' => 'Profilo XLSX Satispay con importo signed e buoni pasto solo come metadati.',
                'settings' => self::satispayXlsxSettings(),
            ]
        );

        $expectedSettings = self::satispayXlsxSettings();

        if (
            (int) $format->bank_id !== (int) $bank->id
            || $format->type !== ImportFormatTypeEnum::BANK_CSV
            || $format->status !== ImportFormatStatusEnum::ACTIVE
            || $format->is_generic
            || $format->name !== 'Satispay XLSX'
            || $format->settings !== $expectedSettings
        ) {
            $format->forceFill([
                'bank_id' => $bank->id,
                'name' => 'Satispay XLSX',
                'version' => 'v1',
                'type' => ImportFormatTypeEnum::BANK_CSV,
                'status' => ImportFormatStatusEnum::ACTIVE,
                'is_generic' => false,
                'notes' => 'Profilo XLSX Satispay con importo signed e buoni pasto solo come metadati.',
                'settings' => $expectedSettings,
            ])->save();
        }

        return $format->refresh();
    }

    /**
     * @return array<string, mixed>
     */
    protected static function mediobancaXlsxSettings(): array
    {
        return [
            'source_types' => ['xlsx'],
            'header_row' => 15,
            'skip_rows' => [],
            'columns' => [
                'date' => 'Data contabile',
                'value_date' => 'Data valuta',
                'description' => 'Tipologia',
                'credit' => 'Entrate',
                'debit' => 'Uscite',
                'currency' => 'Divisa',
            ],
            'amount' => [
                'mode' => 'separate_debit_credit',
                'debit_column' => 'Uscite',
                'credit_column' => 'Entrate',
                'debit_sign' => 'negative',
            ],
            'normalization' => [
                'date_format' => 'd/m/Y',
                'decimal_separator' => ',',
                'thousands_separator' => '.',
                'description_cleanup' => [
                    'collapse_spaces' => true,
                    'uppercase' => false,
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected static function revolutCsvSettings(): array
    {
        return [
            'source_types' => ['csv'],
            'header_row' => 1,
            'skip_rows' => [],
            'columns' => [
                'date' => 'Data di completamento',
                'value_date' => 'Data di inizio',
                'amount' => 'Importo',
                'description' => 'Descrizione',
                'balance' => 'Saldo',
                'currency' => 'Valuta',
            ],
            'amount' => [
                'mode' => 'signed_amount_with_fee_fallback',
                'fee_column' => 'Costo',
            ],
            'state' => [
                'column' => 'State',
                'completed_values' => ['COMPLETATO'],
            ],
            'metadata_columns' => [
                'type' => 'Tipo',
                'product' => 'Prodotto',
                'state' => 'State',
                'fee' => 'Costo',
            ],
            'normalization' => [
                'date_format' => 'Y-m-d H:i:s',
                'decimal_separator' => '.',
                'thousands_separator' => ',',
                'description_cleanup' => [
                    'collapse_spaces' => true,
                    'uppercase' => false,
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected static function hypeXlsxSettings(): array
    {
        return [
            'source_types' => ['xlsx'],
            'sheet_name' => 'Movimenti',
            'header_row' => 1,
            'skip_rows' => [],
            'columns' => [
                'date' => 'Data contabile',
                'value_date' => 'Data operazione',
                'amount' => 'Importo ( € )',
                'description' => 'Descrizione',
                'merchant' => 'Nome',
            ],
            'amount' => [
                'mode' => 'signed_amount',
            ],
            'metadata_columns' => [
                'iban' => 'Iban',
                'transaction_type' => 'Tipologia',
                'name' => 'Nome',
            ],
            'normalization' => [
                'date_format' => 'd/m/Y',
                'decimal_separator' => ',',
                'thousands_separator' => '.',
                'description_cleanup' => [
                    'collapse_spaces' => true,
                    'uppercase' => false,
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected static function n26CsvSettings(): array
    {
        return [
            'source_types' => ['csv'],
            'header_row' => 1,
            'skip_rows' => [],
            'columns' => [
                'date' => 'Booking Date',
                'value_date' => 'Value Date',
                'amount' => 'Amount (EUR)',
                'description' => 'Partner Name',
                'merchant' => 'Partner Name',
                'reference' => 'Payment Reference',
                'currency' => 'Original Currency',
            ],
            'amount' => [
                'mode' => 'signed_amount',
            ],
            'metadata_columns' => [
                'partner_iban' => 'Partner Iban',
                'transaction_type' => 'Type',
                'payment_reference' => 'Payment Reference',
                'account_name' => 'Account Name',
                'original_amount' => 'Original Amount',
                'original_currency' => 'Original Currency',
                'exchange_rate' => 'Exchange Rate',
            ],
            'normalization' => [
                'date_format' => 'Y-m-d',
                'decimal_separator' => '.',
                'thousands_separator' => ',',
                'description_cleanup' => [
                    'collapse_spaces' => true,
                    'uppercase' => false,
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected static function paypalCsvSettings(): array
    {
        return [
            'source_types' => ['csv'],
            'header_row' => 1,
            'skip_rows' => [],
            'columns' => [
                'date' => 'Data',
                'amount' => 'Netto',
                'description' => 'Descrizione',
                'merchant' => 'Nome',
                'external_reference' => 'Codice transazione',
                'balance' => 'Saldo',
                'currency' => 'Valuta',
            ],
            'date_time' => [
                'time_column' => 'Ora',
                'format' => 'j/n/Y H:i:s',
            ],
            'amount' => [
                'mode' => 'signed_amount',
            ],
            'review_types' => [
                'column' => 'Descrizione',
                'values' => [
                    'Versamento generico con carta',
                    'Trasferimento avviato dall\'utente',
                ],
            ],
            'metadata_columns' => [
                'gross' => 'Lordo',
                'fee' => 'Tariffa',
                'balance' => 'Saldo',
                'transaction_code' => 'Codice transazione',
                'sender_email' => 'Indirizzo email mittente',
                'bank_name' => 'Nome banca',
                'bank_account' => 'Conto bancario',
                'shipping_amount' => 'Importo per spedizione e imballaggio',
                'vat' => 'IVA',
                'pro_forma_invoice_number' => 'N. fattura pro-forma',
                'reference_transaction_code' => 'Codice transazione di riferimento',
                'timezone' => 'Fuso orario',
            ],
            'normalization' => [
                'date_format' => 'j/n/Y',
                'decimal_separator' => ',',
                'thousands_separator' => '.',
                'description_cleanup' => [
                    'collapse_spaces' => true,
                    'uppercase' => false,
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected static function satispayXlsxSettings(): array
    {
        return [
            'source_types' => ['xlsx'],
            'sheet_name' => 'Transactions',
            'header_row' => 1,
            'skip_rows' => [],
            'columns' => [
                'date' => 'Data',
                'amount' => 'Importo',
                'description' => 'Nome',
                'merchant' => 'Nome',
                'reference' => 'Descrizione',
                'external_reference' => 'ID (Comunicalo all\'Assistenza Clienti in caso di problemi)',
            ],
            'amount' => [
                'mode' => 'signed_amount',
            ],
            'state' => [
                'column' => 'Stato',
                'completed_values' => ['✅ Approvato'],
            ],
            'metadata_columns' => [
                'type' => 'Tipo',
                'state' => 'Stato',
                'description' => 'Descrizione',
                'availability' => 'Disponibilità',
                'meal_vouchers' => 'Buoni Pasto',
                'availability_after_transaction' => 'Disponibilità dopo la transazione',
                'transaction_id' => 'ID (Comunicalo all\'Assistenza Clienti in caso di problemi)',
            ],
            'normalization' => [
                'date_format' => 'd/m/Y',
                'decimal_separator' => '.',
                'thousands_separator' => ',',
                'description_cleanup' => [
                    'collapse_spaces' => true,
                    'uppercase' => false,
                ],
            ],
        ];
    }
}
