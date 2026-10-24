<?php

use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

beforeEach(function () {
    $this->fixture = sys_get_temp_dir().'/soamco-restore-test-'.bin2hex(random_bytes(8));
    $this->backup = $this->fixture.'/postgres-20261024T030000Z-abcdefghij';
    mkdir($this->backup, 0700, true);
    mkdir($this->fixture.'/commands', 0700);
    $this->toc = "; Archive created at 2026-10-24\n; Format: CUSTOM\n; Dumped from database version: 17.6\n; Dumped by pg_dump version: 17.6\n; Selected TOC Entries:\n1; 1259 123 TABLE public.restore_parent owner\n";
    file_put_contents($this->backup.'/database.dump', 'PGDMP-synthetic-fixture');
    file_put_contents($this->backup.'/database.list', $this->toc);
    $this->updateManifest = function (): void {
        file_put_contents($this->backup.'/SHA256SUMS',
            hash_file('sha256', $this->backup.'/database.dump')."  database.dump\n".
            hash_file('sha256', $this->backup.'/database.list')."  database.list\n",
        );
        foreach (glob($this->backup.'/*') as $file) {
            chmod($file, 0600);
        }
    };
    ($this->updateManifest)();
    file_put_contents($this->fixture.'/toc', $this->toc);

    $mock = <<<'PHP'
    <?php
    $root = getenv('RESTORE_FIXTURE');
    $failure = getenv('RESTORE_FAILURE');
    $args = array_slice($argv, 1);
    file_put_contents($root.'/calls', json_encode(['args' => $args, 'docker_host' => getenv('DOCKER_HOST'), 'pg_host' => getenv('PGHOST')])."\n", FILE_APPEND);
    if (($args[0] ?? null) === '--host') {
        if ($args[1] !== 'unix:///var/run/docker.sock') {
            exit(90);
        }
        $args = array_slice($args, 2);
    }
    $cid = str_repeat('a', 64);
    $image = 'sha256:'.str_repeat('b', 64);
    $fail = function (): never {
        fwrite(STDERR, 'PRIVATE-SQL-OR-CREDENTIALS');
        exit(42);
    };
    if ($args[0] === 'context') {
        echo json_encode(['Host' => $failure === 'endpoint' ? 'tcp://production:2375' : 'unix:///var/run/docker.sock']);
    } elseif ($args[0] === 'image') {
        if ($failure === 'image') {
            $fail();
        }
        echo $image;
    } elseif ($args[0] === 'create') {
        $name = $args[array_search('--name', $args, true) + 1];
        $label = $args[array_search('--label', $args, true) + 1];
        [$key, $token] = explode('=', $label, 2);
        $data = [
            'Id' => $cid, 'Name' => '/'.$name,
            'Config' => ['Image' => $image, 'User' => 'postgres', 'Labels' => [$key => $token]],
            'HostConfig' => [
                'NetworkMode' => 'none', 'PortBindings' => [], 'PublishAllPorts' => false,
                'Binds' => [], 'Links' => [], 'Devices' => [], 'Privileged' => false,
                'ReadonlyRootfs' => true, 'CapDrop' => ['ALL'],
                'SecurityOpt' => ['no-new-privileges:true'], 'PidMode' => '', 'IpcMode' => 'private',
                'Tmpfs' => ['/tmp' => 'rw', '/var/run/postgresql' => 'rw'],
            ],
            'NetworkSettings' => ['Networks' => ['none' => []], 'Ports' => []],
            'Mounts' => [['Type' => 'volume', 'Name' => str_repeat('c', 64), 'Destination' => '/var/lib/postgresql/data', 'RW' => true]],
        ];
        match ($failure) {
            'label' => $data['Config']['Labels'][$key] = 'not-owned',
            'container-id' => $data['Id'] = str_repeat('d', 64),
            'network' => $data['HostConfig']['NetworkMode'] = 'soamco-budget',
            'extra-network' => $data['NetworkSettings']['Networks']['soamco-budget'] = [],
            'ports' => $data['HostConfig']['PortBindings'] = ['5432/tcp' => [['HostPort' => '5432']]],
            'privileged' => $data['HostConfig']['Privileged'] = true,
            'volume' => $data['Mounts'][0]['Name'] = 'soamco_budget_postgres_data',
            'bind' => $data['Mounts'][0]['Type'] = 'bind',
            default => null,
        };
        file_put_contents($root.'/container.json', json_encode($data));
        echo $cid;
    } elseif ($args[0] === 'inspect') {
        if (! is_file($root.'/container.json')) {
            exit(1);
        }
        echo file_get_contents($root.'/container.json');
    } elseif ($args[0] === 'start') {
        if (end($args) !== $cid) {
            exit(91);
        }
    } elseif ($args[0] === 'rm') {
        if (end($args) !== $cid) {
            exit(92);
        }
        if ($failure === 'cleanup') {
            $fail();
        }
        unlink($root.'/container.json');
    } elseif ($args[0] === 'exec') {
        $index = array_search($cid, $args, true);
        if ($index === false) {
            exit(93);
        }
        $command = array_slice($args, $index + 1);
        if ($command[0] === 'sh') {
            exit(0);
        } elseif ($command[0] === 'psql') {
            $sql = end($command);
            if (str_contains($sql, 'current_database()')) {
                echo match ($failure) {
                    'identity' => 'soamco_budget|170006|false',
                    'server-version' => 'soamco_restore|180001|false',
                    default => 'soamco_restore|170006|false',
                };
            } elseif (str_contains($sql, 'restore_function(8)')) {
                echo $failure === 'synthetic' ? 'incorrect' : 'synthetic|2|42|9|1';
            } elseif (str_contains($sql, 'json_build_object')) {
                echo '{"tables":2,"sequences":1}';
            }
        } elseif ($command[0] === 'pg_restore') {
            if (in_array('--version', $command, true)) {
                echo $failure === 'client' ? 'pg_restore (PostgreSQL) 18.1' : 'pg_restore (PostgreSQL) 17.6';
            } elseif (in_array('--list', $command, true)) {
                if ($failure === 'archive-list' || stream_get_contents(STDIN) !== 'PGDMP-synthetic-fixture') {
                    $fail();
                }
                $toc = file_get_contents($root.'/toc');
                echo $failure === 'toc' ? str_replace('restore_parent', 'wrong_table', $toc) : $toc;
            } else {
                file_put_contents($root.'/restored-input', stream_get_contents(STDIN));
                if ($failure === 'signal') {
                    posix_kill(posix_getppid(), SIGTERM);
                    usleep(50000);
                    $fail();
                }
                if ($failure === 'restore') {
                    $fail();
                }
            }
        } elseif ($command[0] === 'pg_amcheck') {
            if ($failure === 'amcheck') {
                $fail();
            }
        } elseif ($command[0] === 'pg_dump') {
            echo 'PGDMP-synthetic-fixture';
        } elseif (! in_array($command[0], ['createdb', 'dropdb'], true)) {
            exit(94);
        }
    } else {
        exit(95);
    }
    PHP;
    file_put_contents($this->fixture.'/commands/docker', '#!'.PHP_BINARY."\n".$mock);
    chmod($this->fixture.'/commands/docker', 0700);
    $this->runRestore = function (string $failure = '', ?array $arguments = null): Process {
        $process = new Process(['python3', '-B', dirname(__DIR__, 2).'/bin/restore-postgres-isolated', ...($arguments ?? [$this->backup])], env: [
            'PATH' => $this->fixture.'/commands:'.getenv('PATH'),
            'RESTORE_FIXTURE' => $this->fixture,
            'RESTORE_FAILURE' => $failure,
            'DOCKER_HOST' => 'ssh://production',
            'DOCKER_CONTEXT' => 'production',
            'PGHOST' => 'production',
            'PGDATABASE' => 'soamco_budget',
            'TMPDIR' => $this->fixture,
        ]);
        $process->setTimeout(10);
        $process->run();

        return $process;
    };
});

