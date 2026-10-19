<?php

use Symfony\Component\Process\Process;

beforeEach(function () {
    $this->privateDirectory = sys_get_temp_dir().'/soamco-firebase-preflight-'.bin2hex(random_bytes(8));
    mkdir($this->privateDirectory, 0700);
    $this->credentialFile = $this->privateDirectory.'/service-account.json';
    $this->checkCredentials = require dirname(__DIR__, 2).'/docker/php/firebase-credentials-check.php';
    $key = openssl_pkey_new(['private_key_bits' => 1024]);
    openssl_pkey_export($key, $privateKey);
    $this->account = ['type' => 'service_account', 'project_id' => 'fixture-project', 'client_email' => 'fixture@example.invalid', 'private_key' => $privateKey];
    file_put_contents($this->credentialFile, json_encode($this->account, JSON_THROW_ON_ERROR));
    chmod($this->credentialFile, 0600);
});

afterEach(function () {
    chmod($this->credentialFile, 0600);
    unlink($this->credentialFile);
    rmdir($this->privateDirectory);
});

it('accepts a readable service account in persistent storage without modifying the file', function () {
    $before = hash_file('sha256', $this->credentialFile);
    $permissions = fileperms($this->credentialFile);
    expect(($this->checkCredentials)(true, $this->credentialFile, $this->privateDirectory, 'fixture-project'))->toBeNull()
        ->and(hash_file('sha256', $this->credentialFile))->toBe($before)
        ->and(fileperms($this->credentialFile))->toBe($permissions);
});

it('allows a deployment without credentials when push is disabled', function () {
    expect(($this->checkCredentials)(false, null, $this->privateDirectory))->toBeNull();
});

it('clearly rejects a missing credential configuration', function (mixed $configuration) {
    expect(($this->checkCredentials)(true, $configuration, $this->privateDirectory))->toContain('Set FIREBASE_CREDENTIALS');
})->with([[null], [''], [['private_key' => 'do-not-print']]]);

it('rejects a file present only outside the persistent volume', function () {
    expect(($this->checkCredentials)(true, $this->credentialFile, $this->privateDirectory.'/another-volume'))->toContain('must be inside storage/app/private');
});

it('rejects a missing volume file without creating it or disclosing its path', function () {
    $missing = $this->privateDirectory.'/sensitive-name.json';
    $problem = ($this->checkCredentials)(true, $missing, $this->privateDirectory);
    expect($problem)->toContain('missing or inaccessible')->not->toContain($missing)
        ->and(file_exists($missing))->toBeFalse();
});

it('detects unreadable files without changing their permissions', function () {
    if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
        $this->markTestSkipped('Root bypasses file readability; production preflight explicitly runs as www-data.');
    }
    chmod($this->credentialFile, 0000);
    clearstatcache();
    expect(($this->checkCredentials)(true, $this->credentialFile, $this->privateDirectory))->toContain('not readable by www-data')
        ->and(fileperms($this->credentialFile) & 0777)->toBe(0);
});

it('rejects invalid credentials without echoing their contents', function (string $kind) {
    $secret = 'DO-NOT-LOG-THIS-PRIVATE-CONTENT';
    $contents = match ($kind) {
        'json' => '{'.$secret,
        'fields' => json_encode(['private_key' => $secret]),
        'key' => json_encode([...$this->account, 'private_key' => $secret]),
    };
    file_put_contents($this->credentialFile, $contents);
    $problem = ($this->checkCredentials)(true, $this->credentialFile, $this->privateDirectory);
    expect($problem)->toBeString()->not->toContain($secret)
        ->and(file_get_contents($this->credentialFile))->toBe($contents);
})->with(['json', 'fields', 'key']);

it('detects a mismatched project without printing either project or key', function () {
    $problem = ($this->checkCredentials)(true, $this->credentialFile, $this->privateDirectory, 'another-project');
    expect($problem)->toContain('does not match')->not->toContain('fixture-project')->not->toContain('another-project')->not->toContain($this->account['private_key']);
});

it('fails the actual preflight command with a sanitized actionable error', function () {
    $process = new Process([PHP_BINARY, 'docker/php/firebase-credentials-check.php'], dirname(__DIR__, 2), [
        'FEATURE_PUSH_NOTIFICATIONS_ENABLED' => 'true',
        'FIREBASE_CREDENTIALS' => $this->privateDirectory.'/SECRET-NAME.json',
        'APP_CONFIG_CACHE' => $this->privateDirectory.'/unused-cache.php',
    ]);
    $process->run();
    expect($process->getExitCode())->toBe(1)
        ->and($process->getErrorOutput())->toContain('Firebase credentials preflight failed:', 'private Docker volume')->not->toContain('SECRET-NAME')
        ->and($process->getOutput())->toBe('');
});
