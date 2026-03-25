<?php

namespace App\Http\Requests\Admin;

use App\Enums\CommunicationChannelEnum;
use App\Models\CommunicationCategory;
use App\Models\User;
use App\Services\Communication\ManualCommunicationCatalogService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class PreviewCommunicationRequest extends FormRequest
{
    protected ?CommunicationCategory $resolvedCategory = null;

    /** @var Collection<int, User>|null */
    protected ?Collection $resolvedRecipients = null;

    public function authorize(): bool
    {
        return $this->user()?->hasRole('admin') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'category_uuid' => ['required', 'uuid'],
            'channels' => ['required', 'array', 'min:1'],
            'channels.*' => ['required', 'string'],
            'recipient_uuids' => ['required', 'array', 'min:1'],
            'recipient_uuids.*' => ['required', 'uuid'],
            'locale' => ['required', 'string', Rule::in($this->allowedLocaleValues())],
            'content_mode' => ['required', 'string', Rule::in(['template', 'custom'])],
            'custom_content' => ['nullable', 'array'],
            'custom_content.subject' => ['nullable', 'string', 'max:255'],
            'custom_content.title' => ['nullable', 'string', 'max:255'],
            'custom_content.body' => ['nullable', 'string'],
            'custom_content.cta_label' => ['nullable', 'string', 'max:255'],
            'custom_content.cta_url' => ['nullable', 'string', 'max:2048'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'category_uuid.required' => __('admin.communication_composer.validation.category_required'),
            'category_uuid.uuid' => __('admin.communication_composer.validation.category_invalid'),
            'channels.required' => __('admin.communication_composer.validation.channels_required'),
            'channels.array' => __('admin.communication_composer.validation.channels_required'),
            'channels.min' => __('admin.communication_composer.validation.channels_required'),
            'channels.*.required' => __('admin.communication_composer.validation.channel_invalid'),
            'recipient_uuids.required' => __('admin.communication_composer.validation.recipients_required'),
            'recipient_uuids.array' => __('admin.communication_composer.validation.recipients_required'),
            'recipient_uuids.min' => __('admin.communication_composer.validation.recipients_required'),
            'recipient_uuids.*.required' => __('admin.communication_composer.validation.recipient_invalid'),
            'recipient_uuids.*.uuid' => __('admin.communication_composer.validation.recipient_invalid'),
            'locale.required' => __('admin.communication_composer.validation.locale_required'),
            'locale.in' => __('admin.communication_composer.validation.locale_invalid'),
            'content_mode.required' => __('admin.communication_composer.validation.content_mode_required'),
            'content_mode.in' => __('admin.communication_composer.validation.content_mode_invalid'),
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var ManualCommunicationCatalogService $catalog */
            $catalog = app(ManualCommunicationCatalogService::class);

            $category = $catalog->findManualCategoryByUuid((string) $this->input('category_uuid'));

            if (! $category) {
                $validator->errors()->add('category_uuid', __('admin.communication_composer.validation.category_invalid'));

                return;
            }

            $this->resolvedCategory = $category;

            $availableChannels = collect($catalog->availableChannels($category))
                ->map(fn (CommunicationChannelEnum $channel): string => $channel->value)
                ->all();

            foreach ((array) $this->input('channels', []) as $index => $channelValue) {
                if (! in_array((string) $channelValue, $availableChannels, true)) {
                    $validator->errors()->add("channels.{$index}", __('admin.communication_composer.validation.channel_invalid'));
                }
            }

            $recipientUuids = collect((array) $this->input('recipient_uuids', []))
                ->filter(fn ($uuid) => is_string($uuid) && $uuid !== '')
                ->values();

            $recipients = $catalog->recipientQuery()
                ->whereIn('uuid', $recipientUuids->all())
                ->get()
                ->keyBy('uuid');

            if ($recipients->count() !== $recipientUuids->count()) {
                $validator->errors()->add('recipient_uuids', __('admin.communication_composer.validation.recipient_invalid'));
            }

            $this->resolvedRecipients = $recipientUuids
                ->map(fn (string $uuid) => $recipients->get($uuid))
                ->filter()
                ->values();

            if ($this->contentMode() === 'custom' && blank($this->input('custom_content.body'))) {
                $validator->errors()->add('custom_content.body', __('admin.communication_composer.validation.custom_body_required'));
            }
        });
    }

    public function category(): CommunicationCategory
    {
        return $this->resolvedCategory ?? throw new \RuntimeException('Validated category missing.');
    }

    /**
     * @return Collection<int, User>
     */
    public function recipients(): Collection
    {
        return $this->resolvedRecipients ?? collect();
    }

    /**
     * @return array<int, CommunicationChannelEnum>
     */
    public function channels(): array
    {
        return collect((array) $this->input('channels', []))
            ->map(fn (string $channel) => CommunicationChannelEnum::from($channel))
            ->values()
            ->all();
    }

    public function contentMode(): string
    {
        return (string) $this->input('content_mode', 'template');
    }

    /**
     * @return array{subject?: ?string, title?: ?string, body?: ?string, cta_label?: ?string, cta_url?: ?string}|null
     */
    public function customContent(): ?array
    {
        if ($this->contentMode() !== 'custom') {
            return null;
        }

        /** @var array{subject?: ?string, title?: ?string, body?: ?string, cta_label?: ?string, cta_url?: ?string}|null $content */
        $content = $this->input('custom_content');

        return [
            'subject' => $content['subject'] ?? null,
            'title' => $content['title'] ?? null,
            'body' => $content['body'] ?? null,
            'cta_label' => array_key_exists('cta_label', $content ?? []) ? ($content['cta_label'] ?? '') : '',
            'cta_url' => array_key_exists('cta_url', $content ?? []) ? ($content['cta_url'] ?? '') : '',
        ];
    }

    public function forcedLocaleFor(User $recipient): ?string
    {
        $locale = (string) $this->input('locale', 'recipient');

        if ($locale === 'recipient') {
            return $recipient->preferredLocale();
        }

        return $locale;
    }

    /**
     * @return array<int, string>
     */
    protected function allowedLocaleValues(): array
    {
        return array_merge(
            ['recipient'],
            array_keys(config('locales.supported', [])),
        );
    }
}
