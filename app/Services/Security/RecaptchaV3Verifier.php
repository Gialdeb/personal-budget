<?php

namespace App\Services\Security;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class RecaptchaV3Verifier
{
    public function assertValid(Request $request, string $action): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $token = trim((string) $request->input('recaptcha_token', ''));

        if ($token === '') {
            $this->fail(__('auth.recaptcha_required'));
        }

        $secretKey = trim((string) config('recaptcha.secret_key', ''));

        if ($secretKey === '') {
            $this->fail(__('auth.recaptcha_failed'));
        }

        try {
            $response = Http::asForm()
                ->timeout((int) config('recaptcha.timeout', 5))
                ->acceptJson()
                ->post((string) config('recaptcha.verify_url'), [
                    'secret' => $secretKey,
                    'response' => $token,
                    'remoteip' => $request->ip(),
                ]);
        } catch (\Throwable $exception) {
            report($exception);

            $this->fail(__('auth.recaptcha_failed'));
        }

        if (! $response->successful()) {
            report('reCAPTCHA verification failed with HTTP status '.$response->status().'.');

            $this->fail(__('auth.recaptcha_failed'));
        }

        /** @var array<string, mixed> $payload */
        $payload = $response->json();
        $score = (float) ($payload['score'] ?? 0);
        $expectedThreshold = (float) config("recaptcha.actions.{$action}.threshold", config('recaptcha.threshold', 0.5));

        $this->logVerificationResult($request, $action, $payload);

        if (($payload['success'] ?? false) !== true) {
            report('reCAPTCHA verification failed: '.json_encode($payload['error-codes'] ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            $this->fail(__('auth.recaptcha_failed'));
        }

        if (($payload['action'] ?? null) !== $action) {
            $this->fail(__('auth.recaptcha_failed'));
        }

        if (! $this->matchesExpectedHostname($payload['hostname'] ?? null)) {
            $this->fail(__('auth.recaptcha_failed'));
        }

        if ($score < $expectedThreshold) {
            $this->fail(__('auth.recaptcha_failed'));
        }
    }

    public function frontendConfig(): array
    {
        return [
            'enabled' => $this->isEnabled(),
            'siteKey' => $this->isEnabled() ? config('recaptcha.site_key') : null,
        ];
    }

    protected function isEnabled(): bool
    {
        return (bool) config('recaptcha.enabled', false);
    }

    protected function fail(string $message): never
    {
        throw ValidationException::withMessages([
            'recaptcha_token' => $message,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function logVerificationResult(Request $request, string $action, array $payload): void
    {
        if (! (bool) config('recaptcha.log_results', false)) {
            return;
        }

        Log::info('reCAPTCHA verification result', [
            'flow' => $action,
            'request_host' => $request->getHost(),
            'request_path' => $request->path(),
            'app_env' => app()->environment(),
            'success' => (bool) ($payload['success'] ?? false),
            'score' => isset($payload['score']) ? (float) $payload['score'] : null,
            'action' => $payload['action'] ?? null,
            'hostname' => $payload['hostname'] ?? null,
            'challenge_ts' => $payload['challenge_ts'] ?? null,
            'error_codes' => $payload['error-codes'] ?? [],
        ]);
    }

    protected function matchesExpectedHostname(mixed $hostname): bool
    {
        $expectedHostnames = config('recaptcha.expected_hostnames', []);

        if (! is_array($expectedHostnames) || $expectedHostnames === []) {
            return true;
        }

        if (! is_string($hostname) || $hostname === '') {
            return false;
        }

        return in_array($hostname, $expectedHostnames, true);
    }
}
