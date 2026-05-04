<?php

namespace App\Http\Requests\Imports;

use App\Enums\ImportFormatStatusEnum;
use App\Enums\ImportFormatTypeEnum;
use App\Models\Account;
use App\Models\ImportFormat;
use App\Supports\Imports\ImportFormatProfile;
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
            'import_format_uuid' => ['required', 'uuid'],
            'import_format_id' => ['nullable', 'integer'],
            'account_uuid' => ['nullable', 'uuid'],
            'account_id' => ['nullable', 'integer'],
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx', 'max:10240'],
        ];
    }

    public function messages(): array
    {
        return [
            'import_format_uuid.required' => __('imports.validation.format_required'),
            'file.required' => __('imports.validation.file_required'),
            'file.file' => __('imports.validation.file_invalid'),
            'file.mimes' => __('imports.validation.file_supported'),
            'file.max' => __('imports.validation.file_too_large'),
            'account_uuid.uuid' => __('imports.validation.account_unavailable'),
        ];
    }

    protected function prepareForValidation(): void
    {
        ImportFormat::ensureGenericCsvV1();
        if ($this->user()?->hasRole('admin')) {
            ImportFormat::ensureHypeXlsx();
            ImportFormat::ensureMediobancaXlsx();
            ImportFormat::ensureN26Csv();
            ImportFormat::ensurePayPalCsv();
            ImportFormat::ensureRevolutCsv();
            ImportFormat::ensureSatispayXlsx();
        }

        $activeFormats = ImportFormat::query()
            ->where('status', ImportFormatStatusEnum::ACTIVE)
            ->whereIn('type', $this->visibleFormatTypes())
            ->orderByDesc('is_generic')
            ->orderBy('name')
            ->get(['uuid']);
        $defaultFormatUuid = $activeFormats->count() === 1
            ? (string) $activeFormats->first()->uuid
            : null;
        $importFormatUuid = $this->filled('import_format_uuid')
            ? (string) $this->input('import_format_uuid')
            : $defaultFormatUuid;

        $this->merge([
            'import_format_uuid' => $importFormatUuid,
            'import_format_id' => $importFormatUuid !== null
                ? ImportFormat::query()->where('uuid', $importFormatUuid)->value('id')
                : null,
            'account_id' => $this->filled('account_uuid')
                ? Account::query()
                    ->where('user_id', $this->user()?->id)
                    ->where('is_active', true)
                    ->where('uuid', (string) $this->input('account_uuid'))
                    ->value('id')
                : null,
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $format = ImportFormat::query()->find($this->integer('import_format_id'));

            if (! $format instanceof ImportFormat) {
                $validator->errors()->add('import_format_uuid', __('imports.validation.format_unavailable'));

                return;
            }

            if ($format->status !== ImportFormatStatusEnum::ACTIVE) {
                $validator->errors()->add('import_format_uuid', __('imports.validation.format_inactive'));
            }

            if (! in_array($format->type, $this->visibleFormatTypes(), true)) {
                $validator->errors()->add('import_format_uuid', __('imports.validation.format_not_supported'));

                return;
            }

            if ($format->type === ImportFormatTypeEnum::BANK_PDF) {
                $validator->errors()->add('import_format_uuid', __('imports.validation.format_not_supported'));

                return;
            }

            $extension = mb_strtolower((string) $this->file('file')?->getClientOriginalExtension());
            $sourceType = $extension === 'xlsx' ? 'xlsx' : 'csv';
            $profile = ImportFormatProfile::fromSettings($format->settings);

            if ($format->type === ImportFormatTypeEnum::BANK_CSV && ! $profile instanceof ImportFormatProfile) {
                $validator->errors()->add('import_format_uuid', __('imports.validation.format_profile_invalid'));

                return;
            }

            if ($profile instanceof ImportFormatProfile && ! $profile->supportsSourceType($sourceType)) {
                $validator->errors()->add('file', __('imports.validation.format_source_unsupported'));
            }

            if ($format->type === ImportFormatTypeEnum::BANK_CSV && ! $this->integer('account_id')) {
                $validator->errors()->add('account_uuid', __('imports.validation.account_required'));
            }
        });
    }

    /**
     * @return array<int, ImportFormatTypeEnum>
     */
    protected function visibleFormatTypes(): array
    {
        if ($this->user()?->hasRole('admin')) {
            return [
                ImportFormatTypeEnum::GENERIC_CSV,
                ImportFormatTypeEnum::BANK_CSV,
            ];
        }

        return [
            ImportFormatTypeEnum::GENERIC_CSV,
        ];
    }
}
