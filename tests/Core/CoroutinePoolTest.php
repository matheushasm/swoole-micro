<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use SwooleMicro\Core\CoroutinePool;

final class CoroutinePoolTest extends TestCase
{
    public function testMaxConcurrencyClamps(): void
    {
        if (!extension_loaded('swoole')) {
            $this->markTestSkipped('Swoole extension is required.');
        }

        $self = $this;
        $items = range(1, 2);

        Swoole\Coroutine\run(function () use ($self, $items): void {
            $channel = new Swoole\Coroutine\Channel(count($items));

            CoroutinePool::make()
                ->maxConcurrency(0)
                ->run($items, function (int $item) use ($channel): void {
                    $channel->push($item);
                });

            $collected = [];
            for ($i = 0; $i < count($items); $i++) {
                $collected[] = $channel->pop();
            }

            sort($collected);
            $self->assertSame($items, $collected);
        });
    }

    public function testProcessesAllItems(): void
    {
        if (!extension_loaded('swoole')) {
            $this->markTestSkipped('Swoole extension is required.');
        }

        $items = range(1, 6);
        $self = $this;

        Swoole\Coroutine\run(function () use ($items, $self): void {
            $channel = new Swoole\Coroutine\Channel(count($items));

            CoroutinePool::make()
                ->maxConcurrency(3)
                ->run($items, function (int $item) use ($channel): void {
                    $channel->push($item);
                });

            $collected = [];
            for ($i = 0; $i < count($items); $i++) {
                $collected[] = $channel->pop();
            }

            sort($collected);
            $self->assertSame($items, $collected);
        });
    }
}
