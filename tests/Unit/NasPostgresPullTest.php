<?php

use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

beforeEach(function () {
    $this->fixture = sys_get_temp_dir().'/soamco-nas-pull-'.bin2hex(random_bytes(8));
    $this->nasRoot = $this->fixture.'/Web Backups/Soamco Budget';
    $this->storage = $this->nasRoot.'/postgresql';
    foreach ([$this->nasRoot, $this->fixture.'/remote', $this->fixture.'/commands'] as $directory) {
        mkdir($directory, 0700, true);
    }
    file_put_contents($this->fixture.'/identity', 'fixture private identity');
    chmod($this->fixture.'/identity', 0600);
    file_put_contents($this->fixture.'/known_hosts', 'fixture pinned host key');
    chmod($this->fixture.'/known_hosts', 0600);

    $mock = <<<'PHP'
    <?php
    $root = getenv('PULL_FIXTURE');
    $failure = getenv('PULL_FAILURE');
    $batch = stream_get_contents(STDIN);
    file_put_contents($root.'/calls', json_encode(['args' => array_slice($argv, 1), 'batch' => $batch])."\n", FILE_APPEND);
    if (str_starts_with($batch, '@ls ')) {
        if ($failure === 'listing') {
            fwrite(STDERR, 'PRIVATE-SFTP-DIAGNOSTIC');
            exit(42);
        }
        foreach (scandir($root.'/remote') as $name) {
            if ($name !== '.' && $name !== '..') {
                echo '/var/backups/soamco/'.$name."\n";
            }
        }
        exit(0);
    }
    foreach (array_filter(explode("\n", $batch)) as $line) {
        if (! str_starts_with($line, '@get ')) {
            exit(43);
        }
        [$remote, $local] = str_getcsv(substr($line, 5), ' ', '"', '\\');
        if (! str_starts_with($remote, '/var/backups/soamco/')) {
            exit(44);
        }
        $source = $root.'/remote/'.substr($remote, strlen('/var/backups/soamco/'));
        if ($failure === 'network') {
            file_put_contents($local, 'incomplete download');
            fwrite(STDERR, 'PRIVATE-SFTP-DIAGNOSTIC');
            exit(42);
        }
        if ($failure === 'signal') {
            file_put_contents($local, 'interrupted download');
            posix_kill(posix_getppid(), SIGTERM);
            usleep(50000);
            exit(42);
        }
        if ($failure === 'missing' && basename($remote) === 'database.list') {
            continue;
        }
        if (! is_file($source) || ! copy($source, $local)) {
            exit(45);
        }
        if ($failure === 'corrupt' && basename($remote) === 'database.dump') {
            file_put_contents($local, 'corrupted download');
        }
    }
    PHP;
    file_put_contents($this->fixture.'/commands/sftp', '#!'.PHP_BINARY."\n".$mock);
    chmod($this->fixture.'/commands/sftp', 0700);

    $this->makeBackup = function (int $days = 0, bool $local = false): string {
        $name = 'postgres-'.gmdate('Ymd\THis\Z', time() - $days * 86400).'-'.bin2hex(random_bytes(5));
        $directory = ($local ? $this->storage : $this->fixture.'/remote').'/'.$name;
        mkdir($directory, 0700, true);
        file_put_contents($directory.'/database.dump', 'PGDMP-fixture');
        file_put_contents($directory.'/database.list', "; PostgreSQL 17 archive\n1; TABLE public.example\n");
        file_put_contents($directory.'/SHA256SUMS',
            hash_file('sha256', $directory.'/database.dump')."  database.dump\n".
            hash_file('sha256', $directory.'/database.list')."  database.list\n",
        );
        if ($local) {
            file_put_contents($directory.'/.soamco-nas-receipt.json', json_encode([
                'schema' => 1,
                'source' => 'soamco-backup@100.73.11.75:/var/backups/soamco',
                'backup' => $name,
                'checksums_sha256' => hash_file('sha256', $directory.'/SHA256SUMS'),
            ]));
        }
        foreach (glob($directory.'/*') as $file) {
            chmod($file, 0600);
        }

        return $name;
    };
    $this->remoteName = ($this->makeBackup)();
    $this->environment = [];
    $this->runPull = function (string $failure = '', array $arguments = []): Process {
        $process = new Process(['python3', '-B', dirname(__DIR__, 2).'/bin/pull-postgres-backups-nas', ...$arguments], env: [
            'PATH' => $this->fixture.'/commands:'.getenv('PATH'),
            'PULL_FIXTURE' => $this->fixture,
            'PULL_FAILURE' => $failure,
            'SOAMCO_NAS_BACKUP_ROOT' => $this->nasRoot,
            'SOAMCO_SSH_IDENTITY' => $this->fixture.'/identity',
            'SOAMCO_SSH_KNOWN_HOSTS' => $this->fixture.'/known_hosts',
            ...$this->environment,
        ]);
        $process->setTimeout(10);
        $process->run();

        return $process;
    };
});

