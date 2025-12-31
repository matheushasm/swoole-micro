<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use SwooleMicro\Core\BatchProcessor;

final class BatchProcessorTest extends TestCase
{
    public function testSequentialProcessingWithoutSwoole(): void
    {
        $collector = new class extends BatchProcessor {
            public array $items = [];

            protected function processItem(mixed $item, mixed $payload): void
            {
                $this->items[] = $item;
            }
        };

        $collector->handle(['items' => [1, 2, 3]]);

        $this->assertSame([1, 2, 3], $collector->items);
    }

    public function testUsesListPayloadWhenItemsMissing(): void
    {
        $collector = new class extends BatchProcessor {
            public array $items = [];

            protected function processItem(mixed $item, mixed $payload): void
            {
                $this->items[] = $item;
            }
        };

        $collector->handle([10, 20]);

        $this->assertSame([10, 20], $collector->items);
    }

    public function testParallelProcessingWithSwoole(): void
    {
        if (!extension_loaded('swoole')) {
            $this->markTestSkipped('Swoole extension is required.');
        }

        $collector = new class extends BatchProcessor {
            public array $items = [];

            protected function maxConcurrency(mixed $payload): int
            {
                return 2;
            }

            protected function processItem(mixed $item, mixed $payload): void
            {
                $this->items[] = $item;
            }
        };

        Swoole\Coroutine\run(function () use ($collector): void {
            $collector->handle(['items' => [1, 2, 3, 4]]);
        });

        sort($collector->items);
        $this->assertSame([1, 2, 3, 4], $collector->items);
    }

    public function testOverridesConcurrencyFluently(): void
    {
        $collector = new class extends BatchProcessor {
            public array $items = [];

            protected function maxConcurrency(mixed $payload): int
            {
                return 1;
            }

            protected function processItem(mixed $item, mixed $payload): void
            {
                $this->items[] = $item;
            }
        };

        if (extension_loaded('swoole')) {
            Swoole\Coroutine\run(function () use ($collector): void {
                $collector->concurrency(3)->handle(['items' => [1, 2]]);
            });
        } else {
            $collector->concurrency(1)->handle(['items' => [1, 2]]);
        }

        $this->assertSame([1, 2], $collector->items);
    }
}
