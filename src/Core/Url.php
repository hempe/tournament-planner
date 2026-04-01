<?php

declare(strict_types=1);

namespace TP\Core;

use function PHPUnit\Framework\returnArgument;

final class Url
{
    public static function build(string|null $url): string
    {
        $url = $url ?? '';
        return $url;
    }
}