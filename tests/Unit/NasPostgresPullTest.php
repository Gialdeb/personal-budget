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
    if (str_starts_with($failure, 'files-')) {
        $failure = str_contains($batch, '/files-encrypted-') ? substr($failure, 6) : '';
    }
    file_put_contents($root.'/calls', json_encode(['args' => array_slice($argv, 1), 'batch' => $batch])."\n", FILE_APPEND);
    if (str_starts_with($batch, '@ls ')) {
        if ($failure === 'listing') {
            fwrite(STDERR, 'PRIVATE-SFTP-DIAGNOSTIC');
            exit(42);
        }
        foreach (scandir($root.'/remote') as $name) {
            if ($name !== '.' && $name !== '..') {
                echo '/soamco/'.$name."\n";
            }
        }
        exit(0);
    }
    foreach (array_filter(explode("\n", $batch)) as $line) {
        if (! str_starts_with($line, '@get ')) {
            exit(43);
        }
        [$remote, $local] = str_getcsv(substr($line, 5), ' ', '"', '\\');
        if (! str_starts_with($remote, '/soamco/')) {
            exit(44);
        }
        $source = $root.'/remote/'.substr($remote, strlen('/soamco/'));
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

    $this->makeBackup = function (int $days = 0, bool $local = false, bool $encrypted = false): string {
        $name = 'postgres-'.gmdate('Ymd\THis\Z', time() - $days * 86400).'-'.bin2hex(random_bytes(5));
        $directory = ($local ? $this->storage : $this->fixture.'/remote').'/'.$name;
        mkdir($directory, 0700, true);
        file_put_contents($directory.'/database.dump', 'PGDMP-fixture');
        file_put_contents($directory.'/database.list', "; PostgreSQL 17 archive\n1; TABLE public.example\n");
        $files = ['database.dump', 'database.list'];
        if ($encrypted) {
            $plain = file_get_contents($directory.'/database.dump');
            $header = "age-encryption.org/v1\n-> X25519 ".str_repeat('A', 43)."\n".str_repeat('A', 43)."\n--- ".str_repeat('A', 43)."\n";
            file_put_contents($directory.'/database.dump.age', $header.str_repeat('x', 16 + strlen($plain) + 16));
            file_put_contents($directory.'/manifest.json', json_encode([
                'schema' => 'soamco-postgres-age-v1', 'postgres_major' => 17,
                'pg_restore_list_verified' => true, 'decryption_verified_on_vps' => false,
                'plaintext_bytes' => strlen($plain), 'plaintext_sha256' => hash('sha256', $plain),
                'recipient_sha256' => str_repeat('a', 64),
            ]));
            unlink($directory.'/database.dump');
            $files = ['database.dump.age', 'database.list', 'manifest.json'];
        }
        file_put_contents($directory.'/SHA256SUMS', implode('', array_map(fn (string $file): string => hash_file('sha256', $directory.'/'.$file).'  '.$file."\n", $files)));
        if ($local) {
            file_put_contents($directory.'/.soamco-nas-receipt.json', json_encode([
                'schema' => 1,
                'source' => 'soamco-backup@100.73.11.75:/soamco',
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
    $this->makeFilesBackup = function (int $days = 0, bool $local = false): string {
        $name = 'files-encrypted-'.gmdate('Ymd\THis\Z', time() - $days * 86400).'-'.bin2hex(random_bytes(5));
        $directory = ($local ? $this->nasRoot.'/files' : $this->fixture.'/remote').'/'.$name;
        mkdir($directory, 0700, true);
        $header = "age-encryption.org/v1\n-> X25519 ".str_repeat('A', 43)."\n".str_repeat('A', 43)."\n--- ".str_repeat('A', 43)."\n";
        file_put_contents($directory.'/files.tar.gz.age', $header.str_repeat('x', 16 + 42 + 16));
        file_put_contents($directory.'/manifest.json', json_encode([
            'schema' => 'soamco-encrypted-files-v1', 'backup' => $name, 'cipher' => 'age-X25519',
            'archive_verified' => true, 'database_files_atomic' => false, 'decryption_verified_on_vps' => false,
            'plaintext_bytes' => 42, 'plaintext_sha256' => str_repeat('a', 64), 'recipient_sha256' => str_repeat('b', 64),
        ]));
        file_put_contents($directory.'/SHA256SUMS',
            hash_file('sha256', $directory.'/files.tar.gz.age')."  files.tar.gz.age\n".
            hash_file('sha256', $directory.'/manifest.json')."  manifest.json\n",
        );
        if ($local) {
            file_put_contents($directory.'/.soamco-nas-receipt.json', json_encode([
                'schema' => 1, 'source' => 'soamco-backup@100.73.11.75:/soamco',
                'backup' => $name, 'checksums_sha256' => hash_file('sha256', $directory.'/SHA256SUMS'),
            ]));
        }
        foreach (glob($directory.'/*') as $file) {
            chmod($file, 0600);
        }

        return $name;
    };
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
    $this->runSummary = function (bool $files, DateTimeImmutable $now): Process {
        $code = <<<'PY'
        import datetime as dt, pathlib, runpy, sys
        sys.path.insert(0, str(pathlib.Path(sys.argv[1]).parent))
        module = runpy.run_path(sys.argv[1])
        pull = module[sys.argv[2]]()
        try:
            print(pull.final_summary(dt.datetime.fromisoformat(sys.argv[3])))
        finally:
            pull.lock.close()
            pull.log_file.close()
        PY;
        $process = new Process(['python3', '-B', '-c', $code, dirname(__DIR__, 2).'/bin/pull-postgres-backups-nas', $files ? 'FilesPull' : 'Pull', $now->format('c')], env: [
            'SOAMCO_NAS_BACKUP_ROOT' => $this->nasRoot,
        ]);
        $process->mustRun();

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
    expect(array_filter($calls, fn (array $call): bool => str_starts_with($call['batch'], '@get ')))->toHaveCount(2);
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

it('pulls mixed encrypted and legacy backups without any age identity or plaintext conversion', function () {
    $encrypted = ($this->makeBackup)(0, false, true);
    $first = ($this->runPull)();
    expect($first->getExitCode())->toBe(0, $first->getErrorOutput())
        ->and(is_file($this->storage.'/'.$encrypted.'/database.dump.age'))->toBeTrue()
        ->and(is_file($this->storage.'/'.$encrypted.'/manifest.json'))->toBeTrue()
        ->and(file_exists($this->storage.'/'.$encrypted.'/database.dump'))->toBeFalse()
        ->and(is_file($this->storage.'/'.$this->remoteName.'/database.dump'))->toBeTrue();
    $before = file_get_contents($this->fixture.'/calls');
    expect(($this->runPull)()->getExitCode())->toBe(0);
    $after = file_get_contents($this->fixture.'/calls');
    expect(substr_count($after, '@get '))->toBe(substr_count($before, '@get '))
        ->and($after)->not->toContain('--decrypt', '--identity', '@rm ', '@put ')
        ->and(glob($this->storage.'/.pull-incomplete-*'))->toBe([]);
});

it('refuses a damaged encrypted transfer and does not publish its plaintext', function () {
    $name = ($this->makeBackup)(0, false, true);
    file_put_contents($this->fixture.'/remote/'.$name.'/database.dump.age', 'damaged');
    $process = ($this->runPull)();
    expect($process->isSuccessful())->toBeFalse()
        ->and(is_dir($this->storage.'/'.$name))->toBeFalse()
        ->and(glob($this->storage.'/.pull-incomplete-*'))->toBe([]);
});

it('preserves the last expired encrypted copy while applying retention to legacy copies', function () {
    (new Filesystem)->deleteDirectory($this->fixture.'/remote');
    mkdir($this->fixture.'/remote');
    $keeper = ($this->makeBackup)(20, true, true);
    $expired = ($this->makeBackup)(30, true);
    $process = ($this->runPull)();
    expect($process->getExitCode())->toBe(0, $process->getErrorOutput());
    clearstatcache();
    expect(is_file($this->storage.'/'.$keeper.'/database.dump.age'))->toBeTrue()
        ->and(is_dir($this->storage.'/'.$expired))->toBeFalse();
});

it('pulls encrypted files with the existing SFTP settings without decrypting or changing PostgreSQL copies', function () {
    $name = ($this->makeFilesBackup)();
    mkdir($this->fixture.'/remote/.files-incomplete-abc');
    $remote = $this->fixture.'/remote/'.$name;
    $directory = $this->nasRoot.'/files/'.$name;
    $process = ($this->runPull)();
    expect($process->getExitCode())->toBe(0, $process->getErrorOutput())
        ->and(hash_file('sha256', $directory.'/files.tar.gz.age'))->toBe(hash_file('sha256', $remote.'/files.tar.gz.age'))
        ->and(is_file($directory.'/manifest.json'))->toBeTrue()
        ->and(is_file($directory.'/.soamco-nas-receipt.json'))->toBeTrue()
        ->and(glob($this->nasRoot.'/files/.pull-incomplete-*'))->toBe([])
        ->and(fileperms($directory) & 0777)->toBe(0700)
        ->and(fileperms($directory.'/files.tar.gz.age') & 0777)->toBe(0600)
        ->and(file_get_contents($this->storage.'/'.$this->remoteName.'/database.dump'))->toBe('PGDMP-fixture');
    $before = file_get_contents($this->fixture.'/calls');
    $inode = fileinode($directory.'/files.tar.gz.age');
    expect(($this->runPull)()->getExitCode())->toBe(0)
        ->and(substr_count(file_get_contents($this->fixture.'/calls'), '@get '))->toBe(substr_count($before, '@get '))
        ->and(fileinode($directory.'/files.tar.gz.age'))->toBe($inode)
        ->and($before)->not->toContain('--decrypt', 'age ', '@rm ', '@put ', '@rename ', '.files-incomplete')
        ->and(file_get_contents($this->nasRoot.'/files/.pull.log'))->toContain('SUCCESS', 'SKIP');
});

it('rejects incomplete or invalid files backups and preserves the last NAS copy', function (string $kind) {
    $name = ($this->makeFilesBackup)();
    $keeper = ($this->makeFilesBackup)(20, true);
    $old = $this->nasRoot.'/files/'.$keeper.'/files.tar.gz.age';
    $hash = hash_file('sha256', $old);
    $remote = $this->fixture.'/remote/'.$name;
    $failure = '';
    if (in_array($kind, ['network', 'signal'], true)) {
        $failure = 'files-'.$kind;
    } elseif ($kind === 'missing') {
        unlink($remote.'/manifest.json');
    } elseif ($kind === 'checksum') {
        file_put_contents($remote.'/files.tar.gz.age', 'corrupted');
    } elseif ($kind === 'checksum-path') {
        file_put_contents($remote.'/SHA256SUMS', str_repeat('a', 64)."  ../../identity\n");
    } else {
        $manifest = json_decode(file_get_contents($remote.'/manifest.json'), true);
        if ($kind === 'backup-name') {
            $manifest['backup'] = 'files-encrypted-20000101T000000Z-abcdefghij';
        } elseif ($kind === 'schema') {
            $manifest['schema'] = 'unknown';
        } elseif ($kind === 'unverified') {
            $manifest['archive_verified'] = false;
        } else {
            file_put_contents($remote.'/files.tar.gz.age', 'not-age-ciphertext');
        }
        file_put_contents($remote.'/manifest.json', json_encode($manifest));
        file_put_contents($remote.'/SHA256SUMS',
            hash_file('sha256', $remote.'/files.tar.gz.age')."  files.tar.gz.age\n".
            hash_file('sha256', $remote.'/manifest.json')."  manifest.json\n",
        );
    }
    $process = ($this->runPull)($failure);
    expect($process->isSuccessful())->toBeFalse()
        ->and(is_dir($this->nasRoot.'/files/'.$name))->toBeFalse()
        ->and(hash_file('sha256', $old))->toBe($hash)
        ->and(glob($this->nasRoot.'/files/.pull-incomplete-*'))->toBe([])
        ->and(file_get_contents($this->nasRoot.'/files/.pull.log'))->toContain('ERROR')
        ->not->toContain('RETENTION', 'PRIVATE-SFTP-DIAGNOSTIC');
})->with(['network', 'signal', 'missing', 'checksum', 'checksum-path', 'backup-name', 'schema', 'unverified', 'ciphertext']);

it('retains ten days of files copies and never prunes unverified paths or PostgreSQL files', function () {
    ($this->makeFilesBackup)();
    $expired = ($this->makeFilesBackup)(11, true);
    $recent = ($this->makeFilesBackup)(9, true);
    $unmanaged = ($this->makeFilesBackup)(20, true);
    unlink($this->nasRoot.'/files/'.$unmanaged.'/.soamco-nas-receipt.json');
    $extra = ($this->makeFilesBackup)(20, true);
    file_put_contents($this->nasRoot.'/files/'.$extra.'/.keep', 'unmanaged');
    $process = ($this->runPull)();
    expect($process->getExitCode())->toBe(0, $process->getErrorOutput());
    clearstatcache();
    expect(is_dir($this->nasRoot.'/files/'.$expired))->toBeFalse()
        ->and(is_dir($this->nasRoot.'/files/'.$recent))->toBeTrue()
        ->and(is_dir($this->nasRoot.'/files/'.$unmanaged))->toBeTrue()
        ->and(file_get_contents($this->nasRoot.'/files/'.$extra.'/.keep'))->toBe('unmanaged')
        ->and(is_file($this->storage.'/'.$this->remoteName.'/database.dump'))->toBeTrue();
});

it('preserves the latest valid files backup even if every files copy is expired', function () {
    $keeper = ($this->makeFilesBackup)(20, true);
    $expired = ($this->makeFilesBackup)(30, true);
    $corrupt = ($this->makeFilesBackup)(15, true);
    file_put_contents($this->nasRoot.'/files/'.$corrupt.'/files.tar.gz.age', 'corrupt');
    $process = ($this->runPull)();
    expect($process->getExitCode())->toBe(0, $process->getErrorOutput());
    clearstatcache();
    expect(is_file($this->nasRoot.'/files/'.$keeper.'/files.tar.gz.age'))->toBeTrue()
        ->and(is_dir($this->nasRoot.'/files/'.$expired))->toBeFalse()
        ->and(is_dir($this->nasRoot.'/files/'.$corrupt))->toBeTrue();
});

it('refuses overlapping files pulls and never replaces a damaged files destination', function () {
    $name = ($this->makeFilesBackup)();
    expect(($this->runPull)()->getExitCode())->toBe(0);
    $storage = $this->nasRoot.'/files';
    $lock = fopen($storage.'/.pull.lock', 'a');
    flock($lock, LOCK_EX);
    try {
        $before = file_get_contents($this->fixture.'/calls');
        expect(($this->runPull)()->getExitCode())->toBe(75)
            ->and(substr_count(file_get_contents($this->fixture.'/calls'), '@get '))->toBe(substr_count($before, '@get '));
    } finally {
        fclose($lock);
    }
    file_put_contents($storage.'/'.$name.'/files.tar.gz.age', 'do not overwrite');
    expect(($this->runPull)()->isSuccessful())->toBeFalse()
        ->and(file_get_contents($storage.'/'.$name.'/files.tar.gz.age'))->toBe('do not overwrite');
});

it('uses only the SFTP chroot path and writes matching receipts for both backup collections', function () {
    $name = ($this->makeFilesBackup)();
    $process = ($this->runPull)();
    expect($process->getExitCode())->toBe(0, $process->getErrorOutput());
    foreach ([$this->storage.'/'.$this->remoteName, $this->nasRoot.'/files/'.$name] as $directory) {
        $receipt = json_decode(file_get_contents($directory.'/.soamco-nas-receipt.json'), true);
        expect($receipt['source'])->toBe('soamco-backup@100.73.11.75:/soamco')
            ->and($receipt['checksums_sha256'])->toBe(hash_file('sha256', $directory.'/SHA256SUMS'));
    }
    $calls = array_map(fn (string $line): array => json_decode($line, true), file($this->fixture.'/calls'));
    expect(implode("\n", array_column($calls, 'batch')))->toContain('@ls -1 "/soamco/"', '/soamco/'.$name, '/soamco/'.$this->remoteName)
        ->not->toContain('/var/backups/soamco');
});

it('accepts the exact legacy receipt source without downloading existing copies again', function () {
    $name = ($this->makeFilesBackup)();
    expect(($this->runPull)()->getExitCode())->toBe(0);
    foreach ([$this->storage.'/'.$this->remoteName, $this->nasRoot.'/files/'.$name] as $directory) {
        $path = $directory.'/.soamco-nas-receipt.json';
        $receipt = json_decode(file_get_contents($path), true);
        $receipt['source'] = 'soamco-backup@100.73.11.75:/var/backups/soamco';
        file_put_contents($path, json_encode($receipt));
    }
    $before = substr_count(file_get_contents($this->fixture.'/calls'), '@get ');
    $process = ($this->runPull)();
    expect($process->getExitCode())->toBe(0, $process->getErrorOutput())
        ->and(substr_count(file_get_contents($this->fixture.'/calls'), '@get '))->toBe($before);
});

it('rejects foreign or mismatched receipts after changing the SFTP root', function (bool $files, string $field, string $value) {
    $name = ($this->makeFilesBackup)();
    expect(($this->runPull)()->getExitCode())->toBe(0);
    $directory = $files ? $this->nasRoot.'/files/'.$name : $this->storage.'/'.$this->remoteName;
    $path = $directory.'/.soamco-nas-receipt.json';
    $receipt = json_decode(file_get_contents($path), true);
    $receipt['source'] = 'soamco-backup@100.73.11.75:/var/backups/soamco';
    $receipt[$field] = $value;
    file_put_contents($path, json_encode($receipt));
    $hash = hash_file('sha256', $directory.'/SHA256SUMS');
    expect(($this->runPull)()->isSuccessful())->toBeFalse()
        ->and(hash_file('sha256', $directory.'/SHA256SUMS'))->toBe($hash);
})->with([true, false])->with([
    ['source', 'soamco-backup@100.73.11.75:/soamco/other'],
    ['source', 'soamco-backup@100.73.11.76:/soamco'],
    ['checksums_sha256', 'invalid'],
    ['backup', 'another-backup'],
]);

it('reports the latest verified NAS backup in Italian time with exact freshness boundaries', function (bool $files, int $seconds, string $status) {
    $name = $files ? ($this->makeFilesBackup)(0, true) : ($this->makeBackup)(0, true);
    preg_match('/(\d{8}T\d{6}Z)/', $name, $match);
    $timestamp = DateTimeImmutable::createFromFormat('!Ymd\THis\Z', $match[1], new DateTimeZone('UTC'));
    $now = $timestamp->modify('+'.$seconds.' seconds');
    $process = ($this->runSummary)($files, $now);
    $local = $timestamp->setTimezone(new DateTimeZone('Europe/Rome'))->format('d/m/Y H:i:s T');
    expect($process->getOutput())->toBe(($files ? 'File/configurazioni' : 'PostgreSQL').': '.$status.' | ultimo backup verificato sul NAS: '.$local." (Europe/Rome)\n")
        ->and(file_exists($this->fixture.'/calls'))->toBeFalse();
})->with([
    [false, 0, 'OK'], [false, 7200, 'OK'], [false, 7201, 'NON AGGIORNATO'],
    [true, 0, 'OK'], [true, 172800, 'OK'], [true, 172801, 'NON AGGIORNATO'],
]);

it('reports missing when no local backup can actually be verified', function (bool $files, bool $corrupt) {
    if ($corrupt) {
        $name = $files ? ($this->makeFilesBackup)(0, true) : ($this->makeBackup)(0, true);
        $directory = ($files ? $this->nasRoot.'/files' : $this->storage).'/'.$name;
        unlink($directory.'/.soamco-nas-receipt.json');
    }
    $process = ($this->runSummary)($files, new DateTimeImmutable('now', new DateTimeZone('UTC')));
    expect($process->getOutput())->toContain(': MANCANTE | ultimo backup verificato sul NAS: nessuno')
        ->and(file_exists($this->fixture.'/calls'))->toBeFalse();
})->with([true, false])->with([true, false]);

it('ignores newer corrupted NAS copies and file modification times in the summary', function (bool $files) {
    $keeper = $files ? ($this->makeFilesBackup)(20, true) : ($this->makeBackup)(20, true);
    $invalid = $files ? ($this->makeFilesBackup)(0, true) : ($this->makeBackup)(0, true);
    $root = $files ? $this->nasRoot.'/files' : $this->storage;
    $artifact = $files ? 'files.tar.gz.age' : 'database.dump';
    file_put_contents($root.'/'.$invalid.'/'.$artifact, 'corrupt');
    touch($root.'/'.$keeper);
    preg_match('/(\d{8}T\d{6}Z)/', $keeper, $match);
    $timestamp = DateTimeImmutable::createFromFormat('!Ymd\THis\Z', $match[1], new DateTimeZone('UTC'));
    $local = $timestamp->setTimezone(new DateTimeZone('Europe/Rome'))->format('d/m/Y H:i:s T');
    $hash = hash_file('sha256', $root.'/'.$keeper.'/'.$artifact);
    $process = ($this->runSummary)($files, new DateTimeImmutable('now', new DateTimeZone('UTC')));
    expect($process->getOutput())->toContain(': NON AGGIORNATO', $local)
        ->and(hash_file('sha256', $root.'/'.$keeper.'/'.$artifact))->toBe($hash)
        ->and(is_dir($root.'/'.$invalid))->toBeTrue();
})->with([true, false]);

it('prints the final summary for both collections only after a normal pull', function () {
    ($this->makeFilesBackup)();
    $process = ($this->runPull)();
    expect($process->getExitCode())->toBe(0, $process->getErrorOutput())
        ->and($process->getErrorOutput())->toContain('Riepilogo NAS', 'PostgreSQL: OK', 'File/configurazioni: OK', '(Europe/Rome)');
    $check = ($this->runPull)('', ['--check']);
    expect($check->getExitCode())->toBe(0)
        ->and($check->getErrorOutput())->not->toContain('Riepilogo NAS');
});
