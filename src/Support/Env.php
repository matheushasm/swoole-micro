<?php

declare(strict_types=1);

namespace SwooleMicro\Support;

final class Env
{
    public static function get(string $key, mixed $default = null): mixed
    {
        $value = getenv($key);
        return $value === false ? $default : $value;
    }
}
