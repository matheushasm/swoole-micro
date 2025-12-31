<?php

declare(strict_types=1);

namespace Examples\Processors;

use SwooleMicro\Core\ProcessorInterface;

final class FetchUrlsProcessor implements ProcessorInterface
{
    public function handle(mixed $payload): void
    {
        if (!is_array($payload) || empty($payload['url'])) {
            throw new \InvalidArgumentException('Invalid payload: missing "url".');
        }

        \Swoole\Coroutine::sleep(0.05);
        echo sprintf("Fetched %s\n", $payload['url']);
    }
}
