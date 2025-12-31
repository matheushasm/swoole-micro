<?php

declare(strict_types=1);

namespace Examples\Processors;

use SwooleMicro\Core\ProcessorInterface;

final class SendEmailProcessor implements ProcessorInterface
{
    public function handle(mixed $payload): void
    {
        if (!is_array($payload) || empty($payload['to'])) {
            throw new \InvalidArgumentException('Invalid payload: missing "to".');
        }

        \Swoole\Coroutine::sleep(0.1);

        echo sprintf("Email sent to %s\n", $payload['to']);
    }
}
