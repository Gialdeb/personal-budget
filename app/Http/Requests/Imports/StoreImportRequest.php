<?php

namespace App\Http\Requests\Imports;

use App\Enums\ImportFormatStatusEnum;
use App\Enums\ImportFormatTypeEnum;
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
            'import_format_uuid' => ['required', 'uuid'],
            'import_format_id' => ['nullable', 'integer'],
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
        ];
    }

    protected function prepareForValidation(): void
    {
        ImportFormat::ensureGenericCsvV1();

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

            $format = ImportFormat::query()->find($this->integer('import_format_id'));

            if (! $format instanceof ImportFormat) {
                $validator->errors()->add('import_format_uuid', __('imports.validation.format_unavailable'));

                return;
            }

            if ($format->status !== ImportFormatStatusEnum::ACTIVE) {
                $validator->errors()->add('import_format_uuid', __('imports.validation.format_inactive'));
            }

            if ($format->type !== ImportFormatTypeEnum::GENERIC_CSV) {
                $validator->errors()->add('import_format_uuid', __('imports.validation.format_not_supported'));
            }
        });
    }
}
