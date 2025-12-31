<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use SwooleMicro\Queue\RedisQueueDriver;

final class WorkerIntegrationTest extends TestCase
{
    public function testMultipleWorkersConsumeJobsFromRedisQueue(): void
    {
        if (!extension_loaded('swoole')) {
            $this->markTestSkipped('Swoole extension is required.');
        }

        if (!extension_loaded('redis')) {
            $this->markTestSkipped('Redis extension is required.');
        }

        $client = $this->createRedisClient();
        if ($client === null) {
            return;
        }

        $prefix = 'swoole-micro:test:' . bin2hex(random_bytes(4)) . ':';
        $queueName = 'integration';
        $expectedJobs = 6;
        $driver = new RedisQueueDriver(
            $this->redisHost(),
            $this->redisPort(),
            $this->redisDb(),
            $prefix,
            $this->redisUsername(),
            $this->redisPassword()
        );

        $processes = [];

        try {
            $processes[] = $this->spawnWorker($queueName, $prefix, 'worker-a', 2);
            $processes[] = $this->spawnWorker($queueName, $prefix, 'worker-b', 2);

            for ($i = 0; $i < $expectedJobs; $i++) {
                $driver->push($queueName, ['index' => $i]);
            }

            $this->assertTrue($this->waitForProcessedTotalCount(
                $client,
                $prefix,
                $expectedJobs,
                10,
                ['worker-a', 'worker-b']
            ));
        } finally {
            foreach ($processes as $process) {
                if (is_resource($process)) {
                    proc_terminate($process);
                    proc_close($process);
                }
            }

            $client->del(
                $prefix . 'processed:worker-a',
                $prefix . 'processed:worker-b',
                $prefix . 'queue:' . $queueName,
                $prefix . 'processing:' . $queueName,
                $prefix . 'jobs:' . $queueName
            );

            $keys = $client->keys($prefix . 'default:*');
            if (is_array($keys) && $keys !== []) {
                $client->del($keys);
            }
        }
    }

    public function testSingleWorkerConcurrencyProcessesJobs(): void
    {
        if (!extension_loaded('swoole')) {
            $this->markTestSkipped('Swoole extension is required.');
        }

        if (!extension_loaded('redis')) {
            $this->markTestSkipped('Redis extension is required.');
        }

        $client = $this->createRedisClient();
        if ($client === null) {
            return;
        }

        $prefix = 'swoole-micro:test:' . bin2hex(random_bytes(4)) . ':';
        $queueName = 'single-worker';
        $expectedJobs = 8;
        $driver = new RedisQueueDriver(
            $this->redisHost(),
            $this->redisPort(),
            $this->redisDb(),
            $prefix,
            $this->redisUsername(),
            $this->redisPassword()
        );

        $processes = [];

        try {
            $processes[] = $this->spawnWorker($queueName, $prefix, 'solo', 4);

            for ($i = 0; $i < $expectedJobs; $i++) {
                $driver->push($queueName, ['index' => $i]);
            }

            $this->assertTrue($this->waitForProcessedCount($client, $prefix, $expectedJobs, 10, 'solo'));
        } finally {
            foreach ($processes as $process) {
                if (is_resource($process)) {
                    proc_terminate($process);
                    proc_close($process);
                }
            }

            $client->del(
                $prefix . 'processed:solo',
                $prefix . 'queue:' . $queueName,
                $prefix . 'processing:' . $queueName,
                $prefix . 'jobs:' . $queueName
            );
        }
    }

