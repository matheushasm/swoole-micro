<?php

declare(strict_types=1);

namespace SwooleMicro\Supervisor;

final class RedisSupervisor implements SupervisorInterface
{
    private string $host;
    private int $port;
    private int $db;
    private string $prefix;
    private string $username;
    private string $password;
    private ?\Redis $client = null;

    public function __construct(
        string $host = '127.0.0.1',
        int $port = 6379,
        int $db = 0,
        string $prefix = 'swoole-micro:',
        string $username = 'default',
        string $password = ''
    )
    {
        if (!extension_loaded('redis')) {
            throw new \RuntimeException('Redis extension is required for RedisSupervisor.');
        }

        $this->host = $host;
        $this->port = $port;
        $this->db = $db;
        $this->prefix = $prefix;
        $this->username = $username;
        $this->password = $password;
    }

    public function heartbeat(string $workerName, string $instanceId, int $ttlSeconds = 0): void
    {
        $key = $this->key($workerName, $instanceId);
        $timestamp = (string) time();
        if ($ttlSeconds > 0) {
            $this->client()->setex($key, $ttlSeconds, $timestamp);
            return;
        }

        $this->client()->set($key, $timestamp);
    }

    public function deadWorkers(int $thresholdSeconds): array
    {
        $thresholdSeconds = max(1, $thresholdSeconds);
        $now = time();
        $keys = $this->client()->keys($this->prefix . '*');
        $dead = [];

        foreach ($keys as $key) {
            $value = $this->client()->get($key);
            if ($value === false) {
                continue;
            }

            $timestamp = (int) $value;
            if ($now - $timestamp >= $thresholdSeconds) {
                $dead[] = $this->stripPrefix($key);
            }
        }

        return $dead;
    }

    private function client(): \Redis
    {
        if ($this->client === null) {
            $this->client = new \Redis();
            $this->client->connect($this->host, $this->port);
            $this->authenticate($this->client);
            if ($this->db > 0) {
                $this->client->select($this->db);
            }
        }

        return $this->client;
    }

    private function authenticate(\Redis $client): void
    {
        if ($this->password === '') {
            return;
        }

        if ($this->username !== '' && $this->username !== 'default') {
            $client->auth([$this->username, $this->password]);
            return;
        }

        $client->auth($this->password);
    }

    private function key(string $workerName, string $instanceId): string
    {
        return $this->prefix . $workerName . ':' . $instanceId;
    }

    private function stripPrefix(string $key): string
    {
        return str_starts_with($key, $this->prefix)
            ? substr($key, strlen($this->prefix))
            : $key;
    }
}
