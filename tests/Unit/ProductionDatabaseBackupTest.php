<?php

use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

beforeEach(function () {
    $this->fixture = sys_get_temp_dir().'/soamco-pg-backup-'.bin2hex(random_bytes(8));
    $this->backupTimestamp = time();
    mkdir($this->fixture, 0700);
    mkdir($this->fixture.'/commands', 0700);
    $source = file_get_contents(dirname(__DIR__, 2).'/bin/backup-production-db');
    file_put_contents($this->fixture.'/backup', str_replace(
        ['/var/backups/soamco', '/tmp/soamco-production-deploy.lock'],
        [$this->fixture.'/output', $this->fixture.'/deploy.lock'],
        $source,
    ));

    $mock = <<<'PHP'
    <?php
    $name = basename($_SERVER['argv'][0]);
    $args = array_slice($_SERVER['argv'], 1);
    $failure = getenv('BACKUP_FAILURE');
    file_put_contents(getenv('BACKUP_FIXTURE').'/calls', $name.' '.implode(' ', $args)."\n", FILE_APPEND);
    $fail = function (): never {
        fwrite(STDERR, 'PRIVATE-DIAGNOSTIC-DO-NOT-LOG');
        exit(42);
    };
    switch ($name) {
        case 'id':
            echo $failure === 'nonroot' ? '1000' : '0';
            break;
        case 'getent':
            exit($failure === 'group' ? 2 : 0);
        case 'logger':
        case 'chown':
            break;
        case 'stat':
            if (in_array('%u', $args, true)) {
                echo $failure === 'owner' ? '1000' : '0';
            } elseif (in_array('%a', $args, true)) {
                echo decoct(fileperms(end($args)) & 0777);
            } elseif (in_array('%h', $args, true)) {
                echo stat(end($args))['nlink'];
            } else {
                echo $failure === 'inode' && str_starts_with(end($args), '/proc/') ? '1:3' : '1:2';
            }
            break;
        case 'flock':
            if ($failure === 'lock') {
                exit(1);
            }
            $lock = fopen('php://fd/9', 'r');
            exit(flock($lock, LOCK_EX | LOCK_NB) ? 0 : 1);
        case 'install':
            if (! is_dir(end($args))) {
                mkdir(end($args), 0750, true);
            }
            chmod(end($args), 0750);
            break;
        case 'df':
            if ($failure === 'df') {
                $fail();
            }
            $available = getenv('BACKUP_AVAILABLE_BYTES');
            echo "Avail\n".($available === false ? '3221225472' : $available)."\n";
            break;
        case 'date':
            $format = end($args);
            $now = (int) getenv('BACKUP_NOW');
            if (in_array('-d', $args, true)) {
                $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $args[2], new DateTimeZone('UTC'));
                $errors = DateTimeImmutable::getLastErrors();
                if (! $date || ($errors !== false && ($errors['warning_count'] || $errors['error_count']))) {
                    exit(1);
                }
                echo $date->getTimestamp();
            } else {
                echo match ($format) {
                    '+%s' => $now,
                    '+%Y%m%dT%H%M%SZ' => gmdate('Ymd\THis\Z', $now),
                    default => gmdate('Y-m-d\TH:i:s\Z', $now),
                };
            }
            break;
        case 'mv':
            if ($failure === 'publish') {
                $fail();
            }
            $from = $args[count($args) - 2];
            $to = end($args);
            if (file_exists($to) || ! rename($from, $to)) {
                $fail();
            }
            break;
        case 'sha256sum':
            if ($failure === 'checksum') {
                $fail();
            }
            if (in_array('--check', $args, true)) {
                $counter = getenv('BACKUP_FIXTURE').'/checksum-count';
                $count = is_file($counter) ? (int) file_get_contents($counter) + 1 : 1;
                file_put_contents($counter, (string) $count);
                if ($failure === 'keeper-checksum' && $count === 2) {
                    $fail();
                }
                foreach (file(end($args), FILE_IGNORE_NEW_LINES) as $line) {
                    [$hash, $file] = explode('  ', $line, 2);
                    if (hash_file('sha256', $file) !== $hash) {
                        $fail();
                    }
                }
            } else {
                foreach ($args as $file) {
                    echo hash_file('sha256', $file).'  '.$file."\n";
                }
            }
            break;
        case 'docker':
            $command = implode(' ', $args);
            if ($args[0] === 'inspect') {
                if (str_contains($command, '{{.Id}}')) {
                    echo str_repeat('a', 64);
                } elseif (str_contains($command, '.State.Running')) {
                    echo $failure === 'stopped' ? 'false' : 'true';
                } else {
                    echo $failure === 'service' ? 'umami-db' : 'db';
                }
            } elseif (str_contains($command, 'SHOW server_version_num')) {
                echo $failure === 'version' ? '180001' : '170006';
            } elseif (str_contains($command, 'pg_database_size')) {
                echo $failure === 'database-size' ? 'unknown' : '1048576';
            } elseif (str_contains($command, '--version')) {
                echo str_contains($command, 'pg_dump') ? 'pg_dump (PostgreSQL) 17.6' : 'pg_restore (PostgreSQL) 17.6';
            } elseif (str_contains($command, 'exec pg_dump -Fc')) {
                if ($failure === 'signal') {
                    posix_kill(posix_getppid(), SIGTERM);
                }
                echo $failure === 'empty' ? '' : 'PGDMP-fixture';
                if (in_array($failure, ['dump', 'full'], true)) {
                    $fail();
                }
            } elseif (str_contains($command, 'pg_restore --list')) {
                $counter = getenv('BACKUP_FIXTURE').'/restore-count';
                $count = is_file($counter) ? (int) file_get_contents($counter) + 1 : 1;
                file_put_contents($counter, (string) $count);
                if ($failure === 'keeper-list' && $count === 2) {
                    $fail();
                }
                if ($failure === 'list' || stream_get_contents(STDIN) !== 'PGDMP-fixture') {
                    $fail();
                }
                echo "; Archive fixture\n1; table public.example\n";
            } else {
                $fail();
            }
            break;
        default:
            $fail();
    }
    PHP;
    file_put_contents($this->fixture.'/commands/mock', '#!'.PHP_BINARY."\n".$mock);
    chmod($this->fixture.'/commands/mock', 0700);
    foreach (['docker', 'flock', 'getent', 'stat', 'sha256sum', 'mv', 'logger', 'chown', 'install', 'id', 'df', 'date'] as $command) {
        symlink('mock', $this->fixture.'/commands/'.$command);
    }
    $this->backupEnvironment = [];
    $this->runBackup = function (string $failure = ''): Process {
        $process = new Process(['bash', $this->fixture.'/backup'], env: [
            'PATH' => $this->fixture.'/commands:'.getenv('PATH'),
            'BACKUP_FIXTURE' => $this->fixture,
            'BACKUP_FAILURE' => $failure,
            'SOAMCO_BACKUP_MIN_FREE_MIB' => '1024',
            'BACKUP_NOW' => (string) $this->backupTimestamp,
            ...$this->backupEnvironment,
        ]);
        $process->setTimeout(10);
        $process->run();

        return $process;
    };
    $this->createBackup = function (int $ageDays): string {
        $directory = $this->fixture.'/output/postgres-'.gmdate('Ymd\THis\Z', $this->backupTimestamp - $ageDays * 86400).'-'.bin2hex(random_bytes(5));
        mkdir($directory, 0750, true);
        file_put_contents($directory.'/database.dump', 'PGDMP-fixture');
        file_put_contents($directory.'/database.list', "; Archive fixture\n1; table public.example\n");
        file_put_contents($directory.'/SHA256SUMS',
            hash_file('sha256', $directory.'/database.dump')."  database.dump\n".
            hash_file('sha256', $directory.'/database.list')."  database.list\n",
        );
        foreach (glob($directory.'/*') as $file) {
            chmod($file, 0640);
        }

        return $directory;
    };
});