    public function testJobsAreDistributedAcrossMultipleWorkers(): void
    {
        if (!extension_loaded('swoole')) {
            $this->markTestSkipped('Swoole extension is required.');
        }

        if (!extension_loaded('redis')) {
            $this->markTestSkipped('Redis extension is required.');
        }

        $client = $this->createRedisClient();
        if ($client === null) {
            return;
        }

        $prefix = 'swoole-micro:test:' . bin2hex(random_bytes(4)) . ':';
        $queueName = 'distributed';
        $expectedJobs = 10;
        $driver = new RedisQueueDriver(
            $this->redisHost(),
            $this->redisPort(),
            $this->redisDb(),
            $prefix,
            $this->redisUsername(),
            $this->redisPassword()
        );

        $processes = [];

        try {
            $processes[] = $this->spawnWorker($queueName, $prefix, 'worker-a', 2);
            $processes[] = $this->spawnWorker($queueName, $prefix, 'worker-b', 2);

            for ($i = 0; $i < $expectedJobs; $i++) {
                $driver->push($queueName, ['index' => $i]);
            }

            $this->assertTrue($this->waitForProcessedTotalCount(
                $client,
                $prefix,
                $expectedJobs,
                12,
                ['worker-a', 'worker-b']
            ));

            $countA = (int) ($client->get($prefix . 'processed:worker-a') ?: 0);
            $countB = (int) ($client->get($prefix . 'processed:worker-b') ?: 0);

            $this->assertGreaterThan(0, $countA);
            $this->assertGreaterThan(0, $countB);
            $this->assertSame($expectedJobs, $countA + $countB);
        } finally {
            foreach ($processes as $process) {
                if (is_resource($process)) {
                    proc_terminate($process);
                    proc_close($process);
                }
            }

            $client->del(
                $prefix . 'processed:worker-a',
                $prefix . 'processed:worker-b',
                $prefix . 'queue:' . $queueName,
                $prefix . 'processing:' . $queueName,
                $prefix . 'jobs:' . $queueName
            );
        }
    }

    public function testWorkerContinuesAfterProcessorFailure(): void
    {
        if (!extension_loaded('swoole')) {
            $this->markTestSkipped('Swoole extension is required.');
        }

        if (!extension_loaded('redis')) {
            $this->markTestSkipped('Redis extension is required.');
        }

        $client = $this->createRedisClient();
        if ($client === null) {
            return;
        }

        $prefix = 'swoole-micro:test:' . bin2hex(random_bytes(4)) . ':';
        $queueName = 'failures';
        $driver = new RedisQueueDriver(
            $this->redisHost(),
            $this->redisPort(),
            $this->redisDb(),
            $prefix,
            $this->redisUsername(),
            $this->redisPassword()
        );

        $processes = [];

        try {
            $processes[] = $this->spawnWorkerWithProcessor(
                $queueName,
                $prefix,
                'fail',
                1,
                'Tests\\Fixtures\\RedisFailingProcessor'
            );

            $driver->push($queueName, ['index' => 1]);
            $driver->push($queueName, ['index' => 2, 'fail' => true]);
            $driver->push($queueName, ['index' => 3]);

            $this->assertTrue($this->waitForProcessedCount($client, $prefix, 2, 10, 'fail'));
            $this->assertTrue($this->waitForKeyCount($client, $prefix . 'failed:fail', 1, 10));

            $this->assertSame(1, $client->lLen($prefix . 'processing:' . $queueName));
        } finally {
            foreach ($processes as $process) {
                if (is_resource($process)) {
                    proc_terminate($process);
                    proc_close($process);
                }
            }

            $client->del(
                $prefix . 'processed:fail',
                $prefix . 'failed:fail',
                $prefix . 'queue:' . $queueName,
                $prefix . 'processing:' . $queueName,
                $prefix . 'jobs:' . $queueName
            );
        }
    }

