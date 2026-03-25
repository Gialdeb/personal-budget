<?php

namespace App\Services\Communication\ContextResolvers;

use App\Contracts\CommunicationContextResolverInterface;
use App\Models\User;

class UserCommunicationContextResolver implements CommunicationContextResolverInterface
{
    public function supports(string $contextType): bool
    {
        return $contextType === 'user';
    }

    /**
     * @return array<string, mixed>
     */
    public function resolve(object $model): array
    {
        /** @var User $model */
        return [
            'user' => [
                'uuid' => $model->uuid,
                'name' => $model->name,
                'surname' => $model->surname,
                'full_name' => trim($model->name.' '.$model->surname),
                'email' => $model->email,
                'locale' => $model->locale,
                'base_currency_code' => $model->base_currency_code,
            ],
        ];
    }

    /**
     * @return array<int, array{key: string, label: string, example: string|null}>
     */
    public function availableVariables(): array
    {
        return [
            ['key' => 'user.uuid', 'label' => 'User UUID', 'example' => '550e8400-e29b-41d4-a716-446655440000'],
            ['key' => 'user.name', 'label' => 'First name', 'example' => 'Giuseppe'],
            ['key' => 'user.surname', 'label' => 'Surname', 'example' => 'De Blasio'],
            ['key' => 'user.full_name', 'label' => 'Full name', 'example' => 'Giuseppe De Blasio'],
            ['key' => 'user.email', 'label' => 'Email', 'example' => 'giuseppe@example.com'],
            ['key' => 'user.locale', 'label' => 'Locale', 'example' => 'it'],
            ['key' => 'user.base_currency_code', 'label' => 'Base currency', 'example' => 'EUR'],
        ];
    }
}