afterEach(function () {
    (new Filesystem)->deleteDirectory($this->fixture);
});

it('publishes only a verified custom archive and checksums through one directory rename', function () {
    $process = ($this->runBackup)();
    expect($process->getExitCode())->toBe(0, $process->getErrorOutput());
    $directories = glob($this->fixture.'/output/postgres-*');
    expect($directories)->toHaveCount(1)
        ->and(glob($this->fixture.'/output/.postgres-incomplete.*'))->toBe([]);
    $directory = $directories[0];
    expect(fileperms($directory) & 0777)->toBe(0750);
    foreach (['database.dump', 'database.list', 'SHA256SUMS'] as $file) {
        expect(fileperms($directory.'/'.$file) & 0777)->toBe(0640);
    }
    expect(file_get_contents($directory.'/SHA256SUMS'))->toBe(
        hash_file('sha256', $directory.'/database.dump')."  database.dump\n".
        hash_file('sha256', $directory.'/database.list')."  database.list\n",
    );
    $calls = file_get_contents($this->fixture.'/calls');
    expect(strpos($calls, 'exec pg_dump -Fc'))->toBeLessThan(strpos($calls, 'pg_restore --list'))
        ->and(strpos($calls, 'sha256sum --check --status'))->toBeLessThan(strpos($calls, 'mv -T'))
        ->and($calls)->toContain('chown root:soamco-backup', 'flock -n 9', '--user postgres')
        ->not->toContain('artisan', 'compose', 'docker stop', 'docker pause', 'POSTGRES_PASSWORD')
        ->and($process->getErrorOutput())->toContain('START', 'SUCCESS');
});

