<?php

declare(strict_types=1);

namespace SwooleMicro\Support;

final class Json
{
    public static function encode(mixed $value): string
    {
        $json = json_encode($value, JSON_THROW_ON_ERROR);
        return $json;
    }

    public static function decode(string $value, bool $assoc = true): mixed
    {
        return json_decode($value, $assoc, 512, JSON_THROW_ON_ERROR);
    }
}
