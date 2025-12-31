<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use SwooleMicro\Core\ProcessorInterface;

final class RedisCounterProcessor implements ProcessorInterface
{
    private string $host;
    private int $port;
    private int $db;
    private string $prefix;
    private string $username;
    private string $password;
    private string $tag;

    public function __construct()
    {
        $this->host = getenv('REDIS_HOST') ?: '127.0.0.1';
        $this->port = (int) (getenv('REDIS_PORT') ?: 6379);
        $this->db = (int) (getenv('REDIS_DB') ?: 0);
        $this->prefix = getenv('REDIS_PREFIX') ?: 'swoole-micro:test:';
        $this->username = getenv('REDIS_USERNAME') ?: 'default';
        $this->password = getenv('REDIS_PASSWORD') ?: '';
        $this->tag = getenv('PROCESSOR_TAG') ?: 'default';
    }

    public function handle(mixed $payload): void
    {
        $client = new \Redis();
        $client->connect($this->host, $this->port);

        if ($this->password !== '') {
            if ($this->username !== '' && $this->username !== 'default') {
                $client->auth([$this->username, $this->password]);
            } else {
                $client->auth($this->password);
            }
        }

        if ($this->db > 0) {
            $client->select($this->db);
        }

        $key = $this->prefix . 'processed:' . $this->tag;
        $client->incr($key);
    }
}
