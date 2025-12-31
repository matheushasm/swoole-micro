<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Examples\Processors\SendEmailProcessor;
use SwooleMicro\Core\Worker;
use SwooleMicro\Core\WorkerOptions;
use SwooleMicro\Queue\InMemoryQueueDriver;

Swoole\Coroutine\run(function (): void {
    $queue = new InMemoryQueueDriver();

    $queue->push('default', ['to' => 'dev@example.com']);
    $queue->push('default', ['to' => 'test@example.com']);

    $worker = Worker::make('default')
        ->queue($queue, 'default')
        ->processor(new SendEmailProcessor())
        ->options(WorkerOptions::new()->concurrency(2));

    Swoole\Coroutine::create(function () use ($queue, $worker): void {
        while (true) {
            if ($queue->isEmpty('default')) {
                $worker->stop();
                return;
            }

            Swoole\Coroutine::sleep(0.05);
        }
    });

    $worker->run();
});
