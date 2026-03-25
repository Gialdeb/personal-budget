<?php

namespace App\Contracts;

interface CommunicationContextResolverInterface
{
    public function supports(string $contextType): bool;

    /**
     * @return array<string, mixed>
     */
    public function resolve(object $model): array;

    /**
     * @return array<int, array{key: string, label: string, example: string|null}>
     */
    public function availableVariables(): array;
}
