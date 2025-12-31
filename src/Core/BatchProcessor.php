<?php

declare(strict_types=1);

namespace SwooleMicro\Core;

abstract class BatchProcessor implements ProcessorInterface
{
    private ?int $overrideConcurrency = null;

    public function concurrency(int $concurrency): self
    {
        $this->overrideConcurrency = max(1, $concurrency);
        return $this;
    }

    final public function handle(mixed $payload): void
    {
        $items = $this->itemsFromPayload($payload);
        $concurrency = $this->overrideConcurrency ?? $this->maxConcurrency($payload);
        $concurrency = max(1, $concurrency);

        if ($items === []) {
            return;
        }

        if ($concurrency === 1) {
            foreach ($items as $item) {
                $this->processItem($item, $payload);
            }
            return;
        }

        CoroutinePool::make()
            ->maxConcurrency($concurrency)
            ->run($items, function (mixed $item) use ($payload): void {
                $this->processItem($item, $payload);
            });
    }

    /**
     * Override to decide parallelism per payload.
     */
    protected function maxConcurrency(mixed $payload): int
    {
        return 1;
    }

    /**
     * Override to extract items from the payload.
     *
     * @return array<int, mixed>
     */
    protected function itemsFromPayload(mixed $payload): array
    {
        if (!is_array($payload)) {
            return [];
        }

        if (isset($payload['items']) && is_array($payload['items'])) {
            return $payload['items'];
        }

        if (array_is_list($payload)) {
            return $payload;
        }

        return [];
    }

    abstract protected function processItem(mixed $item, mixed $payload): void;
}
