<?php

use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

beforeEach(function () {
    $this->fixture = realpath(sys_get_temp_dir()).'/soamco-files-test-'.bin2hex(random_bytes(8));
    mkdir($this->fixture, 0700);
    foreach (['commands', 'checkout', 'engine'] as $directory) {
        mkdir($this->fixture.'/'.$directory, 0700);
    }
    $this->sources = [];
    foreach (['public', 'private'] as $disk) {
        $root = $this->fixture.'/engine/volumes/project_soamco_budget_'.$disk.'_storage/_data';
        mkdir($root, 0700, true);
        $this->sources[$disk] = $root;
    }
    file_put_contents($this->sources['public'].'/avatar.png', 'synthetic public file');
    file_put_contents($this->sources['private'].'/firebase.json', '{"private_key":"DUMMY_SECRET"}');
    foreach (['backups/full', 'pending-reference-logos', 'custom-full', 'custom-users', 'cache', 'tmp'] as $directory) {
        mkdir($this->sources['private'].'/'.$directory, 0700, true);
        file_put_contents($this->sources['private'].'/'.$directory.'/excluded', 'not part of this backup');
    }
    mkdir($this->sources['public'].'/custom-full', 0700);
    file_put_contents($this->sources['public'].'/custom-full/excluded', 'also exclude public disk backups');
    foreach (['docker-compose.yml', 'docker-compose.production.yml', 'Caddyfile', '.env', '.env.docker'] as $file) {
        file_put_contents($this->fixture.'/checkout/'.$file, $file === '.env' ? 'APP_KEY=DUMMY_SECRET' : 'synthetic configuration');
        chmod($this->fixture.'/checkout/'.$file, 0600);
    }
    // Deliberately invalid public placeholder; the age process is simulated.
    file_put_contents($this->fixture.'/recipient', 'age1'.str_repeat('q', 58));
    chmod($this->fixture.'/recipient', 0600);
    $this->config = [
        'checkout' => $this->fixture.'/checkout',
        'runtime_env_file' => $this->fixture.'/checkout/.env',
        'compose_env_file' => $this->fixture.'/checkout/.env.docker',
        'recipient_file' => $this->fixture.'/recipient',
        'minimum_free_mib' => 1,
    ];
    file_put_contents($this->fixture.'/config.json', json_encode($this->config));
    chmod($this->fixture.'/config.json', 0600);

    $mock = <<<'PHP'
    <?php
    $root = getenv('FILES_BACKUP_FIXTURE');
    $failure = getenv('FILES_BACKUP_FAILURE');
    $tool = basename($argv[0]);
    $args = array_slice($argv, 1);
    file_put_contents($root.'/calls', json_encode([$tool, $args])."\n", FILE_APPEND);
    if ($tool === 'git') {
        echo str_repeat('a', 40);
    } elseif ($tool === 'age') {
        $output = $args[array_search('--output', $args, true) + 1];
        $input = end($args);
        copy($input, $root.'/synthetic-cleartext.tar.gz');
        if ($failure === 'signal') {
            posix_kill(posix_getppid(), SIGTERM);
            usleep(50000);
            exit(42);
        }
        if ($failure === 'age') {
            file_put_contents($output, 'incomplete');
            fwrite(STDERR, 'PRIVATE-DIAGNOSTICS-DUMMY_SECRET');
            exit(42);
        }
        if ($failure === 'plaintext') {
            copy($input, $output);
        } else {
            $header = "age-encryption.org/v1\n-> X25519 ".str_repeat('A', 43)."\n".str_repeat('A', 43)."\n--- ".str_repeat('A', 43)."\n";
            $bytes = filesize($input);
            $length = 16 + $bytes + 16 * max(1, (int) ceil($bytes / 65536));
            file_put_contents($output, $header.str_repeat('x', $failure === 'truncated' ? 5 : $length));
        }
    } elseif ($tool === 'docker') {
        if ($args[0] !== '--host' || $args[1] !== 'unix:///var/run/docker.sock') {
            exit(43);
        }
        $args = array_slice($args, 2);
        if ($args[0] === 'info') {
            echo json_encode(['DockerRootDir' => $root.'/engine']);
        } elseif ($args[0] === 'inspect') {
            $service = $args[1] === 'soamco-budget-app' ? 'app' : 'proxy';
            $mounts = [];
            if ($service === 'app') {
                foreach (['public', 'private'] as $disk) {
                    $mounts[] = ['Type' => 'volume', 'Name' => 'project_soamco_budget_'.$disk.'_storage', 'Destination' => '/var/www/html/storage/app/'.$disk];
                }
            } else {
                $mounts[] = ['Type' => 'bind', 'Source' => $root.'/checkout/Caddyfile', 'Destination' => '/etc/caddy/Caddyfile'];
            }
            echo json_encode([
                'Image' => 'sha256:'.str_repeat('b', 64),
                'Config' => ['Image' => 'soamco:synthetic-release', 'Labels' => ['com.docker.compose.service' => $service, 'com.docker.compose.project' => 'project'],
                    'Env' => ['APP_ENV=production', 'DB_PASSWORD=DUMMY_SECRET', 'AUTOMATION_FULL_BACKUP_DIRECTORY=custom-full', 'AUTOMATION_USER_BACKUP_DIRECTORY=custom-users']],
                'Mounts' => $mounts,
            ]);
        } elseif ($args[0] === 'volume' && $args[1] === 'inspect') {
            $name = $args[2];
            echo json_encode([
                'Mountpoint' => $root.'/engine/volumes/'.$name.'/_data',
                'Driver' => $failure === 'driver' ? 'nfs' : 'local', 'Options' => [],
                'Labels' => ['com.docker.compose.project' => 'project', 'com.docker.compose.volume' => $failure === 'database-volume' ? 'soamco_budget_postgres_data' : substr($name, 8)],
            ]);
        } else {
            exit(44);
        }
    }
    PHP;
    file_put_contents($this->fixture.'/commands/mock', '#!'.PHP_BINARY."\n".$mock);
    chmod($this->fixture.'/commands/mock', 0700);
    foreach (['docker', 'git', 'age'] as $name) {
        symlink('mock', $this->fixture.'/commands/'.$name);
    }
    $harness = <<<'PY'
    import os, pathlib, runpy, sys, types
    scope = runpy.run_path(os.environ['FILES_BACKUP_SCRIPT'])['main'].__globals__
    root = pathlib.Path(os.environ['FILES_BACKUP_FIXTURE'])
    failure = os.environ.get('FILES_BACKUP_FAILURE', '')
    scope['ROOT'] = root / 'output'
    scope['LOCK'] = root / 'deploy.lock'
    scope['root_owned'] = lambda info: failure != 'owner'
    os.geteuid = lambda: 1000 if failure == 'nonroot' else 0
    def chown(path, uid, gid):
        with (root / 'ownership').open('a') as stream:
            stream.write(str(path) + ' ' + str(uid) + ':' + str(gid) + '\n')
    os.chown = chown
    scope['grp'].getgrnam = lambda name: types.SimpleNamespace(gr_gid=os.getgid())
    scope['shutil'].disk_usage = lambda path: types.SimpleNamespace(free=0 if failure == 'space' else 64 * 1024 * 1024)
    if failure == 'archive':
        original = scope['verify_archive']
        def corrupt(path, manifest):
            data = path.read_bytes()
            path.write_bytes(data[:-8] + bytes([data[-8] ^ 1]) + data[-7:])
            original(path, manifest)
        scope['verify_archive'] = corrupt
    if failure == 'changed':
        original = scope['HashReader'].read
        def change(self, size):
            data = original(self, size)
            if not getattr(self, 'changed', False):
                self.changed = True
                with open(self.stream.name if isinstance(self.stream.name, str) else root / 'checkout/.env', 'ab') as stream:
                    stream.write(b'changed')
            return data
        scope['HashReader'].read = change
    if failure == 'publish':
        original_rename = pathlib.Path.rename
        def rename(self, target):
            if target.parent == root / 'output' and target.name.startswith('files-encrypted-'):
                raise OSError('publication unavailable')
            return original_rename(self, target)
        pathlib.Path.rename = rename
    if failure == 'checksum':
        original_managed = scope['managed']
        def damage(path):
            cipher = path / 'files.tar.gz.age'
            content = cipher.read_bytes()
            cipher.write_bytes(content[:-1] + bytes([content[-1] ^ 1]))
            return original_managed(path)
        scope['managed'] = damage
    if failure == 'fifo-race':
        original_archive = scope['make_archive']
        def replace_source(path, inventory, generated, details):
            source = inventory['storage/public/avatar.png']
            source.unlink()
            os.mkfifo(source)
            return original_archive(path, inventory, generated, details)
        scope['make_archive'] = replace_source
    sys.argv = [os.environ['FILES_BACKUP_SCRIPT'], '--config', str(root / 'config.json')]
    sys.exit(scope['main']())
    PY;
    file_put_contents($this->fixture.'/harness.py', $harness);
    $this->runBackup = function (string $failure = ''): Process {
        $process = new Process(['python3', '-B', $this->fixture.'/harness.py'], env: [
            'PATH' => $this->fixture.'/commands:'.getenv('PATH'),
            'FILES_BACKUP_SCRIPT' => dirname(__DIR__, 2).'/bin/backup-production-files',
            'FILES_BACKUP_FIXTURE' => $this->fixture,
            'FILES_BACKUP_FAILURE' => $failure,
        ]);
        $process->setTimeout(15);
        $process->run();

        return $process;
    };
    $this->pastBackup = function (int $days): string {
        $name = 'files-encrypted-'.gmdate('Ymd\THis\Z', time() - $days * 86400).'-'.bin2hex(random_bytes(5));
        $directory = $this->fixture.'/output/'.$name;
        mkdir($directory, 0750, true);
        $header = "age-encryption.org/v1\n-> X25519 ".str_repeat('A', 43)."\n".str_repeat('A', 43)."\n--- ".str_repeat('A', 43)."\n";
        file_put_contents($directory.'/files.tar.gz.age', $header.str_repeat('x', 16 + 10 + 16));
        file_put_contents($directory.'/manifest.json', json_encode(['schema' => 'soamco-encrypted-files-v1', 'backup' => $name, 'archive_verified' => true, 'database_files_atomic' => false, 'plaintext_bytes' => 10]));
        file_put_contents($directory.'/SHA256SUMS', hash_file('sha256', $directory.'/files.tar.gz.age')."  files.tar.gz.age\n".hash_file('sha256', $directory.'/manifest.json')."  manifest.json\n");
        foreach (glob($directory.'/*') as $file) {
            chmod($file, 0640);
        }

        return $directory;
    };
});

