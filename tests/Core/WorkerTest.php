<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use SwooleMicro\Core\ProcessorInterface;
use SwooleMicro\Core\Worker;
use SwooleMicro\Queue\QueueDriverInterface;

final class WorkerTest extends TestCase
{
    public function testRunThrowsWhenQueueMissing(): void
    {
        $worker = Worker::make('missing-queue')
            ->processor(new class implements ProcessorInterface {
                public function handle(mixed $payload): void
                {
                }
            });

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Queue driver and queue name must be configured.');

        $worker->run();
    }

    public function testRunThrowsWhenProcessorMissing(): void
    {
        $worker = Worker::make('missing-processor')
            ->queue(new class implements QueueDriverInterface {
                public function push(string $queue, mixed $payload): string
                {
                    return 'job';
                }

                public function pop(string $queue): ?array
                {
                    return null;
                }

                public function ack(string $queue, string $jobId): void
                {
                }
            }, 'default');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Processor must be configured.');

        $worker->run();
    }
}
