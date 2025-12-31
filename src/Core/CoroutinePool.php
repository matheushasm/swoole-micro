<?php

declare(strict_types=1);

namespace SwooleMicro\Core;

use Swoole\Coroutine\Channel;
use Swoole\Coroutine\WaitGroup;

final class CoroutinePool
{
    private int $maxConcurrency = 1;

    public static function make(): self
    {
        return new self();
    }

    public function maxConcurrency(int $maxConcurrency): self
    {
        $this->maxConcurrency = max(1, $maxConcurrency);
        return $this;
    }

    public function run(iterable $items, callable $handler): void
    {
        if (!extension_loaded('swoole')) {
            throw new \RuntimeException('Swoole extension is required for CoroutinePool.');
        }

        $semaphore = new Channel($this->maxConcurrency);
        for ($i = 0; $i < $this->maxConcurrency; $i++) {
            $semaphore->push(true);
        }

        $waitGroup = new WaitGroup();

        foreach ($items as $item) {
            $semaphore->pop();
            $waitGroup->add();

            \Swoole\Coroutine::create(function () use ($handler, $item, $semaphore, $waitGroup): void {
                try {
                    $handler($item);
                } finally {
                    $semaphore->push(true);
                    $waitGroup->done();
                }
            });
        }

        $waitGroup->wait();
    }
}
