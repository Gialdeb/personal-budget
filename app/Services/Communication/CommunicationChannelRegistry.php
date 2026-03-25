<?php

namespace App\Services\Communication;

use App\Enums\CommunicationChannelEnum;

class CommunicationChannelRegistry
{
    /**
     * @return array<string, array{label:string, is_enabled:bool, is_transport_ready:bool}>
     */
    public function definitions(): array
    {
        /** @var array<string, array{label:string, is_enabled:bool, is_transport_ready:bool}> $definitions */
        $definitions = config('communication.channels', []);

        return $definitions;
    }

    /**
     * @return array<int, string>
     */
    public function values(): array
    {
        return array_keys($this->definitions());
    }

    public function has(string $channel): bool
    {
        return array_key_exists($channel, $this->definitions());
    }

    public function label(string|CommunicationChannelEnum $channel): string
    {
        $value = $channel instanceof CommunicationChannelEnum ? $channel->value : $channel;

        return $this->definitions()[$value]['label'] ?? strtoupper($value);
    }

    public function isEnabled(string|CommunicationChannelEnum $channel): bool
    {
        $value = $channel instanceof CommunicationChannelEnum ? $channel->value : $channel;

        return (bool) ($this->definitions()[$value]['is_enabled'] ?? false);
    }

    public function isTransportReady(string|CommunicationChannelEnum $channel): bool
    {
        $value = $channel instanceof CommunicationChannelEnum ? $channel->value : $channel;

        return (bool) ($this->definitions()[$value]['is_transport_ready'] ?? false);
    }

    public function isGloballyAvailable(string|CommunicationChannelEnum $channel): bool
    {
        return $this->isEnabled($channel) && $this->isTransportReady($channel);
    }
}