afterEach(function () {
    (new Filesystem)->deleteDirectory($this->fixture);
});

it('pulls only published PostgreSQL artifacts into an atomic verified NAS copy and skips duplicates', function () {
    mkdir($this->fixture.'/remote/.postgres-incomplete.abcdefghij');
    file_put_contents($this->fixture.'/remote/user-backup.zip', 'unmanaged');
    $first = ($this->runPull)();
    expect($first->getExitCode())->toBe(0, $first->getErrorOutput());
    $directory = $this->storage.'/'.$this->remoteName;
    expect(file_get_contents($directory.'/database.dump'))->toBe('PGDMP-fixture')
        ->and(glob($this->storage.'/.pull-incomplete-*'))->toBe([])
        ->and(fileperms($directory) & 0777)->toBe(0700)
        ->and(fileperms($directory.'/database.dump') & 0777)->toBe(0600);
    $inode = fileinode($directory.'/database.dump');
    $second = ($this->runPull)();
    expect($second->getExitCode())->toBe(0, $second->getErrorOutput())
        ->and($second->getErrorOutput())->toContain('Already verified locally')
        ->and(fileinode($directory.'/database.dump'))->toBe($inode);
    $calls = array_map(fn (string $line): array => json_decode($line, true), file($this->fixture.'/calls'));
    expect(array_filter($calls, fn (array $call): bool => str_starts_with($call['batch'], '@get ')))->toHaveCount(1);
    foreach ($calls as $call) {
        expect($call['args'])->toContain('soamco-backup@100.73.11.75', '22', 'StrictHostKeyChecking=yes', 'BatchMode=yes', 'IdentitiesOnly=yes')
            ->and($call['batch'])->not->toContain('@put ', '@rm ', '@rename ', '@mkdir ', 'user-backup');
    }
    expect(file_get_contents($this->storage.'/.pull.log'))->toContain('START', 'SUCCESS', 'SKIP')
        ->and(is_file($this->fixture.'/remote/'.$this->remoteName.'/database.dump'))->toBeTrue();
});

it('preserves existing copies and removes incomplete downloads after errors or interruptions', function (string $failure) {
    $old = ($this->makeBackup)(20, true);
    $hash = hash_file('sha256', $this->storage.'/'.$old.'/database.dump');
    $process = ($this->runPull)($failure);
    expect($process->isSuccessful())->toBeFalse()
        ->and(is_dir($this->storage.'/'.$this->remoteName))->toBeFalse()
        ->and(hash_file('sha256', $this->storage.'/'.$old.'/database.dump'))->toBe($hash)
        ->and(glob($this->storage.'/.pull-incomplete-*'))->toBe([])
        ->and(file_get_contents($this->storage.'/.pull.log'))->toContain('ERROR')
        ->not->toContain('PRIVATE-SFTP-DIAGNOSTIC', 'RETENTION');
    $retry = ($this->runPull)();
    expect($retry->getExitCode())->toBe(0, $retry->getErrorOutput())
        ->and(is_file($this->storage.'/'.$this->remoteName.'/database.dump'))->toBeTrue();
})->with(['listing', 'network', 'missing', 'corrupt', 'signal']);

it('refuses unsafe checksum paths before considering a download complete', function () {
    file_put_contents($this->fixture.'/remote/'.$this->remoteName.'/SHA256SUMS', str_repeat('a', 64)."  ../../identity\n");
    $process = ($this->runPull)();
    expect($process->isSuccessful())->toBeFalse()
        ->and(file_get_contents($this->fixture.'/identity'))->toBe('fixture private identity')
        ->and(glob($this->storage.'/postgres-*'))->toBe([])
        ->and(glob($this->storage.'/.pull-incomplete-*'))->toBe([]);
});

