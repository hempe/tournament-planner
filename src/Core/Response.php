<?php

declare(strict_types=1);

namespace TP\Core;

enum HttpStatus: int
{
    case OK = 200;
    case CREATED = 201;
    case NO_CONTENT = 204;
    case MOVED_PERMANENTLY = 301;
    case FOUND = 302;
    case SEE_OTHER = 303;
    case NOT_MODIFIED = 304;
    case TEMPORARY_REDIRECT = 307;
    case PERMANENT_REDIRECT = 308;
    case BAD_REQUEST = 400;
    case UNAUTHORIZED = 401;
    case FORBIDDEN = 403;
    case NOT_FOUND = 404;
    case METHOD_NOT_ALLOWED = 405;
    case CONFLICT = 409;
    case UNPROCESSABLE_ENTITY = 422;
    case INTERNAL_SERVER_ERROR = 500;
}

final class Response
{
    private array $headers = [];
    private array $cookies = [];

    /** @var array{status: int, headers: array<string, string>, cookies: array<string, array>}|null */
    private static ?array $lastSent = null;


    public function __construct(
        private string $content = '',
        private HttpStatus $status = HttpStatus::OK,
        array $headers = []
    ) {
        $this->headers = $headers;
    }

    public static function ok(string $content = '', array $headers = []): Response
    {
        return new Response($content, HttpStatus::OK, $headers);
    }

    public static function redirect(string $url, HttpStatus $status = HttpStatus::SEE_OTHER): Response
    {
        return new Response('', $status, ['Location' => Url::build($url)]);
    }

    public static function json(array $data, HttpStatus $status = HttpStatus::OK): Response
    {
        return new Response(
            json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            $status,
            ['Content-Type' => 'application/json']
        );
    }

    /**
     * API error response. $key and the keys inside $fields are translation keys;
     * the frontend translates them.
     *
     * @param array<string, array{key: string, params?: array<string, scalar>}> $fields
     */
    public static function apiError(string $key, HttpStatus $status, array $fields = []): Response
    {
        $body = ['error' => $key];
        if ($fields !== []) {
            $body['fields'] = $fields;
        }
        return self::json($body, $status);
    }

    public static function noContent(): Response
    {
        return new Response('', HttpStatus::NO_CONTENT);
    }

    public static function notFound(string $message = 'Not Found'): Response
    {
        ob_start();
        require __DIR__ . '/../Layout/header.php';
        require __DIR__ . '/../Views/Errors/404.php';
        require __DIR__ . '/../Layout/footer.php';
        $content = ob_get_clean();

        return new Response($content, HttpStatus::NOT_FOUND);
    }

    public static function forbidden(string $message = 'Forbidden'): Response
    {
        ob_start();
        require __DIR__ . '/../Layout/header.php';
        require __DIR__ . '/../Views/Errors/403.php';
        require __DIR__ . '/../Layout/footer.php';
        $content = ob_get_clean();

        return new Response($content, HttpStatus::FORBIDDEN);
    }

    public static function unauthorized(string $message = 'Unauthorized'): Response
    {
        return new Response($message, HttpStatus::UNAUTHORIZED);
    }

    public function getStatus(): HttpStatus
    {
        return $this->status;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    /** @return array<string, string> */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /** @return array<string, array{value: string, path: string, secure: bool, httponly: bool, samesite: string}> */
    public function getCookies(): array
    {
        return $this->cookies;
    }

    public function withHeader(string $name, string $value): Response
    {
        $clone = clone $this;
        $clone->headers[$name] = $value;
        return $clone;
    }

    public function withCookie(
        string $name,
        string $value,
        bool $httpOnly = true,
        bool $secure = false,
        string $sameSite = 'Strict',
    ): Response {
        $clone = clone $this;
        $clone->cookies[$name] = [
            'value' => $value,
            'path' => '/',
            'secure' => $secure,
            'httponly' => $httpOnly,
            'samesite' => $sameSite,
        ];
        return $clone;
    }

    /**
     * The last response passed to send(). Used by integration tests, because the
     * CLI SAPI does not expose sent headers.
     *
     * @return array{status: int, headers: array<string, string>, cookies: array<string, array>}|null
     */
    public static function lastSent(): ?array
    {
        return self::$lastSent;
    }

    public static function resetLastSent(): void
    {
        self::$lastSent = null;
    }

    public function send(): void
    {
        self::$lastSent = [
            'status' => $this->status->value,
            'headers' => $this->headers,
            'cookies' => $this->cookies,
        ];

        if (!headers_sent()) {
            http_response_code($this->status->value);

            foreach ($this->headers as $name => $value) {
                header("{$name}: {$value}");
            }

            foreach ($this->cookies as $name => $options) {
                setcookie($name, $options['value'], [
                    'expires' => 0,
                    'path' => $options['path'],
                    'secure' => $options['secure'],
                    'httponly' => $options['httponly'],
                    'samesite' => $options['samesite'],
                ]);
            }
        }

        echo $this->content;
    }

    public function isRedirect(): bool
    {
        return in_array($this->status, [
            HttpStatus::MOVED_PERMANENTLY,
            HttpStatus::FOUND,
            HttpStatus::SEE_OTHER,
            HttpStatus::TEMPORARY_REDIRECT,
            HttpStatus::PERMANENT_REDIRECT,
        ], true);
    }
}