afterEach(function () {
    (new Filesystem)->deleteDirectory($this->fixture);
});

it('archives the real synthetic TAR with protected configuration and publishes only encrypted artifacts', function () {
    $process = ($this->runBackup)();
    expect($process->getExitCode())->toBe(0, $process->getErrorOutput());
    $published = glob($this->fixture.'/output/files-encrypted-*');
    expect($published)->toHaveCount(1)
        ->and(glob($this->fixture.'/output/.files-incomplete-*'))->toBe([]);
    $directory = $published[0];
    expect(array_map('basename', glob($directory.'/*')))->toBe(['SHA256SUMS', 'files.tar.gz.age', 'manifest.json'])
        ->and(fileperms($directory) & 0777)->toBe(0750);
    foreach (glob($directory.'/*') as $file) {
        expect(fileperms($file) & 0777)->toBe(0640)
            ->and(file_get_contents($file))->not->toContain('DUMMY_SECRET');
    }
    $readTar = new Process(['python3', '-B', '-c', 'import json,sys,tarfile; t=tarfile.open(sys.argv[1]); print(json.dumps({"names":t.getnames(),"manifest":json.load(t.extractfile("manifest.json")),"env":t.extractfile("configuration/runtime.env").read().decode()}))', $this->fixture.'/synthetic-cleartext.tar.gz']);
    $readTar->mustRun();
    $archive = json_decode($readTar->getOutput(), true);
    expect($archive['names'])->toContain('storage/public/avatar.png', 'storage/private/firebase.json', 'configuration/runtime.env', 'configuration/compose.env', 'configuration/live-runtime-environment.json', 'configuration/Caddyfile')
        ->and(implode(' ', $archive['names']))->not->toContain('backups/', 'pending-reference-logos', 'custom-full', 'custom-users', 'cache/', 'tmp/')
        ->and($archive['env'])->toBe('APP_KEY=DUMMY_SECRET')
        ->and($archive['manifest']['database_files_atomic'])->toBeFalse()
        ->and($archive['manifest']['files']['storage/public/avatar.png']['sha256'])->toBe(hash('sha256', 'synthetic public file'));
    $record = json_decode(file_get_contents($directory.'/manifest.json'), true);
    expect($record['decryption_verified_on_vps'])->toBeFalse()
        ->and(file_get_contents($directory.'/SHA256SUMS'))->toContain(hash_file('sha256', $directory.'/files.tar.gz.age'))
        ->and(file_get_contents($this->fixture.'/calls'))->toContain('--encrypt', '--recipient')
        ->not->toContain('--identity', '--decrypt', 'keygen', '"exec"', '"run"', '"stop"', '"pause"')
        ->and($process->getErrorOutput())->toContain('NOT atomic with PostgreSQL', 'SUCCESS')
        ->not->toContain('DUMMY_SECRET')
        ->and(file_get_contents($this->fixture.'/ownership'))->toContain(' 0:');
});

