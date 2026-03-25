<?php

namespace App\Services\Communication;

class CommunicationVariableResolver
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function resolve(string $path, array $context): mixed
    {
        $segments = explode('.', $path);
        $value = $context;

        foreach ($segments as $segment) {
            if (is_array($value) && array_key_exists($segment, $value)) {
                $value = $value[$segment];

                continue;
            }

            return null;
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function replacePlaceholders(string $content, array $context): string
    {
        return preg_replace_callback('/\{([a-zA-Z0-9_.]+)\}/', function (array $matches) use ($context) {
            $resolved = $this->resolve($matches[1], $context);

            if ($resolved === null || is_array($resolved) || is_object($resolved)) {
                return $matches[0];
            }

            return (string) $resolved;
        }, $content) ?? $content;
    }
}
