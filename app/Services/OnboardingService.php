<?php

namespace App\Services;

use App\Models\User;

class OnboardingService
{
    public function __construct(protected UserSettingsService $userSettings) {}

    /**
     * @param  array<string, mixed>|null  $stored
     * @return array{enabled: bool, tours: array<string, string>, quick_start_dismissed: bool, legacy_imported: bool, revision: int}
     */
    public function state(?array $stored): array
    {
        return [
            'enabled' => (bool) ($stored['enabled'] ?? true),
            'tours' => $stored['tours'] ?? [],
            'quick_start_dismissed' => (bool) ($stored['quick_start_dismissed'] ?? false),
            'legacy_imported' => (bool) ($stored['legacy_imported'] ?? false),
            'revision' => (int) ($stored['revision'] ?? 0),
        ];
    }

    /**
     * @return array{enabled: bool, tours: array<string, string>, quick_start_dismissed: bool, legacy_imported: bool, revision: int}
     */
    public function update(User $user, string $action, bool $legacyDismissed = false, string $section = 'dashboard'): array
    {
        $settings = $this->userSettings->updateBranches($user, [
            'onboarding' => function (?array $stored) use ($action, $legacyDismissed, $section): array {
                $state = [...($stored ?? []), ...$this->state($stored)];

                if ($action === 'migrate' && ! $state['legacy_imported']) {
                    $state['quick_start_dismissed'] = $state['quick_start_dismissed'] || $legacyDismissed;
                    $state['legacy_imported'] = true;
                } elseif ($action === 'disable') {
                    $state['enabled'] = false;
                } elseif ($action === 'reset') {
                    $state['enabled'] = true;
                    unset($state['tours'][$section]);
                } elseif (in_array($action, ['complete', 'skip'], true)) {
                    $state['tours'][$section] = $action === 'complete' ? 'completed' : 'skipped';
                }

                $state['revision'] += 1;

                return $state;
            },
        ]);

        return $this->state($settings->settings['onboarding']);
    }
}
