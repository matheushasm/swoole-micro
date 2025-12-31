<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use SwooleMicro\Support\Json;

final class JsonTest extends TestCase
{
    public function testEncodeDecodeRoundTrip(): void
    {
        $payload = ['name' => 'swoole', 'count' => 2];
        $encoded = Json::encode($payload);
        $decoded = Json::decode($encoded);

        $this->assertSame($payload, $decoded);
    }

    public function testDecodeThrowsOnInvalidJson(): void
    {
        $this->expectException(JsonException::class);
        Json::decode('{invalid');
    }

    public function testEncodeThrowsOnInvalidUtf8(): void
    {
        $this->expectException(JsonException::class);
        $value = "bad\xB1";
        Json::encode(['bad' => $value]);
    }
}