it('never publishes partial output and cleans staging after a failure', function (string $failure) {
    $process = ($this->runBackup)($failure);
    expect($process->isSuccessful())->toBeFalse($failure.' '.$process->getErrorOutput())
        ->and(glob($this->fixture.'/output/postgres-*'))->toBe([])
        ->and(glob($this->fixture.'/output/.postgres-incomplete.*'))->toBe([])
        ->and($process->getErrorOutput())->toContain('ERROR')
        ->not->toContain('SUCCESS', 'PRIVATE-DIAGNOSTIC-DO-NOT-LOG');
    if (in_array($failure, ['lock', 'nonroot', 'group', 'inode'], true)) {
        expect(file_get_contents($this->fixture.'/calls'))->not->toContain('docker ');
    }
})->with(['dump', 'empty', 'list', 'checksum', 'publish', 'stopped', 'service', 'version', 'lock', 'nonroot', 'group', 'inode', 'df', 'database-size', 'full']);

it('cleans incomplete output on termination without stopping application services', function () {
    $process = ($this->runBackup)('signal');
    expect($process->getExitCode())->toBe(143)
        ->and(glob($this->fixture.'/output/postgres-*'))->toBe([])
        ->and(glob($this->fixture.'/output/.postgres-incomplete.*'))->toBe([]);
});

it('cannot start while another process holds the existing deployment lock', function () {
    $lock = fopen($this->fixture.'/deploy.lock', 'w');
    flock($lock, LOCK_EX);
    try {
        $process = ($this->runBackup)();
        expect($process->getExitCode())->toBe(75)
            ->and(file_get_contents($this->fixture.'/calls'))->not->toContain('docker ');
    } finally {
        fclose($lock);
    }
});

it('rejects unsafe existing destinations and lock symlinks', function (string $kind) {
    if ($kind === 'lock-symlink') {
        file_put_contents($this->fixture.'/target', 'unchanged');
        symlink($this->fixture.'/target', $this->fixture.'/deploy.lock');
    } elseif ($kind === 'directory-symlink') {
        symlink($this->fixture.'/commands', $this->fixture.'/output');
    } else {
        mkdir($this->fixture.'/output', 0700);
        chmod($this->fixture.'/output', $kind === 'permissions' ? 0770 : 0700);
    }
    $process = ($this->runBackup)($kind === 'owner' ? 'owner' : '');
    expect($process->isSuccessful())->toBeFalse()
        ->and(file_get_contents($this->fixture.'/calls'))->not->toContain('docker ');
    if ($kind === 'lock-symlink') {
        expect(file_get_contents($this->fixture.'/target'))->toBe('unchanged');
    }
})->with(['permissions', 'owner', 'lock-symlink', 'directory-symlink']);