    public function testCliQueuePushEnqueuesJob(): void
    {
        if (!extension_loaded('redis')) {
            $this->markTestSkipped('Redis extension is required.');
        }

        $client = $this->createRedisClient();
        if ($client === null) {
            return;
        }

        $prefix = 'swoole-micro:test:' . bin2hex(random_bytes(4)) . ':';
        $queueName = 'cli';

        try {
            $command = $this->buildCliCommand('queue:push', [
                $queueName,
                '{"ok":true}',
            ]);

            $env = $this->buildEnv([
                'QUEUE_DRIVER' => 'redis',
                'REDIS_HOST' => $this->redisHost(),
                'REDIS_PORT' => (string) $this->redisPort(),
                'REDIS_DB' => (string) $this->redisDb(),
                'REDIS_USERNAME' => $this->redisUsername(),
                'REDIS_PASSWORD' => $this->redisPassword(),
                'REDIS_PREFIX' => $prefix,
            ]);

            $process = proc_open($command, [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ], $pipes, null, $env);

            if (!is_resource($process)) {
                $this->fail('Failed to run CLI queue:push.');
            }

            $output = stream_get_contents($pipes[1]);
            $error = stream_get_contents($pipes[2]);
            foreach ($pipes as $pipe) {
                fclose($pipe);
            }

            $exitCode = proc_close($process);

            $this->assertSame(0, $exitCode, $error);
            $this->assertStringContainsString('Enqueued job', (string) $output);

            $this->assertSame(1, $client->lLen($prefix . 'queue:' . $queueName));
            $this->assertSame(1, $client->hLen($prefix . 'jobs:' . $queueName));
        } finally {
            $client->del(
                $prefix . 'queue:' . $queueName,
                $prefix . 'processing:' . $queueName,
                $prefix . 'jobs:' . $queueName
            );
        }
    }

    public function testCliQueuePushRejectsInvalidJson(): void
    {
        if (!extension_loaded('redis')) {
            $this->markTestSkipped('Redis extension is required.');
        }

        $client = $this->createRedisClient();
        if ($client === null) {
            return;
        }

        $prefix = 'swoole-micro:test:' . bin2hex(random_bytes(4)) . ':';
        $queueName = 'cli-invalid';

        try {
            $command = $this->buildCliCommand('queue:push', [
                $queueName,
                '{invalid',
            ]);

            $env = $this->buildEnv([
                'QUEUE_DRIVER' => 'redis',
                'REDIS_HOST' => $this->redisHost(),
                'REDIS_PORT' => (string) $this->redisPort(),
                'REDIS_DB' => (string) $this->redisDb(),
                'REDIS_USERNAME' => $this->redisUsername(),
                'REDIS_PASSWORD' => $this->redisPassword(),
                'REDIS_PREFIX' => $prefix,
            ]);

            $process = proc_open($command, [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ], $pipes, null, $env);

            if (!is_resource($process)) {
                $this->fail('Failed to run CLI queue:push.');
            }

            $output = stream_get_contents($pipes[1]);
            $error = stream_get_contents($pipes[2]);
            foreach ($pipes as $pipe) {
                fclose($pipe);
            }

            $exitCode = proc_close($process);

            $this->assertSame(1, $exitCode);
            $this->assertStringContainsString('Invalid JSON payload', $error . $output);

            $this->assertSame(0, $client->lLen($prefix . 'queue:' . $queueName));
            $this->assertSame(0, $client->hLen($prefix . 'jobs:' . $queueName));
        } finally {
            $client->del(
                $prefix . 'queue:' . $queueName,
                $prefix . 'processing:' . $queueName,
                $prefix . 'jobs:' . $queueName
            );
        }
    }

    private function spawnWorker(string $queueName, string $prefix, string $tag, int $concurrency)
    {
        return $this->spawnWorkerWithProcessor($queueName, $prefix, $tag, $concurrency, 'Tests\\Fixtures\\RedisCounterProcessor');
    }

