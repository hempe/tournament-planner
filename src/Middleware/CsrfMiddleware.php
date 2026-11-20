<?php

declare(strict_types=1);

namespace TP\Middleware;

use TP\Core\Config;
use TP\Core\HttpMethod;
use TP\Core\HttpStatus;
use TP\Core\MiddlewareInterface;
use TP\Core\Request;
use TP\Core\Response;

/**
 * Double-submit CSRF protection for the JSON API.
 *
 * Every response carries a readable XSRF-TOKEN cookie holding the session's token.
 * Non-safe requests must echo it in the X-XSRF-TOKEN header.
 */
final class CsrfMiddleware implements MiddlewareInterface
{
    public const SESSION_KEY = 'xsrf_token';
    public const COOKIE_NAME = 'XSRF-TOKEN';
    public const HEADER_NAME = 'X-XSRF-TOKEN';

    private const SAFE_METHODS = [HttpMethod::GET, HttpMethod::HEAD, HttpMethod::OPTIONS];

    public function handle(Request $request, callable $next): Response
    {
        $expected = self::token();

        if (!in_array($request->getMethod(), self::SAFE_METHODS, true)) {
            $sent = $request->getHeader(self::HEADER_NAME) ?? '';
            if (!hash_equals($expected, $sent)) {
                return $this->withTokenCookie(Response::apiError('errors.csrf', HttpStatus::FORBIDDEN));
            }
        }

        // Read the token again: the handler may have reset the session (logout).
        return $this->withTokenCookie($next($request));
    }

    public static function token(): string
    {
        $token = $_SESSION[self::SESSION_KEY] ?? null;
        if (!is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            $_SESSION[self::SESSION_KEY] = $token;
        }
        return $token;
    }

    private function withTokenCookie(Response $response): Response
    {
        return $response->withCookie(
            self::COOKIE_NAME,
            self::token(),
            httpOnly: false,
            secure: (bool) Config::getInstance()->get('app.secure', false),
        );
    }
}
