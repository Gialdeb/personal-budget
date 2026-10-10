<?php

namespace App\Logging;

use Illuminate\Log\Logger;
use Monolog\LogRecord;
use Throwable;

class SanitizeDiagnosticLogs
{
    public function __invoke(Logger $logger): void
    {
        $logger->getLogger()->pushProcessor(function (LogRecord $record): LogRecord {
            $secrets = [];
            $this->collectSecrets(config()->all(), $secrets);
            if (is_string(config('app.key')) && config('app.key') !== '') {
                $secrets[] = config('app.key');
            }
            if (app()->bound('request')) {
                $request = request();
                $this->collectSecrets($request->all(), $secrets);
                foreach ([$request->header('Authorization'), $request->header('Cookie'), ...array_values($request->cookies->all())] as $secret) {
                    if (is_string($secret) && $secret !== '') {
                        $secrets[] = $secret;
                    }
                }
                if ($authorization = $request->bearerToken()) {
                    $secrets[] = $authorization;
                }
            }

            return $record->with(
                message: $this->redact($record->message, $secrets),
                context: $this->sanitize($record->context, $secrets),
                extra: $this->sanitize($record->extra, $secrets),
            );
        });
    }

    /** @param array<string, mixed> $values
     * @param  list<string>  $secrets
     */
    private function collectSecrets(array $values, array &$secrets, bool $sensitive = false): void
    {
        foreach ($values as $key => $value) {
            $isSensitive = $sensitive || $this->isSensitiveKey((string) $key);
            if (is_array($value)) {
                $this->collectSecrets($value, $secrets, $isSensitive);
            } elseif ($isSensitive && (is_string($value) || is_int($value) || is_float($value)) && (string) $value !== '') {
                $secrets[] = (string) $value;
            }
        }
    }

    private function isSensitiveKey(string $key): bool
    {
        $key = preg_replace('/([a-z])([A-Z])/', '$1_$2', $key);

        return (bool) preg_match('/(?:^|_)(?:password(?:_confirmation|_hash)?|token|cookie|authorization|secret|credentials?|api_?key)$/i', $key);
    }

    /** @param array<string, mixed> $context
     * @param  list<string>  $secrets
     * @return array<string, mixed>
     */
    private function sanitize(array $context, array $secrets): array
    {
        foreach ($context as $key => $value) {
            if ($this->isSensitiveKey((string) $key)) {
                $context[$key] = '[REDACTED]';
            } elseif ($value instanceof Throwable) {
                $context[$key] = $this->exception($value, $secrets);
            } elseif (is_array($value)) {
                $context[$key] = $this->sanitize($value, $secrets);
            } elseif (is_string($value)) {
                $context[$key] = $this->redact($value, $secrets);
            } elseif (is_object($value)) {
                $context[$key] = ['class' => $value::class];
            }
        }

        return $context;
    }

    /** @param list<string> $secrets
     * @return array<string, mixed>
     */
    private function exception(Throwable $exception, array $secrets): array
    {
        $trace = array_map(fn (array $frame): array => array_intersect_key($frame, array_flip(['file', 'line', 'class', 'type', 'function'])), $exception->getTrace());

        return [
            'class' => $exception::class,
            'message' => $this->redact($exception->getMessage(), $secrets),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $trace,
            'previous' => $exception->getPrevious() ? $this->exception($exception->getPrevious(), $secrets) : null,
        ];
    }

    /** @param list<string> $secrets */
    private function redact(string $message, array $secrets): string
    {
        usort($secrets, fn (string $first, string $second): int => strlen($second) <=> strlen($first));

        return str_replace($secrets, '[REDACTED]', $message);
    }
}
