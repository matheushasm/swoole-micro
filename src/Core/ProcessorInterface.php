<?php

declare(strict_types=1);

namespace SwooleMicro\Core;

interface ProcessorInterface
{
    public function handle(mixed $payload): void;
}
