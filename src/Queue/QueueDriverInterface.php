<?php

declare(strict_types=1);

namespace SwooleMicro\Queue;

interface QueueDriverInterface
{
    public function push(string $queue, mixed $payload): string;

    public function pop(string $queue): ?array;

    public function ack(string $queue, string $jobId): void;
}
