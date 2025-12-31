<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use SwooleMicro\Queue\InMemoryQueueDriver;

final class InMemoryQueueDriverTest extends TestCase
{
    public function testConstructorThrowsWhenSwooleMissing(): void
    {
        if (extension_loaded('swoole')) {
            $this->markTestSkipped('Swoole extension is available.');
        }

        $this->expectException(RuntimeException::class);
        new InMemoryQueueDriver();
    }

    public function testPushPopAckFlow(): void
    {
        if (!extension_loaded('swoole')) {
            $this->markTestSkipped('Swoole extension is required.');
        }

        $self = $this;

        Swoole\Coroutine\run(function () use ($self): void {
            $driver = new InMemoryQueueDriver();

            $self->assertTrue($driver->isEmpty('alpha'));

            $jobId = $driver->push('alpha', ['foo' => 'bar']);
            $job = $driver->pop('alpha');

            $self->assertSame($jobId, $job['id']);
            $self->assertSame(['foo' => 'bar'], $job['payload']);

            $driver->ack('alpha', $jobId);
            $self->assertTrue($driver->isEmpty('alpha'));
        });
    }
}
