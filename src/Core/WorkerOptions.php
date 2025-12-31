<?php

declare(strict_types=1);

namespace SwooleMicro\Core;

final class WorkerOptions
{
    private int $concurrency = 1;
    private int $timeoutSeconds = 30;
    private int $memoryLimitMb = 256;
    private int $heartbeatSeconds = 5;

    public static function new(): self
    {
        return new self();
    }

    public function concurrency(int $concurrency): self
    {
        $this->concurrency = max(1, $concurrency);
        return $this;
    }

    public function timeoutSeconds(int $timeoutSeconds): self
    {
        $this->timeoutSeconds = max(1, $timeoutSeconds);
        return $this;
    }

    public function memoryLimitMb(int $memoryLimitMb): self
    {
        $this->memoryLimitMb = max(16, $memoryLimitMb);
        return $this;
    }

    public function heartbeatSeconds(int $heartbeatSeconds): self
    {
        $this->heartbeatSeconds = max(1, $heartbeatSeconds);
        return $this;
    }

    public function getConcurrency(): int
    {
        return $this->concurrency;
    }

    public function getTimeoutSeconds(): int
    {
        return $this->timeoutSeconds;
    }

    public function getMemoryLimitMb(): int
    {
        return $this->memoryLimitMb;
    }

    public function getHeartbeatSeconds(): int
    {
        return $this->heartbeatSeconds;
    }
}