afterEach(function () {
    (new Filesystem)->deleteDirectory($this->fixture);
});

it('restores only into its own offline PostgreSQL 17 instance and verifies the result', function () {
    $process = ($this->runRestore)();
    expect($process->getExitCode())->toBe(0, $process->getErrorOutput())
        ->and($process->getErrorOutput())->toContain('ISOLATED', 'VERIFIED', 'pg_amcheck completed', 'CLEANUP')
        ->and(file_get_contents($this->fixture.'/restored-input'))->toBe('PGDMP-synthetic-fixture')
        ->and(file_exists($this->fixture.'/container.json'))->toBeFalse()
        ->and(glob($this->fixture.'/soamco-restore-input-*'))->toBe([])
        ->and(file_get_contents($this->backup.'/database.dump'))->toBe('PGDMP-synthetic-fixture');
    $calls = array_map(fn (string $line): array => json_decode($line, true), file($this->fixture.'/calls'));
    foreach ($calls as $call) {
        expect($call['docker_host'])->toBeFalse()->and($call['pg_host'])->toBeFalse();
    }
    $serialized = file_get_contents($this->fixture.'/calls');
    expect($serialized)->toContain('--pull=never', '--network', '--read-only', '--no-owner', '--no-privileges', '--no-tablespaces', '--single-transaction', '--exit-on-error', '--database=soamco_restore')
        ->not->toContain('soamco-budget-db', '--network=host', '--publish', '--volumes-from', '--clean', '--create', '--dbname=soamco_budget');
});

