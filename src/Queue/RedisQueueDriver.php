<?php

declare(strict_types=1);

namespace SwooleMicro\Queue;

use SwooleMicro\Support\Json;

final class RedisQueueDriver implements QueueDriverInterface
{
    private string $host;
    private int $port;
    private int $db;
    private string $prefix;
    private string $username;
    private string $password;
    private ?\Redis $client = null;
    /** @var array<int, \Redis> */
    private array $coroutineClients = [];

    public function __construct(
        string $host = '127.0.0.1',
        int $port = 6379,
        int $db = 0,
        string $prefix = 'swoole-micro:',
        string $username = 'default',
        string $password = ''
    ) {
        if (!extension_loaded('redis')) {
            throw new \RuntimeException('Redis extension is required for RedisQueueDriver.');
        }

        $this->host = $host;
        $this->port = $port;
        $this->db = $db;
        $this->prefix = $prefix;
        $this->username = $username;
        $this->password = $password;
    }

    public function push(string $queue, mixed $payload): string
    {
        $jobId = bin2hex(random_bytes(8));
        $payloadJson = Json::encode($payload);

        $this->client()->hSet($this->jobsKey($queue), $jobId, $payloadJson);
        $this->client()->rPush($this->queueKey($queue), $jobId);

        return $jobId;
    }

    public function pop(string $queue): ?array
    {
        $jobId = $this->client()->brPopLPush(
            $this->queueKey($queue),
            $this->processingKey($queue),
            1
        );

        if ($jobId === false || $jobId === null) {
            return null;
        }

        $payloadJson = $this->client()->hGet($this->jobsKey($queue), (string) $jobId);
        $payload = null;

        if ($payloadJson !== false && $payloadJson !== null) {
            $payload = Json::decode((string) $payloadJson);
        }

        return [
            'id' => (string) $jobId,
            'payload' => $payload,
        ];
    }

    public function ack(string $queue, string $jobId): void
    {
        $this->client()->lRem($this->processingKey($queue), $jobId, 1);
        $this->client()->hDel($this->jobsKey($queue), $jobId);
    }

    private function client(): \Redis
    {
        if (class_exists(\Swoole\Coroutine::class)) {
            $cid = \Swoole\Coroutine::getCid();
            if ($cid > 0) {
                return $this->clientForCoroutine($cid);
            }
        }

        if ($this->client === null) {
            $this->client = $this->createClient();
        }

        return $this->client;
    }

    private function clientForCoroutine(int $cid): \Redis
    {
        if (!isset($this->coroutineClients[$cid])) {
            $this->coroutineClients[$cid] = $this->createClient();
        }

        return $this->coroutineClients[$cid];
    }

    private function createClient(): \Redis
    {
        $client = new \Redis();
        $client->connect($this->host, $this->port);
        $this->authenticate($client);
        if ($this->db > 0) {
            $client->select($this->db);
        }

        return $client;
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

    private function queueKey(string $queue): string
    {
        return $this->prefix . 'queue:' . $queue;
    }

    private function processingKey(string $queue): string
    {
        return $this->prefix . 'processing:' . $queue;
    }

    private function jobsKey(string $queue): string
    {
        return $this->prefix . 'jobs:' . $queue;
    }
}
