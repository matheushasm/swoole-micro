# Swoole Micro (MVP – Phase 1)

Swoole Micro is a lightweight toolkit to build PHP microservices and workers using Swoole processes + coroutines, with a clean architecture and pluggable components.

Phase 1 focuses on:

- Processors (job handlers)
- Workers (separate OS processes)
- CoroutinePool (parallel processing with max concurrency)
- Supervisor (Redis) (heartbeat + basic liveness monitoring)
- QueueDriver (InMemory) (for local/dev + tests)
- Minimal CLI runner

Design goals: simple, predictable, testable, framework-agnostic (Laravel support can be a separate bridge later).

## Table of contents

- [Why](#why)
- [Architecture](#architecture)
- [Folder structure](#folder-structure)
- [Requirements](#requirements)
- [Installation](#installation)
- [Quick start](#quick-start)
- [CLI](#cli)
- [Core concepts](#core-concepts)
- [Examples](#examples)
- [Configuration](#configuration)
- [Lifecycle and safety](#lifecycle-and-safety)
- [Roadmap](#roadmap)
- [License](#license)

## Why

PHP + Swoole is powerful, but building robust microservice workers usually means:

- manual Swoole\Process handling
- coroutine orchestration by hand
- no standard monitoring/heartbeat
- lots of repeated boilerplate across services

This project provides a clean, consistent foundation to:

- run multiple independent workers (separate processes)
- run parallel tasks safely with a concurrency limit
- monitor worker health with Redis-based heartbeats
- keep the code base framework-agnostic

## Architecture

High-level modules:

Core

- ProcessorInterface: your business logic handler
- Worker: process runner that pulls jobs and executes them
- CoroutinePool: concurrency limiter for coroutine-based parallel work
- Clock, Logger (optional adapters)

Queue

- QueueDriverInterface: push/pop/ack
- InMemoryQueueDriver: phase-1 driver for dev/tests

Supervisor

- SupervisorInterface: heartbeat + liveness
- RedisSupervisor: stores heartbeats and reads them back

CLI

Minimal command runner:

- worker:run (run a named worker)
- supervisor:watch (watch all heartbeats)

Rule: the core never depends on Laravel/Eloquent. Adapters/bridges come later.

## Folder structure

Recommended structure for the repository:

```
swoole-micro/
├── bin/
│   └── swoole-micro
├── config/
│   └── workers.php
├── src/
│   ├── Core/
│   │   ├── ProcessorInterface.php
│   │   ├── Worker.php
│   │   ├── WorkerOptions.php
│   │   ├── CoroutinePool.php
│   │   ├── Exceptions/
│   │   │   ├── ProcessorFailed.php
│   │   │   └── WorkerCrashed.php
│   ├── Queue/
│   │   ├── QueueDriverInterface.php
│   │   └── InMemoryQueueDriver.php
│   ├── Supervisor/
│   │   ├── SupervisorInterface.php
│   │   └── RedisSupervisor.php
│   └── Support/
│       ├── Env.php
│       └── Json.php
├── examples/
│   ├── processors/
│   │   ├── SendEmailProcessor.php
│   │   └── FetchUrlsProcessor.php
│   └── run_examples.php
├── tests/
├── composer.json
├── README.md
└── LICENSE
```

## Requirements

- PHP 8.2+
- Swoole 5+ (required for workers, coroutines, and InMemoryQueueDriver)
- Redis (required for RedisSupervisor and RedisQueueDriver)
- Composer (dependency manager)

Extensions:

- ext-swoole
- ext-redis (optional if you only use in-memory queue and skip supervisor)

Dependencies overview:

- Swoole: coroutine runtime, channels, and process lifecycle.
- Redis: supervisor heartbeats and Redis queue driver (production).

## Installation

Using Composer (as a package)

```
composer require vendor/swoole-micro
```

Local development (path repository)

In your app composer.json:

```
{
  "repositories": [
    {
      "type": "path",
      "url": "../swoole-micro",
      "options": { "symlink": true }
    }
  ],
  "require": {
    "vendor/swoole-micro": "*"
  }
}
```

Then:

```
composer update vendor/swoole-micro
```

Platform setup

Windows (requires WSL2 for Swoole):

1) Install WSL2 + Ubuntu
2) Install PHP 8.2+ and Composer
3) Install Swoole + Redis extensions:

```
sudo apt update
sudo apt install -y php-cli php-dev php-pear php-curl php-mbstring php-xml pkg-config redis-server
sudo pecl install swoole redis
echo "extension=swoole" | sudo tee /etc/php/8.2/cli/conf.d/20-swoole.ini
echo "extension=redis" | sudo tee /etc/php/8.2/cli/conf.d/20-redis.ini
```

Mac (Homebrew):

```
brew install php@8.4 pkg-config pcre2
brew install redis
pecl install swoole redis
echo "extension=swoole" > /opt/homebrew/etc/php/8.4/conf.d/ext-swoole.ini
echo "extension=redis" > /opt/homebrew/etc/php/8.4/conf.d/ext-redis.ini
brew services restart php
```

Linux (Ubuntu/Debian):

```
sudo apt update
sudo apt install -y php-cli php-dev php-pear php-curl php-mbstring php-xml pkg-config redis-server
sudo pecl install swoole redis
echo "extension=swoole" | sudo tee /etc/php/8.2/cli/conf.d/20-swoole.ini
echo "extension=redis" | sudo tee /etc/php/8.2/cli/conf.d/20-redis.ini
```

Docker (example):

```dockerfile
FROM php:8.2-cli

RUN apt-get update && apt-get install -y \\
    git unzip pkg-config libssl-dev libcurl4-openssl-dev \\
    libbrotli-dev libzstd-dev libpcre2-dev \\
    && pecl install swoole redis \\
    && docker-php-ext-enable swoole redis

WORKDIR /app
COPY . /app
RUN composer install

CMD [\"php\", \"bin/swoole-micro\", \"worker:run\", \"default\"]
```

## Quick start

Create config/workers.php:

```php
<?php

use SwooleMicro\Queue\InMemoryQueueDriver;
use SwooleMicro\Supervisor\RedisSupervisor;

return [
    'queue' => [
        'driver' => InMemoryQueueDriver::class,
    ],

    'supervisor' => [
        'driver' => RedisSupervisor::class,
        'redis' => [
            'host' => getenv('REDIS_HOST') ?: '127.0.0.1',
            'port' => (int) (getenv('REDIS_PORT') ?: 6379),
            'prefix' => 'swoole-micro:',
        ],
    ],

    'workers' => [
        'emails' => [
            'processor' => \App\Processors\SendEmailProcessor::class,
            'queue' => 'emails',
            'options' => [
                'concurrency' => 10,
                'timeoutSeconds' => 30,
                'memoryLimitMb' => 256,
                'heartbeatSeconds' => 5,
            ],
        ],
    ],
];
```

Run a worker:

```
php bin/swoole-micro worker:run emails
```

Run multiple worker instances:

```
php bin/swoole-micro worker:run emails 4
```

Watch heartbeats:

```
php bin/swoole-micro supervisor:watch
```

## CLI

The CLI includes an interactive mode and basic operational commands.

Interactive mode (menu-driven):

```
php bin/swoole-micro
```

Commands:

- `interactive`: starts the interactive menu (default when no command is provided)
- `workers:list`: list configured workers with processor and queue
- `worker:run <name> [count|--instances=<count>]`: run one or more worker processes by name
- `worker:spawn <name> <count>`: spawn multiple worker processes
- `queue:push <queue> <json>`: push a job into a Redis queue (requires `RedisQueueDriver`)
- `supervisor:watch`: watch Redis heartbeats and report dead workers

Examples:

```
php bin/swoole-micro workers:list
php bin/swoole-micro worker:run default
php bin/swoole-micro worker:run default 4
php bin/swoole-micro worker:run default --instances=4
php bin/swoole-micro worker:spawn default 3
php bin/swoole-micro queue:push default '{"to":"dev@example.com"}'
php bin/swoole-micro supervisor:watch
```

## Core concepts

### Processor

A Processor is the unit of work. It receives a payload and performs a task.

Contract:

```php
interface ProcessorInterface
{
    public function handle(mixed $payload): void;
}
```

Best practices:

- Keep it stateless
- Inject dependencies via constructor
- Throw exceptions when failed (later phases can add retries)

### BatchProcessor (easy mode)

If you want simple batch processing without dealing with coroutines directly,
extend `BatchProcessor`. It runs one item at a time by default and can process
items in parallel when you override `maxConcurrency()`.

Contract:

- `processItem($item, $payload)` handles a single item
- `itemsFromPayload($payload)` extracts the list (defaults to `items` key or a list payload)
- `maxConcurrency($payload)` controls parallelism (default 1)

### Worker

A Worker is a long-running OS process (Swoole Process recommended) that:

- continuously pops jobs from a queue
- runs them using a Processor
- uses CoroutinePool to process multiple jobs in parallel
- sends heartbeats via Supervisor

### CoroutinePool

A small utility that limits parallelism:

maxConcurrency(20) means at most 20 concurrent coroutines.

Perfect for:

- multiple HTTP calls
- batch DB operations
- CPU-light tasks in parallel

### QueueDriver

Queue is an interface. In phase 1 we ship an InMemory driver (dev/tests).

Contract:

- push(queue, payload)
- pop(queue)
- ack(job)

Later you plug RabbitMQ / Redis.

### Supervisor (Redis)

Supervisor stores worker heartbeats in Redis:

- key: prefix + workerName + instanceId
- value: last heartbeat timestamp

watch command checks dead workers by time threshold.

## Examples

Example 1: A simple processor

examples/processors/SendEmailProcessor.php

```php
<?php

declare(strict_types=1);

namespace Examples\Processors;

use SwooleMicro\Core\ProcessorInterface;

final class SendEmailProcessor implements ProcessorInterface
{
    public function handle(mixed $payload): void
    {
        if (!is_array($payload) || empty($payload['to'])) {
            throw new \InvalidArgumentException('Invalid payload: missing "to".');
        }

        // Simulate I/O work
        \Swoole\Coroutine::sleep(0.1);

        echo sprintf("Email sent to %s\n", $payload['to']);
    }
}
```

Example 2: Worker consuming jobs from queue

Pseudo-usage (your Worker will do the loop):

```php
$worker = Worker::make('emails')
    ->queue($queueDriver, 'emails')
    ->processor(new SendEmailProcessor())
    ->options(
        WorkerOptions::new()
            ->concurrency(10)
            ->timeoutSeconds(30)
            ->memoryLimitMb(256)
            ->heartbeatSeconds(5)
    )
    ->supervisor($redisSupervisor);

$worker->run();
```

Example 2.1: Running workers with Redis queue via CLI

```
QUEUE_DRIVER=redis php bin/swoole-micro worker:run emails
php bin/swoole-micro queue:push emails '{"to":"dev@example.com"}'
```

Example 2.2: Spawning multiple instances

```
php bin/swoole-micro worker:run emails --instances=4
```

Example 2.3: Full Redis worker setup (Processor + CLI)

```php
<?php

declare(strict_types=1);

namespace App\Processors;

use SwooleMicro\Core\ProcessorInterface;

final class SendEmailProcessor implements ProcessorInterface
{
    public function handle(mixed $payload): void
    {
        if (!is_array($payload) || empty($payload['to'])) {
            throw new \InvalidArgumentException('Invalid payload: missing "to".');
        }

        \Swoole\Coroutine::sleep(0.1);
        echo sprintf("Email sent to %s\n", $payload['to']);
    }
}
```

```php
<?php

use SwooleMicro\Queue\RedisQueueDriver;
use SwooleMicro\Supervisor\RedisSupervisor;

return [
    'queue' => [
        'driver' => RedisQueueDriver::class,
        'redis' => [
            'host' => '127.0.0.1',
            'port' => 6379,
            'db' => 0,
            'username' => 'default',
            'password' => '',
            'prefix' => 'swoole-micro:',
        ],
    ],
    'supervisor' => [
        'driver' => RedisSupervisor::class,
        'redis' => [
            'host' => '127.0.0.1',
            'port' => 6379,
            'db' => 0,
            'username' => 'default',
            'password' => '',
            'prefix' => 'swoole-micro:',
        ],
    ],
    'workers' => [
        'emails' => [
            'processor' => \App\Processors\SendEmailProcessor::class,
            'queue' => 'emails',
            'options' => [
                'concurrency' => 10,
                'timeoutSeconds' => 30,
                'memoryLimitMb' => 256,
                'heartbeatSeconds' => 5,
            ],
        ],
    ],
];
```

```
php bin/swoole-micro worker:run emails
php bin/swoole-micro queue:push emails '{"to":"dev@example.com"}'
```

Example 3: Parallel processing with CoroutinePool

When you need to process a batch in parallel:

```php
$items = range(1, 100);

CoroutinePool::make()
    ->maxConcurrency(20)
    ->run($items, function (int $item): void {
        // Simulate I/O
        \Swoole\Coroutine::sleep(0.05);
        echo "Processed item: {$item}\n";
    });
```

Use cases:

- fetching many URLs concurrently
- sending batches of notifications
- reading/writing multiple small files

Example 3.1: Work inside a worker with extra coroutines

```php
public function handle(mixed $payload): void
{
    $urls = $payload['urls'] ?? [];

    \\SwooleMicro\\Core\\CoroutinePool::make()
        ->maxConcurrency(10)
        ->run($urls, function (string $url): void {
            \\Swoole\\Coroutine::sleep(0.05);
            echo \"Fetched {$url}\\n\";
        });
}
```

Example 3.2: BatchProcessor for simple parallelism

```php
use SwooleMicro\Core\BatchProcessor;

final class FetchUrlsProcessor extends BatchProcessor
{
    protected function maxConcurrency(mixed $payload): int
    {
        return 10;
    }

    protected function itemsFromPayload(mixed $payload): array
    {
        return is_array($payload) && isset($payload['urls']) ? $payload['urls'] : [];
    }

    protected function processItem(mixed $item, mixed $payload): void
    {
        \Swoole\Coroutine::sleep(0.05);
        echo "Fetched {$item}\n";
    }
}
```

Fluent override:

```php
(new FetchUrlsProcessor())
    ->concurrency(10)
    ->handle(['urls' => ['https://example.com']]);
```

Example 4: Supervisor heartbeat monitoring

Worker side:

```php
$supervisor->heartbeat($workerName, $instanceId);
```

Watcher side:

```php
$dead = $supervisor->deadWorkers($thresholdSeconds = 15);

foreach ($dead as $deadWorker) {
    echo "DEAD: {$deadWorker}\n";
}
```

## Configuration

Suggested env vars:

```
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
```

Worker-level config (phase 1):

- concurrency: number of parallel jobs
- timeoutSeconds: max execution time per job (soft limit)
- memoryLimitMb: triggers graceful exit when exceeded
- heartbeatSeconds: heartbeat interval

## Lifecycle and safety

Long-lived PHP processes require discipline.

Recommended lifecycle hooks (phase 1 can start minimal, but keep a place for them):

- onWorkerStart()
- beforeJob()
- afterJob()
- onWorkerStop()

Safety rules (important):

- Do not use hidden global state
- Be careful with static caches
- Reset per-job state if needed
- Always catch top-level exceptions and keep the worker alive (or exit gracefully)

Memory limits approach:

If memory grows beyond memoryLimitMb, worker should:

- stop consuming new jobs
- finish running jobs
- exit with code 0 (so external supervisor restarts it)

## Roadmap

Phase 1 (this README)

- Processor contract
- Worker runner
- CoroutinePool
- Redis heartbeats
- InMemory queue
- Minimal CLI

Phase 2

- RabbitMQ driver
- Redis queue driver
- Retry strategy (exponential backoff)
- Dead-letter queue
- Metrics (Prometheus)

Phase 3

- Laravel bridge package
- Dashboard (optional)
- Distributed locks for singleton workers

## License

MIT

## Git Hooks

Run unit tests on every `git push`:

```
git config core.hooksPath .githooks
```

This enables `.githooks/pre-push`, which runs `vendor/bin/phpunit`.
