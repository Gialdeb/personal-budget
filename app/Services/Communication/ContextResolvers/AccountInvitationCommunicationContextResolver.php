<?php

namespace App\Services\Communication\ContextResolvers;

use App\Contracts\CommunicationContextResolverInterface;
use App\Models\AccountInvitation;
use Illuminate\Support\Facades\App;

class AccountInvitationCommunicationContextResolver implements CommunicationContextResolverInterface
{
    public function supports(string $contextType): bool
    {
        return $contextType === 'account_invitation';
    }

    /**
     * @return array<string, mixed>
     */
    public function resolve(object $model): array
    {
        /** @var AccountInvitation $model */
        $model->loadMissing(['account', 'invitedBy']);

        $plainToken = (string) $model->getAttribute('plain_token');
        $formattedExpiration = $model->expires_at?->clone()
            ->locale(App::currentLocale())
            ->translatedFormat('d F Y H:i');

        return [
            'inviter_name' => trim(implode(' ', array_filter([
                $model->invitedBy?->name,
                $model->invitedBy?->surname,
            ]))) ?: $model->invitedBy?->name,
            'account_name' => $model->account?->name,
            'invitee_email' => $model->email,
            'invitation_role' => $model->role?->value,
            'invitation_role_label' => $model->role?->label(),
            'invitation_accept_url' => route('account-invitations.onboarding.show', $model).'?token='.$plainToken,
            'invitation_expires_at' => $formattedExpiration,
            'invitation_expiry_notice' => $formattedExpiration
                ? __('notifications.topics.account_invitation.expiry_notice', ['date' => $formattedExpiration])
                : '',
        ];
    }

    /**
     * @return array<int, array{key: string, label: string, example: string|null}>
     */
    public function availableVariables(): array
    {
        return [
            ['key' => 'inviter_name', 'label' => 'Inviter name', 'example' => 'Giuseppe De Blasio'],
            ['key' => 'account_name', 'label' => 'Account name', 'example' => 'Main account'],
            ['key' => 'invitee_email', 'label' => 'Invitee email', 'example' => 'wife@example.com'],
            ['key' => 'invitation_role', 'label' => 'Invitation role key', 'example' => 'viewer'],
            ['key' => 'invitation_role_label', 'label' => 'Invitation role label', 'example' => 'Solo visualizzazione'],
            ['key' => 'invitation_accept_url', 'label' => 'Invitation accept URL', 'example' => 'https://example.test/account-invitations/uuid/onboarding?token=secret'],
            ['key' => 'invitation_expires_at', 'label' => 'Invitation expiration date', 'example' => '25 marzo 2026 12:00'],
            ['key' => 'invitation_expiry_notice', 'label' => 'Invitation expiry notice', 'example' => 'Questo invito scade il 25 marzo 2026 12:00.'],
        ];
    }
}
