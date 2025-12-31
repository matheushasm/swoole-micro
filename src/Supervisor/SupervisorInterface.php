<?php

declare(strict_types=1);

namespace SwooleMicro\Supervisor;

interface SupervisorInterface
{
    public function heartbeat(string $workerName, string $instanceId, int $ttlSeconds = 0): void;

    /**
     * @return array<int, string> worker instance identifiers
     */
    public function deadWorkers(int $thresholdSeconds): array;
}
