<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use SwooleMicro\Supervisor\RedisSupervisor;

final class RedisSupervisorTest extends TestCase
{
    public function testConstructorThrowsWhenRedisMissing(): void
    {
        if (extension_loaded('redis')) {
            $this->markTestSkipped('Redis extension is available.');
        }

        $this->expectException(RuntimeException::class);
        new RedisSupervisor();
    }

    public function testHeartbeatWritesKeyWithTtl(): void
    {
        $client = $this->createRedisClient();
        if ($client === null) {
            return;
        }

        $prefix = 'swoole-micro:test:' . bin2hex(random_bytes(4)) . ':';
        $supervisor = $this->createSupervisor($prefix);
        $workerName = 'worker';
        $instanceId = 'instance';

        try {
            $supervisor->heartbeat($workerName, $instanceId, 5);

            $key = $prefix . $workerName . ':' . $instanceId;
            $value = $client->get($key);

            $this->assertNotFalse($value);
            $this->assertGreaterThan(0, $client->ttl($key));
        } finally {
            $client->del($prefix . $workerName . ':' . $instanceId);
        }
    }

    public function testHeartbeatWithoutTtlDoesNotExpire(): void
    {
        $client = $this->createRedisClient();
        if ($client === null) {
            return;
        }

        $prefix = 'swoole-micro:test:' . bin2hex(random_bytes(4)) . ':';
        $supervisor = $this->createSupervisor($prefix);
        $workerName = 'worker';
        $instanceId = 'no-ttl';

        try {
            $supervisor->heartbeat($workerName, $instanceId, 0);
            $key = $prefix . $workerName . ':' . $instanceId;
            $ttl = $client->ttl($key);

            $this->assertSame(-1, $ttl);
        } finally {
            $client->del($prefix . $workerName . ':' . $instanceId);
        }
    }

    public function testDeadWorkersDetectsStaleHeartbeat(): void
    {
        $client = $this->createRedisClient();
        if ($client === null) {
            return;
        }

        $prefix = 'swoole-micro:test:' . bin2hex(random_bytes(4)) . ':';
        $supervisor = $this->createSupervisor($prefix);
        $workerName = 'worker';
        $instanceId = 'stale';

        try {
            $key = $prefix . $workerName . ':' . $instanceId;
            $client->set($key, (string) (time() - 100));

            $dead = $supervisor->deadWorkers(15);

            $this->assertContains($workerName . ':' . $instanceId, $dead);
        } finally {
            $client->del($prefix . $workerName . ':' . $instanceId);
        }
    }

    public function testDeadWorkersSkipsFreshHeartbeat(): void
    {
        $client = $this->createRedisClient();
        if ($client === null) {
            return;
        }

        $prefix = 'swoole-micro:test:' . bin2hex(random_bytes(4)) . ':';
        $supervisor = $this->createSupervisor($prefix);
        $workerName = 'worker';
        $instanceId = 'fresh';

        try {
            $key = $prefix . $workerName . ':' . $instanceId;
            $client->set($key, (string) time());

            $dead = $supervisor->deadWorkers(30);

            $this->assertNotContains($workerName . ':' . $instanceId, $dead);
        } finally {
            $client->del($prefix . $workerName . ':' . $instanceId);
        }
    }

    private function createSupervisor(string $prefix): RedisSupervisor
    {
        return new RedisSupervisor(
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