it('prunes only verified NAS-owned backups older than ten days', function () {
    $old = ($this->makeBackup)(11, true);
    $recent = ($this->makeBackup)(9, true);
    $unmanaged = ($this->makeBackup)(20, true);
    unlink($this->storage.'/'.$unmanaged.'/.soamco-nas-receipt.json');
    $extra = ($this->makeBackup)(20, true);
    file_put_contents($this->storage.'/'.$extra.'/.keep', 'unmanaged data');
    $process = ($this->runPull)();
    expect($process->getExitCode())->toBe(0, $process->getErrorOutput());
    clearstatcache();
    expect(is_dir($this->storage.'/'.$old))->toBeFalse()
        ->and(is_dir($this->storage.'/'.$recent))->toBeTrue()
        ->and(is_dir($this->storage.'/'.$unmanaged))->toBeTrue()
        ->and(file_get_contents($this->storage.'/'.$extra.'/.keep'))->toBe('unmanaged data')
        ->and(is_dir($this->fixture.'/remote/'.$this->remoteName))->toBeTrue();
});

it('keeps the newest valid NAS copy even if all copies are older than ten days', function () {
    (new Filesystem)->deleteDirectory($this->fixture.'/remote');
    mkdir($this->fixture.'/remote');
    $keeper = ($this->makeBackup)(20, true);
    $expired = ($this->makeBackup)(30, true);
    $corrupt = ($this->makeBackup)(15, true);
    file_put_contents($this->storage.'/'.$corrupt.'/database.dump', 'corrupt');
    $process = ($this->runPull)();
    expect($process->getExitCode())->toBe(0, $process->getErrorOutput());
    clearstatcache();
    expect(is_file($this->storage.'/'.$keeper.'/database.dump'))->toBeTrue()
        ->and(is_dir($this->storage.'/'.$expired))->toBeFalse()
        ->and(is_dir($this->storage.'/'.$corrupt))->toBeTrue();
});

it('does not replace an existing damaged destination', function () {
    expect(($this->runPull)()->getExitCode())->toBe(0);
    $path = $this->storage.'/'.$this->remoteName.'/database.dump';
    file_put_contents($path, 'do not overwrite');
    $process = ($this->runPull)();
    expect($process->isSuccessful())->toBeFalse()
        ->and(file_get_contents($path))->toBe('do not overwrite')
        ->and(glob($this->storage.'/.pull-incomplete-*'))->toBe([]);
});

it('refuses concurrent pulls before starting SFTP', function () {
    mkdir($this->storage, 0700);
    $lock = fopen($this->storage.'/.pull.lock', 'w');
    chmod($this->storage.'/.pull.lock', 0600);
    flock($lock, LOCK_EX);
    try {
        $process = ($this->runPull)();
        expect($process->getExitCode())->toBe(75)
            ->and(file_exists($this->fixture.'/calls'))->toBeFalse();
    } finally {
        fclose($lock);
    }
});

it('requires private credentials and pinned hosts without privilege or automatic trust changes', function (string $kind) {
    if ($kind === 'permissions') {
        chmod($this->fixture.'/identity', 0644);
    } elseif ($kind === 'host-key') {
        unlink($this->fixture.'/known_hosts');
    } elseif ($kind === 'namespace') {
        mkdir($this->storage, 0770);
        chmod($this->storage, 0770);
    } else {
        $this->environment['SOAMCO_NAS_BACKUP_ROOT'] = $this->nasRoot."\nget something";
    }
    $process = ($this->runPull)();
    expect($process->isSuccessful())->toBeFalse()
        ->and(file_exists($this->fixture.'/calls'))->toBeFalse();
})->with(['permissions', 'host-key', 'namespace', 'newline']);

it('provides only an inactive hourly user-crontab entry for the NAS', function () {
    $root = dirname(__DIR__, 2);
    expect(file_get_contents($root.'/docker/soamco-nas-pull.crontab'))->toContain('15 * * * * /home/Alex/.local/bin/soamco-pull-postgres')
        ->not->toContain('sudo', 'systemctl', 'root ')
        ->and(file_get_contents($root.'/bin/pull-postgres-backups-nas'))->toContain('/home/Alex/.ssh/soamco_backup_ed25519', '/volume1/Web Backups/Soamco Budget', '100.73.11.75');
});

it('checks NAS prerequisites without connecting or activating any schedule', function () {
    $process = ($this->runPull)('', ['--check']);
    expect($process->getExitCode())->toBe(0, $process->getErrorOutput())
        ->and($process->getErrorOutput())->toContain('Local preflight passed', 'no SFTP connection')
        ->and(file_exists($this->fixture.'/calls'))->toBeFalse()
        ->and(glob($this->storage.'/postgres-*'))->toBe([]);
});