it('keeps the deployment lock unchanged and schedules hourly in UTC without activating anything', function () {
    file_put_contents($this->fixture.'/deploy.lock', 'existing deployment lock');
    chmod($this->fixture.'/deploy.lock', 0600);
    $inode = fileinode($this->fixture.'/deploy.lock');
    expect(($this->runBackup)()->getExitCode())->toBe(0)
        ->and(fileinode($this->fixture.'/deploy.lock'))->toBe($inode)
        ->and(file_get_contents($this->fixture.'/deploy.lock'))->toBe('existing deployment lock')
        ->and(fileperms($this->fixture.'/deploy.lock') & 0777)->toBe(0600);
    $root = dirname(__DIR__, 2);
    expect(file_get_contents($root.'/bin/backup-production-db'))->toContain('lock_file=/tmp/soamco-production-deploy.lock')
        ->and(file_get_contents($root.'/docker/soamco-postgres-backup.timer'))->toContain('OnCalendar=*-*-* *:00:00 UTC', 'Persistent=false')
        ->and(file_get_contents($root.'/docker/soamco-postgres-backup.service'))->toContain('User=root', 'ExecStart=/usr/local/sbin/soamco-backup-postgres', 'StandardError=journal');
});

it('prunes only managed backups older than ten days after verifying the published replacement', function () {
    $expired = ($this->createBackup)(11);
    $boundary = ($this->createBackup)(10);
    $recent = ($this->createBackup)(9);
    touch($expired, $this->backupTimestamp);
    touch($recent, $this->backupTimestamp - 30 * 86400);
    mkdir($this->fixture.'/output/application-backups', 0750);
    file_put_contents($this->fixture.'/output/application-backups/user-backup.zip', 'keep');

    $process = ($this->runBackup)();
    expect($process->getExitCode())->toBe(0, $process->getErrorOutput());
    clearstatcache();
    expect(file_exists($expired))->toBeFalse()
        ->and(is_dir($boundary))->toBeTrue()
        ->and(is_dir($recent))->toBeTrue()
        ->and(file_get_contents($this->fixture.'/output/application-backups/user-backup.zip'))->toBe('keep')
        ->and(glob($this->fixture.'/output/postgres-*'))->toHaveCount(3)
        ->and($process->getErrorOutput())->toContain('days=10 removed=1');
    $calls = file_get_contents($this->fixture.'/calls');
    expect(strpos($calls, 'pg_database_size'))->toBeLessThan(strpos($calls, 'exec pg_dump -Fc'))
        ->and(strpos($calls, 'mv -T'))->toBeLessThan(strpos($calls, 'date -u -d'));
});

it('preserves the last valid backup even when expired if a new backup cannot complete', function (string $failure) {
    $old = ($this->createBackup)(20);
    $hash = hash_file('sha256', $old.'/database.dump');
    if ($failure === 'space') {
        $this->backupEnvironment['BACKUP_AVAILABLE_BYTES'] = '100';
    }
    $process = ($this->runBackup)($failure === 'space' ? '' : $failure);
    expect($process->isSuccessful())->toBeFalse();
    clearstatcache();
    expect(hash_file('sha256', $old.'/database.dump'))->toBe($hash)
        ->and(glob($this->fixture.'/output/postgres-*'))->toBe([$old])
        ->and(glob($this->fixture.'/output/.postgres-incomplete.*'))->toBe([])
        ->and($process->getErrorOutput())->not->toContain('RETENTION removed');
    if ($failure === 'space') {
        expect(file_get_contents($this->fixture.'/calls'))->not->toContain('exec pg_dump -Fc')
            ->and($process->getErrorOutput())->toContain('insufficient disk space');
    }
})->with(['space', 'dump', 'full', 'list', 'publish', 'signal']);

