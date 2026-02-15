<?php

declare(strict_types=1);

namespace TP\Core;

use function PHPUnit\Framework\returnArgument;

final class Url
{
    public static function build(string $url): string
    {
        // Preserve iframe parameter
        if (strpos($url, "iframe=") !== false)
            return $url;

        if (isset($_GET['iframe']) && $_GET['iframe'] === '1') {
            $url .= (strpos($url, '?') !== false ? '&' : '?') . 'iframe=1';
        }

        return $url;
    }
}