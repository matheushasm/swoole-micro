<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use SwooleMicro\Support\Env;

final class EnvTest extends TestCase
{
    public function testReturnsDefaultWhenMissing(): void
    {
        $key = 'SWOOLE_MICRO_ENV_TEST_' . bin2hex(random_bytes(4));
        $original = getenv($key);

        if ($original !== false) {
            putenv($key);
        }

        $this->assertSame('fallback', Env::get($key, 'fallback'));

        $this->restoreEnv($key, $original);
    }

    public function testReturnsValueWhenSet(): void
    {
        $key = 'SWOOLE_MICRO_ENV_TEST_' . bin2hex(random_bytes(4));
        $original = getenv($key);

        putenv($key . '=value');
        $_ENV[$key] = 'value';
        $_SERVER[$key] = 'value';

        $this->assertSame('value', Env::get($key, 'fallback'));

        $this->restoreEnv($key, $original);
    }

    public function testReturnsEmptyStringWhenSet(): void
    {
        $key = 'SWOOLE_MICRO_ENV_TEST_' . bin2hex(random_bytes(4));
        $original = getenv($key);

        putenv($key . '=');
        $_ENV[$key] = '';
        $_SERVER[$key] = '';

        $this->assertSame('', Env::get($key, 'fallback'));

        $this->restoreEnv($key, $original);
    }

    private function restoreEnv(string $key, string|false $original): void
    {
        if ($original === false) {
            putenv($key);
            unset($_ENV[$key], $_SERVER[$key]);
            return;
        }

        putenv($key . '=' . $original);
        $_ENV[$key] = $original;
        $_SERVER[$key] = $original;
    }
}
