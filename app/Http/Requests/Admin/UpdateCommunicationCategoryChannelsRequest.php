<?php

namespace App\Http\Requests\Admin;

use App\Models\CommunicationCategory;
use App\Services\Communication\CommunicationCategoryChannelService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateCommunicationCategoryChannelsRequest extends FormRequest
{
    protected ?CommunicationCategory $resolvedCategory = null;

    public function authorize(): bool
    {
        return $this->user()?->hasRole('admin') ?? false;
    }

    public function rules(): array
    {
        return [
            'channels' => ['required', 'array', 'min:1'],
            'channels.*.value' => ['required', 'string'],
            'channels.*.enabled' => ['required', 'boolean'],
            'channels.*.template_uuid' => ['nullable', 'uuid'],
        ];
    }

    public function messages(): array
    {
        return [
            'channels.required' => __('admin.communication_categories.validation.channels_required'),
            'channels.array' => __('admin.communication_categories.validation.channels_required'),
            'channels.min' => __('admin.communication_categories.validation.channels_required'),
            'channels.*.value.required' => __('admin.communication_categories.validation.channel_invalid'),
            'channels.*.enabled.required' => __('admin.communication_categories.validation.channel_invalid'),
            'channels.*.template_uuid.uuid' => __('admin.communication_categories.validation.template_invalid'),
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var CommunicationCategory|null $category */
            $category = $this->route('communicationCategory');

            if (! $category instanceof CommunicationCategory) {
                $validator->errors()->add('channels', __('admin.communication_categories.validation.category_invalid'));

                return;
            }

            $this->resolvedCategory = $category;

            /** @var CommunicationCategoryChannelService $service */
            $service = app(CommunicationCategoryChannelService::class);
            $allowedValues = $service->displayChannelValues();

            foreach ((array) $this->input('channels', []) as $index => $channel) {
                $value = (string) data_get($channel, 'value', '');
                $enabled = (bool) data_get($channel, 'enabled', false);
                $templateUuid = data_get($channel, 'template_uuid');

                if (! in_array($value, $allowedValues, true)) {
                    $validator->errors()->add("channels.{$index}.value", __('admin.communication_categories.validation.channel_invalid'));

                    continue;
                }

                if ($enabled && blank($templateUuid)) {
                    $validator->errors()->add("channels.{$index}.template_uuid", __('admin.communication_categories.validation.template_required'));
                }
            }
        });
    }

    public function category(): CommunicationCategory
    {
        return $this->resolvedCategory ?? throw new \RuntimeException('Validated category missing.');
    }
}
