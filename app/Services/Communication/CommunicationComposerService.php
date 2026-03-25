<?php

namespace App\Services\Communication;

use App\Data\Communication\ComposedCommunicationData;
use App\Enums\CommunicationChannelEnum;
use App\Models\CommunicationCategory;
use Illuminate\Support\Facades\App;

class CommunicationComposerService
{
    public function __construct(
        protected CommunicationCategoryTemplateResolver $categoryTemplateResolver,
        protected CommunicationTemplateResolver $templateResolver,
        protected CommunicationContextResolverRegistry $contextResolverRegistry,
        protected CommunicationTemplateRenderer $templateRenderer,
        protected CommunicationVariableResolver $variableResolver,
    ) {}

    /**
     * @param  array{subject?: ?string, title?: ?string, body?: ?string, cta_label?: ?string, cta_url?: ?string}|null  $contentOverrides
     */
    public function compose(
        string $categoryKey,
        CommunicationChannelEnum $channel,
        object $model,
        ?string $forcedLocale = null,
        ?array $contentOverrides = null,
    ): ComposedCommunicationData {
        $previousLocale = App::getLocale();
        App::setLocale($forcedLocale ?? $this->resolveLocale($model));

        try {
            $mapping = $this->categoryTemplateResolver->resolve($categoryKey, $channel);

            /** @var CommunicationCategory $category */
            $category = $mapping->category;

            $contextResolver = $this->contextResolverRegistry->for($category->context_type);
            $context = $contextResolver->resolve($model);

            $resolvedTemplate = $this->templateResolver->resolveByTemplateKey($mapping->template->key);

            $rendered = $this->templateRenderer->render($resolvedTemplate, []);

            $subject = $this->resolveContentValue(
                $contentOverrides['subject'] ?? null,
                $rendered['subject'] ?? null,
                $context,
            );
            $title = $this->resolveContentValue(
                $contentOverrides['title'] ?? null,
                $rendered['title'] ?? null,
                $context,
            );
            $body = $this->resolveContentValue(
                $contentOverrides['body'] ?? null,
                $rendered['body'] ?? '',
                $context,
            ) ?? '';
            $ctaLabel = $this->resolveContentValue(
                $contentOverrides['cta_label'] ?? null,
                $rendered['cta_label'] ?? null,
                $context,
                allowEmptyOverride: true,
            );
            $ctaUrl = $this->resolveCtaUrl(
                $this->resolveContentValue(
                    $contentOverrides['cta_url'] ?? null,
                    $rendered['cta_url'] ?? null,
                    $context,
                    allowEmptyOverride: true,
                ),
                $channel,
            );

            return new ComposedCommunicationData(
                category: $category,
                channel: $channel,
                template: $mapping->template,
                override: $resolvedTemplate['override'] ?? null,
                context: $context,
                subject: $subject,
                title: $title,
                body: $body,
                ctaLabel: $ctaLabel,
                ctaUrl: $ctaUrl,
            );
        } finally {
            App::setLocale($previousLocale);
        }
    }

    protected function resolveContentValue(
        ?string $override,
        ?string $baseValue,
        array $context,
        bool $allowEmptyOverride = false,
    ): ?string {
        $candidate = $this->normalizeOverrideValue($override, $allowEmptyOverride);

        if ($candidate === null) {
            $candidate = $baseValue;
        }

        return $this->replaceIfNotNull($candidate, $context);
    }

    protected function normalizeOverrideValue(?string $value, bool $allowEmptyOverride = false): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        if ($allowEmptyOverride && $trimmed === '') {
            return '';
        }

        return $trimmed === '' ? null : $trimmed;
    }

    protected function replaceIfNotNull(?string $value, array $context): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $this->variableResolver->replacePlaceholders($value, $context);
    }

    protected function resolveLocale(object $model): string
    {
        if (method_exists($model, 'preferredLocale')) {
            return (string) $model->preferredLocale();
        }

        if (property_exists($model, 'locale') && is_string($model->locale) && $model->locale !== '') {
            return $model->locale;
        }

        return App::getLocale();
    }

    protected function resolveCtaUrl(?string $url, CommunicationChannelEnum $channel): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }

        if ($channel === CommunicationChannelEnum::MAIL && str_starts_with($url, '/')) {
            return url($url);
        }

        return $url;
    }
}
