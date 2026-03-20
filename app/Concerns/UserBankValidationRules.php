<?php

namespace App\Concerns;

use App\Models\Bank;
use App\Models\UserBank;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

trait UserBankValidationRules
{
    /**
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function storeUserBankRules(): array
    {
        return [
            'mode' => ['required', Rule::in(['catalog', 'custom'])],
            'bank_uuid' => ['nullable', 'uuid'],
            'bank_id' => ['nullable', 'integer', Rule::exists(Bank::class, 'id')],
            'name' => ['nullable', 'string', 'max:150'],
            'slug' => ['nullable', 'string', 'max:150', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'is_active' => ['required', 'boolean'],
            'create_base_account' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function updateUserBankRules(int $userId, UserBank $userBank): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'slug' => [
                'required',
                'string',
                'max:150',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique(UserBank::class)
                    ->where('user_id', $userId)
                    ->ignore($userBank->id),
            ],
            'is_active' => ['required', 'boolean'],
        ];
    }

    protected function prepareStoreUserBankValidation(): void
    {
        $name = trim((string) $this->input('name', ''));
        $slugSource = (string) ($this->input('slug') ?: $name);
        $bankUuid = $this->filled('bank_uuid') ? (string) $this->input('bank_uuid') : null;
        $bankId = $bankUuid === null
            ? null
            : Bank::query()->where('uuid', $bankUuid)->value('id');

        $this->merge([
            'mode' => (string) $this->input('mode'),
            'bank_uuid' => $bankUuid,
            'bank_id' => $bankId !== null ? (int) $bankId : null,
            'name' => $name !== '' ? $name : null,
            'slug' => $slugSource !== '' ? Str::slug($slugSource) : null,
            'is_active' => $this->boolean('is_active', true),
            'create_base_account' => $this->boolean('create_base_account', true),
        ]);
    }

    protected function prepareUpdateUserBankValidation(): void
    {
        $name = trim((string) $this->input('name', ''));
        $slugSource = (string) ($this->input('slug') ?: $name);

        $this->merge([
            'name' => $name,
            'slug' => Str::slug($slugSource),
            'is_active' => $this->boolean('is_active', true),
        ]);
    }

    protected function validateStoreUserBankRules(Validator $validator, int $userId): void
    {
        $validator->after(function (Validator $validator) use ($userId): void {
            $mode = (string) $this->input('mode');

            if ($mode === 'custom') {
                if (! $this->filled('name')) {
                    $validator->errors()->add('name', 'Il nome della banca personalizzata è obbligatorio.');
                }

                if (! $this->filled('slug')) {
                    $validator->errors()->add('slug', 'Lo slug della banca personalizzata è obbligatorio.');
                }

                if ($this->filled('slug')) {
                    $exists = UserBank::query()
                        ->where('user_id', $userId)
                        ->where('slug', (string) $this->input('slug'))
                        ->exists();

                    if ($exists) {
                        $validator->errors()->add('slug', 'Esiste già una banca disponibile con questo slug.');
                    }
                }

                return;
            }

            if (! $this->filled('bank_uuid')) {
                $validator->errors()->add('bank_uuid', 'Seleziona una banca dal catalogo.');
            }

            if ($this->filled('bank_uuid') && ! $this->filled('bank_id')) {
                $validator->errors()->add('bank_uuid', 'La banca selezionata non è valida.');
            }
        });
    }

    protected function validateUpdateUserBankRules(Validator $validator, UserBank $userBank): void
    {
        $validator->after(function (Validator $validator) use ($userBank): void {
            if (! $userBank->is_custom) {
                $validator->errors()->add('name', 'Solo le banche personalizzate possono essere rinominate.');
            }
        });
    }
}
