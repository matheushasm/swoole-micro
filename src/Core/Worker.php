<?php

declare(strict_types=1);

namespace SwooleMicro\Core;

use SwooleMicro\Queue\QueueDriverInterface;
use SwooleMicro\Supervisor\SupervisorInterface;

final class Worker
{
    private string $name;
    private string $instanceId;
    private ?QueueDriverInterface $queueDriver = null;
    private ?string $queueName = null;
    private ?ProcessorInterface $processor = null;
    private WorkerOptions $options;
    private ?SupervisorInterface $supervisor = null;
    private bool $running = true;

    private function __construct(string $name)
    {
        $this->name = $name;
        $this->instanceId = gethostname() . '-' . getmypid() . '-' . bin2hex(random_bytes(3));
        $this->options = WorkerOptions::new();
    }

    public static function make(string $name): self
    {
        return new self($name);
    }

    public function queue(QueueDriverInterface $queueDriver, string $queueName): self
    {
        $this->queueDriver = $queueDriver;
        $this->queueName = $queueName;
        return $this;
    }

    public function processor(ProcessorInterface $processor): self
    {
        $this->processor = $processor;
        return $this;
    }

    public function options(WorkerOptions $options): self
    {
        $this->options = $options;
        return $this;
    }

    public function supervisor(SupervisorInterface $supervisor): self
    {
        $this->supervisor = $supervisor;
        return $this;
    }

    public function stop(): void
    {
        $this->running = false;
    }

    public function run(): void
    {
        $this->assertConfigured();

        if (!extension_loaded('swoole')) {
            throw new \RuntimeException('Swoole extension is required for Worker.');
        }

        $runner = function (): void {
            $this->startHeartbeatLoop();
            $this->startWorkers();
        };

        if (\Swoole\Coroutine::getCid() === -1) {
            \Swoole\Coroutine\run($runner);
            return;
        }

        $runner();
    }

    private function assertConfigured(): void
    {
        if ($this->queueDriver === null || $this->queueName === null) {
            throw new \RuntimeException('Queue driver and queue name must be configured.');
        }

        if ($this->processor === null) {
            throw new \RuntimeException('Processor must be configured.');
        }
    }

    private function startHeartbeatLoop(): void
    {
        if ($this->supervisor === null) {
            return;
        }

        $interval = $this->options->getHeartbeatSeconds();
        $ttlSeconds = max(1, $interval * 3);

        \Swoole\Coroutine::create(function () use ($interval, $ttlSeconds): void {
            while ($this->running) {
                $this->supervisor?->heartbeat($this->name, $this->instanceId, $ttlSeconds);
                \Swoole\Coroutine::sleep($interval);
            }
        });
    }

    private function startWorkers(): void
    {
        $concurrency = $this->options->getConcurrency();

        for ($i = 0; $i < $concurrency; $i++) {
            \Swoole\Coroutine::create(function (): void {
                while ($this->running) {
                    $job = $this->queueDriver?->pop($this->queueName ?? '');
                    if ($job === null) {
                        continue;
                    }

                    $this->handleJob($job);

                    if ($this->memoryLimitReached()) {
                        $this->running = false;
                    }
                }
            });
        }

        while ($this->running) {
            \Swoole\Coroutine::sleep(0.2);
        }
    }

    private function handleJob(array $job): void
    {
        try {
            $this->processor?->handle($job['payload'] ?? null);
            $this->queueDriver?->ack($this->queueName ?? '', (string) ($job['id'] ?? ''));
        } catch (\Throwable $exception) {
            error_log(sprintf('Processor failed for worker %s: %s', $this->name, $exception->getMessage()));
        }
    }

    private function memoryLimitReached(): bool
    {
        $limitBytes = $this->options->getMemoryLimitMb() * 1024 * 1024;
        return memory_get_usage(true) >= $limitBytes;
    }
}
