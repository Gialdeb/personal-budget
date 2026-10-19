<?php

use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Foundation\Bootstrap\LoadEnvironmentVariables;

/**
 * @return Closure(bool, mixed, string, ?string): ?string
 */
$checkFirebaseCredentials = static function (bool $enabled, mixed $credentials, string $privateDirectory, ?string $webProjectId = null): ?string {
    if (! $enabled) {
        return null;
    }

    if (! is_string($credentials) || trim($credentials) === '') {
        return 'Set FIREBASE_CREDENTIALS (or GOOGLE_APPLICATION_CREDENTIALS) to a JSON file in storage/app/private.';
    }

    if (! is_file($credentials)) {
        return 'Credential file is missing or inaccessible in the private Docker volume. A file on the host alone is not available in this volume.';
    }

    $privateRoot = realpath($privateDirectory);
    $credentialPath = realpath($credentials);

    if ($privateRoot === false || $credentialPath === false || ! str_starts_with($credentialPath, $privateRoot.DIRECTORY_SEPARATOR)) {
        return 'Credential file must be inside storage/app/private so it persists in the existing Docker volume.';
    }

    if (! is_readable($credentialPath)) {
        return 'Credential file is not readable by www-data. Check file owner, group and directory traversal permissions in the private volume.';
    }

    $contents = @file_get_contents($credentialPath);

    if ($contents === false) {
        return 'Credential file could not be read by www-data. Check permissions in the private volume.';
    }

    try {
        $account = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        return 'Credential file is not valid JSON. Provision a valid service account file without overwriting existing credentials.';
    }

    foreach (['project_id', 'client_email', 'private_key'] as $field) {
        if (! is_array($account) || ! is_string($account[$field] ?? null) || trim($account[$field]) === '') {
            return 'Credential file is not a complete Firebase service account JSON.';
        }
    }

    if (($account['type'] ?? null) !== 'service_account' || @openssl_pkey_get_private($account['private_key']) === false) {
        return 'Credential file has an invalid service account type or private key.';
    }

    if (is_string($webProjectId) && $webProjectId !== '' && $account['project_id'] !== $webProjectId) {
        return 'Firebase service account project does not match VITE_FIREBASE_PROJECT_ID.';
    }

    return null;
};

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') !== __FILE__) {
    return $checkFirebaseCredentials;
}

try {
    require dirname(__DIR__, 2).'/vendor/autoload.php';
    $app = require dirname(__DIR__, 2).'/bootstrap/app.php';
    $app->bootstrapWith([
        LoadEnvironmentVariables::class,
        LoadConfiguration::class,
    ]);
    $project = (string) config('firebase.default', 'app');
    $enabled = (bool) config('features.push_notifications.enabled');
    $problem = $checkFirebaseCredentials(
        $enabled,
        config('firebase.projects.'.$project.'.credentials'),
        storage_path('app/private'),
        config('push-notifications.firebase_web.project_id'),
    );

    if ($problem !== null) {
        fwrite(STDERR, 'Firebase credentials preflight failed: '.$problem.PHP_EOL);
        exit(1);
    }

    fwrite(STDOUT, $enabled ? 'Firebase credentials preflight passed.'.PHP_EOL : 'Firebase credentials preflight skipped: push feature disabled.'.PHP_EOL);
} catch (Throwable) {
    fwrite(STDERR, 'Firebase credentials preflight failed: application configuration could not be loaded. Check production environment configuration.'.PHP_EOL);
    exit(1);
}
