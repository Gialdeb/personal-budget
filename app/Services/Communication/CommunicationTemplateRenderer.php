<?php

namespace App\Services\Communication;

class CommunicationTemplateRenderer
{
    /**
     * @param  array<string, mixed>  $resolvedTemplate
     * @param  array<string, mixed>  $payload
     * @return array<string, string|null>
     */
    public function render(array $resolvedTemplate, array $payload = []): array
    {
        return [
            'subject' => $this->translateOrNull($resolvedTemplate['subject_template'] ?? null, $payload),
            'title' => $this->translateOrNull($resolvedTemplate['title_template'] ?? null, $payload),
            'body' => $this->translateOrNull($resolvedTemplate['body_template'] ?? null, $payload),
            'cta_label' => $this->translateOrNull($resolvedTemplate['cta_label_template'] ?? null, $payload),
            'cta_url' => $this->interpolateRaw($resolvedTemplate['cta_url_template'] ?? null, $payload),
        ];
    }

    protected function translateOrNull(?string $value, array $payload = []): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return __($value, $payload);
    }

    protected function interpolateRaw(?string $value, array $payload = []): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        foreach ($payload as $key => $replacement) {
            if (is_scalar($replacement)) {
                $value = str_replace('{'.$key.'}', (string) $replacement, $value);
            }
        }

        return $value;
    }
}
