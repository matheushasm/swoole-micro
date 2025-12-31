<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use SwooleMicro\Core\CoroutinePool;

$items = range(1, 10);

\Swoole\Coroutine::run(function () use ($items): void {
    CoroutinePool::make()
        ->maxConcurrency(3)
        ->run($items, function (int $item): void {
            \Swoole\Coroutine::sleep(0.05);
            echo "Processed item: {$item}\n";
        });
});
