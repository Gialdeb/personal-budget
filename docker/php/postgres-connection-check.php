<?php

use Illuminate\Database\ConfigurationUrlParser;
use Illuminate\Database\Connectors\ConnectionFactory;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Foundation\Bootstrap\LoadEnvironmentVariables;

/**
 * @return Closure(array<string, mixed>, ConnectionFactory): ?string
 */
$checkPostgresConnection = static function (array $configuration, ConnectionFactory $factory): ?string {
    $connection = null;

    try {
        $configuration = (new ConfigurationUrlParser)->parseConfiguration($configuration);

        if (($configuration['driver'] ?? null) !== 'pgsql') {
            return 'The production database connection must use PostgreSQL.';
        }

        $configuration['options'][PDO::ATTR_TIMEOUT] = 5;
        $configuration['options'][PDO::ATTR_PERSISTENT] = false;
        $configuration['options'][PDO::ATTR_ERRMODE] = PDO::ERRMODE_EXCEPTION;

        if (isset($configuration['write'])) {
            $writerOptions = static function (array $writer) use ($configuration): array {
                $writer['options'] = array_replace($writer['options'] ?? $configuration['options'], [
                    PDO::ATTR_TIMEOUT => 5,
                    PDO::ATTR_PERSISTENT => false,
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                ]);

                return $writer;
            };
            $configuration['write'] = array_is_list($configuration['write'])
                ? array_map($writerOptions, $configuration['write'])
                : $writerOptions($configuration['write']);
        }

        $connection = $factory->make($configuration, 'deployment-preflight');
        // getPdo() verifies the writer, not an optional read replica.
        $pdo = $connection->getPdo();

        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'pgsql') {
            return 'The production database connection must use PostgreSQL.';
        }

        $pdo->exec("SET statement_timeout = '5s'");

        if ((string) $pdo->query('SELECT 1')->fetchColumn() !== '1') {
            return 'PostgreSQL did not return the expected connection probe result.';
        }

        return null;
    } catch (Throwable) {
        return 'Cannot connect and authenticate to PostgreSQL. Verify the configured writer, credentials, TLS and network; diagnostics withheld.';
    } finally {
        $connection?->disconnect();
    }
};

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') !== __FILE__) {
    return $checkPostgresConnection;
}

try {
    require dirname(__DIR__, 2).'/vendor/autoload.php';
    $app = require dirname(__DIR__, 2).'/bootstrap/app.php';
    $app->bootstrapWith([
        LoadEnvironmentVariables::class,
        LoadConfiguration::class,
    ]);
    $configuration = config('database.connections.'.config('database.default'));

    if (! is_array($configuration)) {
        throw new RuntimeException('Missing database configuration.');
    }

    $problem = $checkPostgresConnection($configuration, new ConnectionFactory($app));

    if ($problem !== null) {
        fwrite(STDERR, 'PostgreSQL preflight failed: '.$problem.PHP_EOL);
        exit(1);
    }

    fwrite(STDOUT, 'PostgreSQL connection and authentication preflight passed.'.PHP_EOL);
} catch (Throwable) {
    fwrite(STDERR, 'PostgreSQL preflight failed: configuration could not be loaded; diagnostics withheld.'.PHP_EOL);
    exit(1);
}
