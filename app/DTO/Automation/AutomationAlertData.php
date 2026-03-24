<?php

namespace App\DTO\Automation;

class AutomationAlertData
{
    public function __construct(
        public readonly string $type,
        public readonly string $pipeline,
        public readonly string $title,
        public readonly string $message,
        public readonly array $context = [],
    ) {}

    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'pipeline' => $this->pipeline,
            'title' => $this->title,
            'message' => $this->message,
            'context' => $this->context,
        ];
    }
}