it('rejects damaged or incompatible input before contacting Docker', function (string $kind) {
    if ($kind === 'checksum') {
        file_put_contents($this->backup.'/database.dump', 'PGDMP-corrupt');
    } elseif ($kind === 'manifest') {
        file_put_contents($this->backup.'/SHA256SUMS', str_repeat('a', 64)."  ../../production\n");
    } elseif ($kind === 'version') {
        file_put_contents($this->backup.'/database.list', str_replace('17.6', '18.1', $this->toc));
        ($this->updateManifest)();
    } elseif ($kind === 'format') {
        file_put_contents($this->backup.'/database.dump', 'plain SQL data');
        ($this->updateManifest)();
    } elseif ($kind === 'symlink') {
        rename($this->backup.'/database.dump', $this->fixture.'/original');
        symlink($this->fixture.'/original', $this->backup.'/database.dump');
    } elseif ($kind === 'extra') {
        file_put_contents($this->backup.'/unmanaged', 'keep');
    } elseif ($kind === 'permissions') {
        chmod($this->backup.'/database.dump', 0660);
    } else {
        link($this->backup.'/database.dump', $this->fixture.'/hardlink');
    }
    $process = ($this->runRestore)();
    expect($process->isSuccessful())->toBeFalse()
        ->and(file_exists($this->fixture.'/calls'))->toBeFalse()
        ->and(glob($this->fixture.'/soamco-restore-input-*'))->toBe([]);
})->with(['checksum', 'manifest', 'version', 'format', 'symlink', 'extra', 'permissions', 'hardlink']);

it('refuses destinations whose ownership or isolation cannot be proven', function (string $failure) {
    $process = ($this->runRestore)($failure);
    expect($process->isSuccessful())->toBeFalse();
    $calls = file_get_contents($this->fixture.'/calls');
    expect($calls)->not->toContain('"exec"', '"start"', '"rm"')
        ->and($process->getErrorOutput())->not->toContain('SUCCESS restore');
})->with(['endpoint', 'image', 'label', 'container-id', 'network', 'extra-network', 'ports', 'privileged', 'volume', 'bind']);

it('cleans only its own sandbox after failures and interruptions even if keep was requested', function (string $failure) {
    $process = ($this->runRestore)($failure, [$this->backup, '--keep']);
    expect($process->isSuccessful())->toBeFalse()
        ->and($process->getErrorOutput())->toContain('ERROR', 'CLEANUP')
        ->not->toContain('KEPT', 'PRIVATE-SQL-OR-CREDENTIALS')
        ->and(file_exists($this->fixture.'/container.json'))->toBeFalse()
        ->and(glob($this->fixture.'/soamco-restore-input-*'))->toBe([]);
})->with(['identity', 'server-version', 'client', 'archive-list', 'toc', 'restore', 'amcheck', 'signal']);

it('keeps a sandbox only after all restore checks pass and still removes the private input snapshot', function () {
    $process = ($this->runRestore)('', [$this->backup, '--keep']);
    expect($process->getExitCode())->toBe(0, $process->getErrorOutput())
        ->and($process->getErrorOutput())->toContain('KEPT')
        ->and(file_exists($this->fixture.'/container.json'))->toBeTrue()
        ->and(glob($this->fixture.'/soamco-restore-input-*'))->toBe([])
        ->and(file_get_contents($this->fixture.'/calls'))->not->toContain('"rm"');
});

it('reports incomplete cleanup instead of reporting an entirely successful procedure', function () {
    $process = ($this->runRestore)('cleanup');
    expect($process->isSuccessful())->toBeFalse()
        ->and($process->getErrorOutput())->toContain('cleanup refused or failed');
});

it('performs the synthetic workflow without reading any supplied backup or application settings', function () {
    (new Filesystem)->deleteDirectory($this->backup);
    $process = ($this->runRestore)('', ['--self-test']);
    expect($process->getExitCode())->toBe(0, $process->getErrorOutput())
        ->and($process->getErrorOutput())->toContain('synthetic restore verified', 'CLEANUP');
    $calls = file_get_contents($this->fixture.'/calls');
    expect($calls)->toContain('"createdb"', '"pg_dump"', '"dropdb"', '--dbname=fixture_source')
        ->not->toContain('artisan', 'migrate', 'soamco-budget-db');
});

it('does not accept existing target connection parameters', function (array $arguments) {
    $process = ($this->runRestore)('', $arguments);
    expect($process->getExitCode())->toBe(2)
        ->and(file_exists($this->fixture.'/calls'))->toBeFalse();
})->with([[['--host', 'production']], [['--dbname', 'soamco_budget']], [['--container', 'soamco-budget-db']], [['--self-test', '--keep']]]);

it('rejects an unsuccessful synthetic data check and removes that sandbox', function () {
    $process = ($this->runRestore)('synthetic', ['--self-test']);
    expect($process->isSuccessful())->toBeFalse()
        ->and($process->getErrorOutput())->toContain('Synthetic data verification failed', 'CLEANUP')
        ->not->toContain('SUCCESS synthetic restore verified')
        ->and(file_exists($this->fixture.'/container.json'))->toBeFalse();
});

it('restores synthetic rows schema constraints sequence and function on a real isolated PostgreSQL 17 instance', function () {
    if (getenv('RUN_POSTGRES_RESTORE_INTEGRATION') !== '1') {
        $this->markTestSkipped('Explicitly opt in to Docker integration; never reads production backups.');
    }
    $process = new Process(['python3', '-B', dirname(__DIR__, 2).'/bin/restore-postgres-isolated', '--self-test']);
    $process->setTimeout(1800);
    $process->run();
    expect($process->getExitCode())->toBe(0, $process->getErrorOutput())
        ->and($process->getErrorOutput())->toContain('synthetic restore verified', 'CLEANUP');
})->group('postgres-restore-integration');