it('preserves the last encrypted backup and removes temporaries on failure', function (string $failure) {
    $old = ($this->pastBackup)(20);
    $before = hash_file('sha256', $old.'/files.tar.gz.age');
    $process = ($this->runBackup)($failure);
    expect($process->isSuccessful())->toBeFalse();
    clearstatcache();
    expect(glob($this->fixture.'/output/files-encrypted-*'))->toBe([$old])
        ->and(hash_file('sha256', $old.'/files.tar.gz.age'))->toBe($before)
        ->and(glob($this->fixture.'/output/.files-incomplete-*'))->toBe([])
        ->and($process->getErrorOutput())->toContain('ERROR')
        ->not->toContain('RETENTION', 'DUMMY_SECRET', 'PRIVATE-DIAGNOSTICS');
    if (in_array($failure, ['space', 'driver', 'database-volume', 'archive', 'changed', 'fifo-race'], true)) {
        expect(file_get_contents($this->fixture.'/calls'))->not->toContain('["age"');
    }
})->with(['age', 'plaintext', 'truncated', 'signal', 'space', 'driver', 'database-volume', 'archive', 'changed', 'publish', 'checksum', 'fifo-race']);

it('retains only managed encrypted archives and leaves PostgreSQL and unexpected data untouched', function () {
    $old = ($this->pastBackup)(11);
    $recent = ($this->pastBackup)(9);
    $unmanaged = ($this->pastBackup)(20);
    file_put_contents($unmanaged.'/.keep', 'unmanaged');
    mkdir($this->fixture.'/output/postgres-20200101T000000Z-abcdefghij');
    file_put_contents($this->fixture.'/output/postgres-20200101T000000Z-abcdefghij/database.dump', 'must not touch');
    $process = ($this->runBackup)();
    expect($process->getExitCode())->toBe(0, $process->getErrorOutput());
    clearstatcache();
    expect(is_dir($old))->toBeFalse()
        ->and(is_dir($recent))->toBeTrue()
        ->and(file_get_contents($unmanaged.'/.keep'))->toBe('unmanaged')
        ->and(file_get_contents($this->fixture.'/output/postgres-20200101T000000Z-abcdefghij/database.dump'))->toBe('must not touch');
});

