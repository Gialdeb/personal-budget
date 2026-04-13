<?php

namespace App\Http\Requests\Admin;

use App\Models\ContextualHelpEntry;
use App\Support\ContextualHelp\CurrentContextualHelpResolver;
use App\Supports\Locale\LocaleResolver;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpsertContextualHelpEntryRequest extends FormRequest
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
        $entry = $this->route('contextualHelpEntry');
        $pageKeys = app(CurrentContextualHelpResolver::class)->supportedPageKeys();

        return [
            'page_key' => [
                'required',
                'string',
                Rule::in($pageKeys),
                Rule::unique('contextual_help_entries', 'page_key')
                    ->ignore($entry instanceof ContextualHelpEntry ? $entry->getKey() : null),
            ],
            'knowledge_article_id' => ['nullable', 'integer', 'exists:knowledge_articles,id'],
            'sort_order' => ['required', 'integer', 'min:0'],
            'is_published' => ['required', 'boolean'],
            'translations' => ['required', 'array', 'size:'.count($locales)],
            'translations.*.locale' => ['required', 'string', Rule::in($locales)],
            'translations.*.title' => ['required', 'string', 'max:255'],
            'translations.*.body' => ['required', 'string'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $locales = collect(app(LocaleResolver::class)->supportedCodes());
                $submittedLocales = $this->submittedLocales();

                if ($submittedLocales->duplicates()->isNotEmpty()) {
                    $validator->errors()->add(
                        'translations',
                        __('admin.knowledge.validation.duplicate_locale'),
                    );
                }

                foreach ($locales as $locale) {
                    if (! $submittedLocales->contains($locale)) {
                        $validator->errors()->add(
                            'translations',
                            __('admin.knowledge.validation.missing_locale', [
                                'locale' => strtoupper((string) $locale),
                            ]),
                        );
                    }
                }
            },
        ];
    }

    /**
     * @return Collection<int, string>
     */
    protected function submittedLocales(): Collection
    {
        return collect((array) $this->input('translations', []))
            ->pluck('locale')
            ->filter(fn ($locale): bool => is_string($locale) && $locale !== '')
            ->values();
    }
}
