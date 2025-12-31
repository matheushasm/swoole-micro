<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use SwooleMicro\Core\WorkerOptions;

final class WorkerOptionsTest extends TestCase
{
    public function testDefaults(): void
    {
        $options = WorkerOptions::new();

        $this->assertSame(1, $options->getConcurrency());
        $this->assertSame(30, $options->getTimeoutSeconds());
        $this->assertSame(256, $options->getMemoryLimitMb());
        $this->assertSame(5, $options->getHeartbeatSeconds());
    }

    public function testMinimumClamps(): void
    {
        $options = WorkerOptions::new()
            ->concurrency(0)
            ->timeoutSeconds(0)
            ->memoryLimitMb(1)
            ->heartbeatSeconds(0);

        $this->assertSame(1, $options->getConcurrency());
        $this->assertSame(1, $options->getTimeoutSeconds());
        $this->assertSame(16, $options->getMemoryLimitMb());
        $this->assertSame(1, $options->getHeartbeatSeconds());
    }
}
