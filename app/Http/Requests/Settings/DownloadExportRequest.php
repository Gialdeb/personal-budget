<?php

namespace App\Http\Requests\Settings;

use App\Enums\ExportDatasetEnum;
use App\Enums\ExportFormatEnum;
use App\Enums\ExportPeriodPresetEnum;
use App\Services\Exports\ExportPeriod;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class DownloadExportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'dataset' => ['required', Rule::in(ExportDatasetEnum::values())],
            'format' => ['required', Rule::in(ExportFormatEnum::values())],
            'period_preset' => ['required', Rule::in(ExportPeriodPresetEnum::values())],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $dataset = $this->validatedDataset();
                $format = $this->validatedFormat();
                $preset = $this->validatedPeriodPreset();

                if ($dataset === null || $format === null || $preset === null) {
                    return;
                }

                if (! in_array($format, $dataset->availableFormats(), true)) {
                    $validator->errors()->add('format', __('export.validation.invalid_format_for_dataset'));
                }

                if ($dataset->supportsPeriod() && $preset === ExportPeriodPresetEnum::CUSTOM_RANGE) {
                    if ($this->string('start_date')->value() === '' || $this->string('end_date')->value() === '') {
                        $validator->errors()->add('start_date', __('export.validation.custom_range_required'));

                        return;
                    }

                    $startDate = $this->date('start_date');
                    $endDate = $this->date('end_date');

                    if ($startDate !== null && $endDate !== null && $endDate->lt($startDate)) {
                        $validator->errors()->add('end_date', __('export.validation.invalid_custom_range'));
                    }
                }
            },
        ];
    }

    public function dataset(): ExportDatasetEnum
    {
        return ExportDatasetEnum::from((string) $this->validated('dataset'));
    }

    public function exportFormat(): ExportFormatEnum
    {
        return ExportFormatEnum::from((string) $this->validated('format'));
    }

    public function period(): ExportPeriod
    {
        $dataset = $this->dataset();

        if (! $dataset->supportsPeriod()) {
            return ExportPeriod::allTime();
        }

        return ExportPeriod::fromPreset(
            ExportPeriodPresetEnum::from((string) $this->validated('period_preset')),
            $this->validated('start_date'),
            $this->validated('end_date'),
        );
    }

    protected function validatedDataset(): ?ExportDatasetEnum
    {
        $value = $this->input('dataset');

        return is_string($value) && in_array($value, ExportDatasetEnum::values(), true)
            ? ExportDatasetEnum::from($value)
            : null;
    }

    protected function validatedFormat(): ?ExportFormatEnum
    {
        $value = $this->input('format');

        return is_string($value) && in_array($value, ExportFormatEnum::values(), true)
            ? ExportFormatEnum::from($value)
            : null;
    }

    protected function validatedPeriodPreset(): ?ExportPeriodPresetEnum
    {
        $value = $this->input('period_preset');

        return is_string($value) && in_array($value, ExportPeriodPresetEnum::values(), true)
            ? ExportPeriodPresetEnum::from($value)
            : null;
    }
}
