<?php

namespace App\Http\Requests\Imports;

use App\Enums\ImportFormatStatusEnum;
use App\Enums\ImportFormatTypeEnum;
use App\Models\Account;
use App\Models\ImportFormat;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'account_uuid' => ['required', 'uuid'],
            'account_id' => ['nullable', 'integer'],
            'import_format_uuid' => ['required', 'uuid'],
            'import_format_id' => ['nullable', 'integer'],
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
        ];
    }

    public function messages(): array
    {
        return [
            'account_uuid.required' => 'Seleziona un conto.',
            'import_format_uuid.required' => 'Seleziona un formato import.',
            'file.required' => 'Carica un file CSV.',
            'file.file' => 'Il file selezionato non è valido.',
            'file.mimes' => 'Carica un file CSV valido.',
            'file.max' => 'Il file supera la dimensione massima consentita.',
        ];
    }

    protected function prepareForValidation(): void
    {
        ImportFormat::ensureGenericCsvV1();

        $accountUuid = $this->filled('account_uuid') ? (string) $this->input('account_uuid') : null;
        $activeGenericFormats = ImportFormat::query()
            ->where('status', ImportFormatStatusEnum::ACTIVE)
            ->where('type', ImportFormatTypeEnum::GENERIC_CSV)
            ->orderByDesc('is_generic')
            ->orderBy('name')
            ->get(['uuid']);
        $defaultFormatUuid = $activeGenericFormats->count() === 1
            ? (string) $activeGenericFormats->first()->uuid
            : null;
        $importFormatUuid = $this->filled('import_format_uuid')
            ? (string) $this->input('import_format_uuid')
            : $defaultFormatUuid;

        $this->merge([
            'account_uuid' => $accountUuid,
            'account_id' => $accountUuid !== null
                ? Account::query()->where('uuid', $accountUuid)->value('id')
                : null,
            'import_format_uuid' => $importFormatUuid,
            'import_format_id' => $importFormatUuid !== null
                ? ImportFormat::query()->where('uuid', $importFormatUuid)->value('id')
                : null,
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $user = $this->user();
            $account = Account::query()->find($this->integer('account_id'));

            if (! $account instanceof Account || $account->user_id !== $user->id) {
                $validator->errors()->add('account_uuid', 'Il conto selezionato non è disponibile.');
            }

            $format = ImportFormat::query()->find($this->integer('import_format_id'));

            if (! $format instanceof ImportFormat) {
                $validator->errors()->add('import_format_uuid', 'Il formato selezionato non è disponibile.');

                return;
            }

            if ($format->status !== ImportFormatStatusEnum::ACTIVE) {
                $validator->errors()->add('import_format_uuid', 'Il formato selezionato non è attivo.');
            }

            if ($format->type !== ImportFormatTypeEnum::GENERIC_CSV) {
                $validator->errors()->add('import_format_uuid', 'Per ora puoi usare solo formati CSV generici.');
            }
        });
    }
}
