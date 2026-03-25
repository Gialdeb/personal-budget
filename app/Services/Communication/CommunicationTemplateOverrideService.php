<?php

namespace App\Services\Communication;

use App\Enums\CommunicationTemplateModeEnum;
use App\Enums\CommunicationTemplateOverrideScopeEnum;
use App\Models\CommunicationTemplate;
use App\Models\CommunicationTemplateOverride;
use InvalidArgumentException;

class CommunicationTemplateOverrideService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function upsertGlobalOverride(CommunicationTemplate $template, array $data): CommunicationTemplateOverride
    {
        $this->guardTemplateIsOverridable($template);

        $override = $template->overrides()
            ->where('scope', CommunicationTemplateOverrideScopeEnum::GLOBAL->value)
            ->latest('id')
            ->first();

        if (! $override) {
            $override = new CommunicationTemplateOverride([
                'scope' => CommunicationTemplateOverrideScopeEnum::GLOBAL,
            ]);

            $override->communicationTemplate()->associate($template);
        }

        $override->fill([
            'subject_template' => $data['subject_template'] ?? null,
            'title_template' => $data['title_template'] ?? null,
            'body_template' => $data['body_template'] ?? null,
            'cta_label_template' => $data['cta_label_template'] ?? null,
            'cta_url_template' => $data['cta_url_template'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);

        $override->save();

        return $override->fresh();
    }

    public function disableGlobalOverride(CommunicationTemplate $template): ?CommunicationTemplateOverride
    {
        $override = $template->overrides()
            ->where('scope', CommunicationTemplateOverrideScopeEnum::GLOBAL->value)
            ->where('is_active', true)
            ->latest('id')
            ->first();

        if (! $override) {
            return null;
        }

        $override->forceFill([
            'is_active' => false,
        ])->save();

        return $override->fresh();
    }

    public function getActiveGlobalOverride(CommunicationTemplate $template): ?CommunicationTemplateOverride
    {
        return $template->overrides()
            ->where('scope', CommunicationTemplateOverrideScopeEnum::GLOBAL->value)
            ->where('is_active', true)
            ->latest('id')
            ->first();
    }

    protected function guardTemplateIsOverridable(CommunicationTemplate $template): void
    {
        if ($template->is_system_locked || $template->template_mode === CommunicationTemplateModeEnum::SYSTEM) {
            throw new InvalidArgumentException("Communication template [{$template->key}] is system locked and cannot be overridden.");
        }
    }
}