    private function spawnWorkerWithProcessor(
        string $queueName,
        string $prefix,
        string $tag,
        int $concurrency,
        string $processor
    ) {
        $command = $this->buildCliCommand('worker:run', ['default']);

        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $env = $this->buildEnv([
            'QUEUE_DRIVER' => 'redis',
            'SUPERVISOR_DRIVER' => 'redis',
            'WORKER_NAME' => 'default',
            'WORKER_QUEUE' => $queueName,
            'WORKER_PROCESSOR' => $processor,
            'WORKER_CONCURRENCY' => (string) $concurrency,
            'REDIS_HOST' => $this->redisHost(),
            'REDIS_PORT' => (string) $this->redisPort(),
            'REDIS_DB' => (string) $this->redisDb(),
            'REDIS_USERNAME' => $this->redisUsername(),
            'REDIS_PASSWORD' => $this->redisPassword(),
            'REDIS_PREFIX' => $prefix,
            'PROCESSOR_TAG' => $tag,
        ]);

        $process = proc_open($command, $descriptorSpec, $pipes, null, $env);
        if (!is_resource($process)) {
            $this->fail('Failed to spawn worker process.');
        }

        return $process;
    }

    private function waitForProcessedCount(
        Redis $client,
        string $prefix,
        int $expected,
        int $timeoutSeconds,
        string $tag
    ): bool {
        return $this->waitForProcessedTotalCount($client, $prefix, $expected, $timeoutSeconds, [$tag]);
    }

    private function waitForProcessedTotalCount(
        Redis $client,
        string $prefix,
        int $expected,
        int $timeoutSeconds,
        array $tags
    ): bool {
        $start = time();

        while ((time() - $start) <= $timeoutSeconds) {
            $total = 0;
            foreach ($tags as $tag) {
                $key = $prefix . 'processed:' . $tag;
                $total += (int) ($client->get($key) ?: 0);
            }

            if ($total >= $expected) {
                return true;
            }

            usleep(100000);
        }

        return false;
    }

    private function waitForKeyCount(Redis $client, string $key, int $expected, int $timeoutSeconds): bool
    {
        $start = time();

        while ((time() - $start) <= $timeoutSeconds) {
            $value = (int) ($client->get($key) ?: 0);
            if ($value >= $expected) {
                return true;
            }

            usleep(100000);
        }

        return false;
    }

    private function buildCliCommand(string $command, array $args): string
    {
        $script = escapeshellarg(dirname(__DIR__, 2) . '/bin/swoole-micro');
        $php = escapeshellarg(PHP_BINARY);
        $parts = array_map('escapeshellarg', $args);
        $argsString = implode(' ', $parts);

        return "{$php} {$script} {$command} {$argsString}";
    }

    private function createRedisClient(): ?Redis
    {
        $client = new Redis();
        try {
            if (!$client->connect($this->redisHost(), $this->redisPort())) {
                $this->markTestSkipped('Redis connection failed.');
            }

            if ($this->redisPassword() !== '') {
                if ($this->redisUsername() !== '' && $this->redisUsername() !== 'default') {
                    $client->auth([$this->redisUsername(), $this->redisPassword()]);
                } else {
                    $client->auth($this->redisPassword());
                }
            }

            if ($this->redisDb() > 0) {
                $client->select($this->redisDb());
            }
        } catch (Throwable $exception) {
            $this->markTestSkipped('Redis connection failed: ' . $exception->getMessage());
        }

        return $client;
    }

    private function buildEnv(array $overrides): array
    {
        $env = [];

        foreach (array_merge($_SERVER, $_ENV) as $key => $value) {
            if (!is_string($key)) {
                continue;
            }

            if (is_string($value)) {
                $env[$key] = $value;
            }
        }

        foreach ($overrides as $key => $value) {
            $env[$key] = $value;
        }

        return $env;
    }

    private function redisHost(): string
    {
        return getenv('REDIS_HOST') ?: '127.0.0.1';
    }

    private function redisPort(): int
    {
        return (int) (getenv('REDIS_PORT') ?: 6379);
    }

    private function redisDb(): int
    {
        return (int) (getenv('REDIS_DB') ?: 0);
    }

    private function redisUsername(): string
    {
        return getenv('REDIS_USERNAME') ?: 'default';
    }

    private function redisPassword(): string
    {
        return getenv('REDIS_PASSWORD') ?: '';
    }
}
