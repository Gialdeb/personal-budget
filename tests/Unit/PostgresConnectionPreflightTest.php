<?php

use Illuminate\Database\Connection;
use Illuminate\Database\Connectors\ConnectionFactory;
use Symfony\Component\Process\Process;

beforeEach(function () {
    $this->checkPostgres = require dirname(__DIR__, 2).'/docker/php/postgres-connection-check.php';
    $this->factory = Mockery::mock(ConnectionFactory::class);
    $this->connection = Mockery::mock(Connection::class);
    $this->pdo = Mockery::mock(PDO::class);
    $this->configuration = ['driver' => 'pgsql', 'host' => 'synthetic.invalid', 'database' => 'synthetic', 'username' => 'synthetic', 'password' => 'DO-NOT-LOG-PASSWORD'];
});

it('authenticates a new writer connection and runs only a session timeout and a read-only probe', function () {
    $this->factory->shouldReceive('make')->once()->withArgs(function (array $configuration, string $name): bool {
        return $name === 'deployment-preflight'
            && $configuration['password'] === 'DO-NOT-LOG-PASSWORD'
            && $configuration['options'][PDO::ATTR_TIMEOUT] === 5
            && $configuration['options'][PDO::ATTR_PERSISTENT] === false;
    })->andReturn($this->connection);
    $this->connection->shouldReceive('getPdo')->once()->andReturn($this->pdo);
    $this->pdo->shouldReceive('getAttribute')->with(PDO::ATTR_DRIVER_NAME)->once()->andReturn('pgsql');
    $this->pdo->shouldReceive('exec')->with("SET statement_timeout = '5s'")->once()->andReturn(0);
    $statement = Mockery::mock(PDOStatement::class);
    $this->pdo->shouldReceive('query')->with('SELECT 1')->once()->andReturn($statement);
    $statement->shouldReceive('fetchColumn')->once()->andReturn(1);
    $this->connection->shouldReceive('disconnect')->once();

    expect(($this->checkPostgres)($this->configuration, $this->factory))->toBeNull();
});

it('resolves DB_URL and preserves writer selection including multiple writer configurations', function (bool $multiple) {
    $writers = ['host' => 'writer.invalid', 'options' => [PDO::ATTR_PERSISTENT => true, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]];
    $configuration = [...$this->configuration, 'url' => 'postgres://url-user:encoded%2Bpassword@url-host.invalid:5432/url-database', 'read' => ['host' => 'replica.invalid'], 'write' => $multiple ? [$writers] : $writers];
    $this->factory->shouldReceive('make')->once()->withArgs(function (array $actual) use ($multiple): bool {
        $writer = $multiple ? $actual['write'][0] : $actual['write'];

        return $actual['username'] === 'url-user' && $actual['password'] === 'encoded+password'
            && $actual['database'] === 'url-database' && $actual['read']['host'] === 'replica.invalid'
            && $writer['host'] === 'writer.invalid' && $writer['options'][PDO::ATTR_TIMEOUT] === 5
            && $writer['options'][PDO::ATTR_PERSISTENT] === false
            && $writer['options'][PDO::ATTR_ERRMODE] === PDO::ERRMODE_EXCEPTION;
    })->andReturn($this->connection);
    $this->connection->shouldReceive('getPdo')->once()->andThrow(new PDOException('SENSITIVE-URL-DO-NOT-LOG'));
    $this->connection->shouldReceive('disconnect')->once();

    expect(($this->checkPostgres)($configuration, $this->factory))->toContain('Cannot connect and authenticate')
        ->not->toContain('SENSITIVE-URL', 'encoded+password', 'url-user');
})->with([true, false]);

it('refuses other database drivers without opening a connection', function () {
    $this->factory->shouldNotReceive('make');
    expect(($this->checkPostgres)(['driver' => 'sqlite', 'database' => ':memory:'], $this->factory))->toContain('must use PostgreSQL');
});

it('sanitizes authentication network and query failures and always disconnects', function (string $phase) {
    $this->factory->shouldReceive('make')->once()->andReturn($this->connection);
    $this->connection->shouldReceive('disconnect')->once();
    if ($phase === 'connect') {
        $this->connection->shouldReceive('getPdo')->once()->andThrow(new PDOException('DO-NOT-LOG-PASSWORD host=synthetic.invalid'));
    } else {
        $this->connection->shouldReceive('getPdo')->once()->andReturn($this->pdo);
        $this->pdo->shouldReceive('getAttribute')->with(PDO::ATTR_DRIVER_NAME)->andReturn('pgsql');
        $this->pdo->shouldReceive('exec')->with("SET statement_timeout = '5s'")->andReturn(0);
        $this->pdo->shouldReceive('query')->with('SELECT 1')->andThrow(new PDOException('DO-NOT-LOG-PASSWORD host=synthetic.invalid'));
    }
    expect(($this->checkPostgres)($this->configuration, $this->factory))->toContain('Cannot connect and authenticate')
        ->not->toContain('DO-NOT-LOG', 'synthetic.invalid');
})->with(['connect', 'query']);

it('returns a sanitized failure from the real command without connecting to a database', function () {
    $process = new Process([PHP_BINARY, 'docker/php/postgres-connection-check.php'], dirname(__DIR__, 2), [
        'DB_CONNECTION' => 'sqlite', 'DB_URL' => '', 'DB_DATABASE' => ':memory:',
        'DB_PASSWORD' => 'DO-NOT-LOG-PASSWORD', 'APP_CONFIG_CACHE' => sys_get_temp_dir().'/soamco-preflight-no-cache-'.bin2hex(random_bytes(8)),
    ]);
    $process->run();
    expect($process->getExitCode())->toBe(1)
        ->and($process->getErrorOutput())->toContain('PostgreSQL preflight failed', 'must use PostgreSQL')
        ->not->toContain('DO-NOT-LOG-PASSWORD', 'sqlite', ':memory:')
        ->and($process->getOutput())->toBe('');
});