it('refuses retention if the published keeper cannot be reverified', function (string $failure) {
    $old = ($this->createBackup)(20);
    $process = ($this->runBackup)($failure);
    expect($process->isSuccessful())->toBeFalse();
    clearstatcache();
    expect(is_dir($old))->toBeTrue()
        ->and(glob($this->fixture.'/output/postgres-*'))->toHaveCount(2)
        ->and($process->getErrorOutput())->toContain('completed backup preserved')
        ->not->toContain('RETENTION removed');
})->with(['keeper-checksum', 'keeper-list']);

it('retains suspicious paths instead of deleting unexpected files', function (string $kind) {
    $old = ($this->createBackup)(20);
    switch ($kind) {
        case 'symlink':
            $target = $this->fixture.'/protected';
            rename($old, $target);
            symlink($target, $old);
            break;
        case 'file-symlink':
            rename($old.'/database.dump', $this->fixture.'/protected');
            symlink($this->fixture.'/protected', $old.'/database.dump');
            break;
        case 'extra-file':
            file_put_contents($old.'/.keep', 'unmanaged data');
            break;
        case 'nested-directory':
            mkdir($old.'/attachments');
            file_put_contents($old.'/attachments/file', 'unmanaged data');
            break;
        case 'corrupt':
            file_put_contents($old.'/database.dump', 'corrupted');
            break;
        case 'checksum-path':
            file_put_contents($this->fixture.'/protected', 'must not be read or removed');
            file_put_contents($old.'/SHA256SUMS', hash_file('sha256', $this->fixture.'/protected')."  ../../protected\n");
            break;
        case 'hardlink':
            link($old.'/database.dump', $this->fixture.'/protected');
            break;
        case 'writable':
            chmod($old, 0770);
            break;
        case 'name':
            rename($old, $old.'-unmanaged');
            $old .= '-unmanaged';
            break;
        case 'invalid-date':
            $other = $this->fixture.'/output/postgres-20000230T000000Z-abcdefghij';
            rename($old, $other);
            $old = $other;
            break;
    }
    $process = ($this->runBackup)();
    expect($process->getExitCode())->toBe(0, $process->getErrorOutput());
    clearstatcache();
    expect(file_exists($old))->toBeTrue()
        ->and($process->getErrorOutput())->not->toContain('RETENTION removed');
    if (file_exists($this->fixture.'/protected')) {
        expect(filesize($this->fixture.'/protected'))->toBeGreaterThan(0);
    }
})->with(['symlink', 'file-symlink', 'extra-file', 'nested-directory', 'corrupt', 'checksum-path', 'hardlink', 'writable', 'name', 'invalid-date']);

it('requires the configured reserve plus twice the database size before starting the dump', function (string $available, bool $succeeds) {
    $this->backupEnvironment = ['SOAMCO_BACKUP_MIN_FREE_MIB' => '1', 'BACKUP_AVAILABLE_BYTES' => $available];
    $process = ($this->runBackup)();
    expect($process->isSuccessful())->toBe($succeeds)
        ->and($process->getErrorOutput())->toContain('required_bytes=3145728 reserve_mib=1');
    if (! $succeeds) {
        expect(file_get_contents($this->fixture.'/calls'))->not->toContain('exec pg_dump -Fc');
    }
})->with([['3145728', true], ['3145727', false], ['0', false]]);

it('fails closed for invalid disk-space configuration or unreadable free-space output', function (string $reserve, string $available) {
    $this->backupEnvironment = ['SOAMCO_BACKUP_MIN_FREE_MIB' => $reserve, 'BACKUP_AVAILABLE_BYTES' => $available];
    $process = ($this->runBackup)();
    expect($process->isSuccessful())->toBeFalse()
        ->and(file_get_contents($this->fixture.'/calls'))->not->toContain('exec pg_dump -Fc')
        ->and(glob($this->fixture.'/output/.postgres-incomplete.*'))->toBe([]);
})->with([['0', '3221225472'], ['-1', '3221225472'], ['oops', '3221225472'], ['9999999999', '3221225472'], ['1', 'unknown'], ['1', '0008']]);
