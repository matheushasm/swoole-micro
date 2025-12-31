<?php

declare(strict_types=1);

use SwooleMicro\Queue\InMemoryQueueDriver;
use SwooleMicro\Support\Env;
use SwooleMicro\Supervisor\RedisSupervisor;

$queueDriverValue = normalizeClassName(Env::get('QUEUE_DRIVER', 'memory'));
$queueDriver = resolveDriver($queueDriverValue, [
    'memory' => InMemoryQueueDriver::class,
    'inmemory' => InMemoryQueueDriver::class,
    'array' => InMemoryQueueDriver::class,
    'redis' => \SwooleMicro\Queue\RedisQueueDriver::class,
]);
$supervisorDriverValue = normalizeClassName(Env::get('SUPERVISOR_DRIVER', 'redis'));
$supervisorDriver = resolveDriver($supervisorDriverValue, [
    'redis' => RedisSupervisor::class,
]);
$workerName = Env::get('WORKER_NAME', 'default');
$workerProcessor = normalizeClassName(
    Env::get('WORKER_PROCESSOR', \Examples\Processors\SendEmailProcessor::class)
);
$workerQueue = Env::get('WORKER_QUEUE', 'default');

return [
    'queue' => [
        'driver' => $queueDriver,
        'redis' => [
            'host' => Env::get('REDIS_HOST', '127.0.0.1'),
            'port' => (int) Env::get('REDIS_PORT', 6379),
            'db' => (int) Env::get('REDIS_DB', 0),
            'username' => Env::get('REDIS_USERNAME', 'default'),
            'password' => Env::get('REDIS_PASSWORD', ''),
            'prefix' => Env::get('REDIS_PREFIX', 'swoole-micro:'),
        ],
    ],

    'supervisor' => [
        'driver' => $supervisorDriver,
        'redis' => [
            'host' => Env::get('REDIS_HOST', '127.0.0.1'),
            'port' => (int) Env::get('REDIS_PORT', 6379),
            'db' => (int) Env::get('REDIS_DB', 0),
            'username' => Env::get('REDIS_USERNAME', 'default'),
            'password' => Env::get('REDIS_PASSWORD', ''),
            'prefix' => Env::get('REDIS_PREFIX', 'swoole-micro:'),
        ],
    ],

    'workers' => [
        $workerName => [
            'processor' => $workerProcessor,
            'queue' => $workerQueue,
            'options' => [
                'concurrency' => (int) Env::get('WORKER_CONCURRENCY', 10),
                'timeoutSeconds' => (int) Env::get('WORKER_TIMEOUT_SECONDS', 30),
                'memoryLimitMb' => (int) Env::get('WORKER_MEMORY_LIMIT_MB', 256),
                'heartbeatSeconds' => (int) Env::get('WORKER_HEARTBEAT_SECONDS', 5),
            ],
        ],
    ],
];

function resolveDriver(string $value, array $map): string
{
    $normalized = strtolower(trim($value));
    if (isset($map[$normalized])) {
        return $map[$normalized];
    }

    return $value;
}

function normalizeClassName(string $value): string
{
    return str_replace('\\\\', '\\', $value);
}
