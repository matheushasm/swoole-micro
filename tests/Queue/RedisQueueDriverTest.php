<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use SwooleMicro\Queue\RedisQueueDriver;

final class RedisQueueDriverTest extends TestCase
{
    public function testConstructorThrowsWhenRedisMissing(): void
    {
        if (extension_loaded('redis')) {
            $this->markTestSkipped('Redis extension is available.');
        }

        $this->expectException(RuntimeException::class);
        new RedisQueueDriver();
    }

    public function testPushPopAckFlow(): void
    {
        $client = $this->createRedisClient();
        if ($client === null) {
            return;
        }

        $prefix = 'swoole-micro:test:' . bin2hex(random_bytes(4)) . ':';
        $queueName = 'default';
        $driver = $this->createDriver($prefix);

        try {
            $jobId = $driver->push($queueName, ['hello' => 'world']);
            $job = $driver->pop($queueName);

            $this->assertNotNull($job);
            $this->assertSame($jobId, $job['id']);
            $this->assertSame(['hello' => 'world'], $job['payload']);

            $driver->ack($queueName, $jobId);

            $this->assertSame(0, $client->hLen($prefix . 'jobs:' . $queueName));
            $this->assertSame(0, $client->lLen($prefix . 'processing:' . $queueName));
            $this->assertSame(0, $client->lLen($prefix . 'queue:' . $queueName));
        } finally {
            $client->del(
                $prefix . 'queue:' . $queueName,
                $prefix . 'processing:' . $queueName,
                $prefix . 'jobs:' . $queueName
            );
        }
    }

    public function testPopReturnsNullWhenEmpty(): void
    {
        $client = $this->createRedisClient();
        if ($client === null) {
            return;
        }

        $prefix = 'swoole-micro:test:' . bin2hex(random_bytes(4)) . ':';
        $queueName = 'empty';
        $driver = $this->createDriver($prefix);

        try {
            $job = $driver->pop($queueName);
            $this->assertNull($job);
        } finally {
            $client->del(
                $prefix . 'queue:' . $queueName,
                $prefix . 'processing:' . $queueName,
                $prefix . 'jobs:' . $queueName
            );
        }
    }

    public function testConcurrentPopUsesSeparateConnections(): void
    {
        if (!extension_loaded('swoole')) {
            $this->markTestSkipped('Swoole extension is required.');
        }

        $client = $this->createRedisClient();
        if ($client === null) {
            return;
        }

        $prefix = 'swoole-micro:test:' . bin2hex(random_bytes(4)) . ':';
        $queueName = 'concurrent';
        $driver = $this->createDriver($prefix);

        try {
            $driver->push($queueName, ['id' => 1]);
            $driver->push($queueName, ['id' => 2]);

            $jobs = [];

            Swoole\Coroutine\run(function () use ($driver, $queueName, &$jobs): void {
                $channel = new Swoole\Coroutine\Channel(2);
                $waitGroup = new Swoole\Coroutine\WaitGroup();

                for ($i = 0; $i < 2; $i++) {
                    $waitGroup->add();
                    Swoole\Coroutine::create(function () use ($driver, $queueName, $channel, $waitGroup): void {
                        $job = $driver->pop($queueName);
                        $channel->push($job);
                        $waitGroup->done();
                    });
                }

                $waitGroup->wait();

                $jobs = [$channel->pop(), $channel->pop()];
            });

            $ids = array_map(static fn ($job) => $job['payload']['id'], $jobs);
            sort($ids);

            $this->assertSame([1, 2], $ids);

            foreach ($jobs as $job) {
                $driver->ack($queueName, $job['id']);
            }
        } finally {
            $client->del(
                $prefix . 'queue:' . $queueName,
                $prefix . 'processing:' . $queueName,
                $prefix . 'jobs:' . $queueName
            );
        }
    }

    private function createDriver(string $prefix): RedisQueueDriver
    {
        return new RedisQueueDriver(
            $this->redisHost(),
            $this->redisPort(),
            $this->redisDb(),
            $prefix,
            $this->redisUsername(),
            $this->redisPassword()
        );
    }

    private function createRedisClient(): ?Redis
    {
        if (!extension_loaded('redis')) {
            $this->markTestSkipped('Redis extension is required.');
        }

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
