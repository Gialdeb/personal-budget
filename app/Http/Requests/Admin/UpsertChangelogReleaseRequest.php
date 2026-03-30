<?php

namespace App\Http\Requests\Admin;

use App\Models\ChangelogRelease;
use App\Support\Changelog\ChangelogVersion;
use App\Supports\Locale\LocaleResolver;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use InvalidArgumentException;

class UpsertChangelogReleaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('admin') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $locales = app(LocaleResolver::class)->supportedCodes();

        return [
            'version_label' => ['required', 'string', 'max:50', 'regex:/^\d+\.\d+\.\d+(?:-[A-Za-z0-9][A-Za-z0-9.\-]*)?$/'],
            'channel' => ['required', 'string', 'max:32'],
            'is_published' => ['required', 'boolean'],
            'is_pinned' => ['required', 'boolean'],
            'published_at' => ['nullable', 'date'],
            'sort_order' => ['nullable', 'integer'],
            'translations' => ['required', 'array', 'min:1'],
            'translations.*.locale' => ['required', 'string', Rule::in($locales)],
            'translations.*.title' => ['required', 'string', 'max:255'],
            'translations.*.summary' => ['nullable', 'string'],
            'translations.*.excerpt' => ['nullable', 'string', 'max:255'],
            'sections' => ['required', 'array', 'min:1'],
            'sections.*.key' => ['required', 'string', 'max:80'],
            'sections.*.sort_order' => ['required', 'integer', 'min:0'],
            'sections.*.translations' => ['required', 'array', 'min:1'],
            'sections.*.translations.*.locale' => ['required', 'string', Rule::in($locales)],
            'sections.*.translations.*.label' => ['required', 'string', 'max:120'],
            'sections.*.items' => ['nullable', 'array'],
            'sections.*.items.*.sort_order' => ['required', 'integer', 'min:0'],
            'sections.*.items.*.screenshot_key' => ['nullable', 'string', 'max:120'],
            'sections.*.items.*.link_url' => ['nullable', 'url', 'max:2048'],
            'sections.*.items.*.link_label' => ['nullable', 'string', 'max:255'],
            'sections.*.items.*.item_type' => ['nullable', 'string', 'max:80'],
            'sections.*.items.*.platform' => ['nullable', 'string', 'max:80'],
            'sections.*.items.*.translations' => ['required', 'array', 'min:1'],
            'sections.*.items.*.translations.*.locale' => ['required', 'string', Rule::in($locales)],
            'sections.*.items.*.translations.*.title' => ['nullable', 'string', 'max:255'],
            'sections.*.items.*.translations.*.body' => ['required', 'string'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                try {
                    $version = ChangelogVersion::parse(
                        versionLabel: (string) $this->input('version_label'),
                        channel: (string) $this->input('channel'),
                    );
                } catch (InvalidArgumentException) {
                    $validator->errors()->add('version_label', __('validation.regex', ['attribute' => 'version_label']));

                    return;
                }

                $duplicateQuery = ChangelogRelease::query()
                    ->where('version_label', $version->label());

                $currentRelease = $this->route('changelogRelease');

                if ($currentRelease instanceof ChangelogRelease) {
                    $duplicateQuery->whereKeyNot($currentRelease->getKey());
                }

                if ($duplicateQuery->exists()) {
                    $validator->errors()->add('version_label', __('admin.changelog.validation.versionTaken'));
                }
            },
        ];
    }
}
