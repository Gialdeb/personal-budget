<?php

test('redis client configuration falls back to predis when the redis extension is unavailable', function () {
    $originalRedisClient = getenv('REDIS_CLIENT');

    putenv('REDIS_CLIENT');
    unset($_ENV['REDIS_CLIENT'], $_SERVER['REDIS_CLIENT']);

    $config = require base_path('config/database.php');

    expect($config['redis']['client'])
        ->toBe(extension_loaded('redis') ? 'phpredis' : 'predis');

    if ($originalRedisClient === false) {
        putenv('REDIS_CLIENT');
        unset($_ENV['REDIS_CLIENT'], $_SERVER['REDIS_CLIENT']);

        return;
    }

    putenv("REDIS_CLIENT={$originalRedisClient}");
    $_ENV['REDIS_CLIENT'] = $originalRedisClient;
    $_SERVER['REDIS_CLIENT'] = $originalRedisClient;
});