it('refuses concurrent backup or deployment before inspecting sources', function () {
    $lock = fopen($this->fixture.'/deploy.lock', 'w');
    flock($lock, LOCK_EX);
    try {
        $process = ($this->runBackup)();
        expect($process->getExitCode())->toBe(75)
            ->and(file_exists($this->fixture.'/calls'))->toBeFalse();
    } finally {
        fclose($lock);
    }
});

it('refuses private identities unsafe configuration and symlink sources', function (string $kind) {
    if ($kind === 'private-key') {
        file_put_contents($this->fixture.'/recipient', 'AGE-SECRET-KEY-invalid-placeholder');
    } elseif ($kind === 'permissions') {
        chmod($this->fixture.'/config.json', 0660);
    } elseif ($kind === 'symlink') {
        symlink($this->fixture.'/checkout', $this->sources['public'].'/linked');
    } elseif ($kind === 'ancestors') {
        chmod($this->fixture, 0777);
    }
    $process = ($this->runBackup)(in_array($kind, ['owner', 'nonroot'], true) ? $kind : '');
    expect($process->isSuccessful())->toBeFalse()
        ->and(glob($this->fixture.'/output/files-encrypted-*'))->toBe([])
        ->and(glob($this->fixture.'/output/.files-incomplete-*'))->toBe([]);
})->with(['private-key', 'permissions', 'symlink', 'ancestors', 'owner', 'nonroot']);

it('does not follow symlinks or remove corrupted encrypted archives during retention', function (string $kind) {
    $old = ($this->pastBackup)(20);
    if ($kind === 'symlink') {
        rename($old, $this->fixture.'/protected');
        symlink($this->fixture.'/protected', $old);
    } else {
        file_put_contents($old.'/files.tar.gz.age', 'corrupt');
    }
    $process = ($this->runBackup)();
    expect($process->getExitCode())->toBe(0, $process->getErrorOutput());
    clearstatcache();
    expect(file_exists($old))->toBeTrue()
        ->and($process->getErrorOutput())->toContain('retention skipped unverified encrypted backup');
    if ($kind === 'symlink') {
        expect(is_file($this->fixture.'/protected/files.tar.gz.age'))->toBeTrue();
    }
})->with(['symlink', 'corrupt']);

it('provides a separate inactive daily timer and configuration without keys or PostgreSQL changes', function () {
    $root = dirname(__DIR__, 2);
    $example = json_decode(file_get_contents($root.'/docker/soamco-files-backup.json.example'), true);
    expect($example['recipient_file'])->toBe('/etc/soamco-backup/files.recipient')
        ->and(file_get_contents($root.'/docker/soamco-files-backup.timer'))->toContain('OnCalendar=*-*-* 03:30:00 UTC', 'Persistent=false')
        ->and(file_get_contents($root.'/docker/soamco-files-backup.service'))->toContain('User=root', 'StandardError=journal')
        ->and(file_get_contents($root.'/bin/backup-production-files'))->toContain('/tmp/soamco-production-deploy.lock')
        ->not->toContain('pg_dump', 'age-keygen');
});
