<?php
namespace TP\Core;

final class Log
{
    public static function trace(string $name, string $message): void
    {
        error_log("TRACE: [" . $name . "] $message");
    }

    public static function error(string $name, string $message): void
    {
        error_log("ERROR: [" . $name . "] $message");
    }
}
