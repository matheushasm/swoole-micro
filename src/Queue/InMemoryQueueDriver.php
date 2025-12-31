<?php

declare(strict_types=1);

namespace SwooleMicro\Queue;

use Swoole\Coroutine\Channel;

final class InMemoryQueueDriver implements QueueDriverInterface
{
    private array $queues = [];
    private array $channels = [];
    private int $bufferSize;

    public function __construct(int $bufferSize = 1024)
    {
        if (!extension_loaded('swoole')) {
            throw new \RuntimeException('Swoole extension is required for InMemoryQueueDriver.');
        }

        $this->bufferSize = max(1, $bufferSize);
    }

    public function push(string $queue, mixed $payload): string
    {
        $jobId = bin2hex(random_bytes(8));
        $job = [
            'id' => $jobId,
            'payload' => $payload,
        ];

        $this->queues[$queue][] = $job;
        $this->channelFor($queue)->push(true);

        return $jobId;
    }

    public function pop(string $queue): ?array
    {
        $channel = $this->channelFor($queue);

        while (true) {
            if (!empty($this->queues[$queue])) {
                return array_shift($this->queues[$queue]);
            }

            $channel->pop();
        }
    }

    public function ack(string $queue, string $jobId): void
    {
        // No-op for in-memory driver.
    }

    public function isEmpty(string $queue): bool
    {
        return empty($this->queues[$queue]);
    }

    private function channelFor(string $queue): Channel
    {
        if (!isset($this->channels[$queue])) {
            $this->channels[$queue] = new Channel($this->bufferSize);
        }

        return $this->channels[$queue];
    }
}
