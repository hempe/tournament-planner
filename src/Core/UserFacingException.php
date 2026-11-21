<?php

declare(strict_types=1);

namespace TP\Core;

/** An error whose message is meant for the user: a translation key plus parameters. */
final class UserFacingException extends \RuntimeException
{
    /** @param array<string, scalar> $params */
    public function __construct(
        public readonly string $key,
        public readonly array $params = [],
    ) {
        parent::__construct(__($key, $params));
    }
}
