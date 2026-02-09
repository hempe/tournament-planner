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
    case UNPROCESSABLE_ENTITY = 422;
    case INTERNAL_SERVER_ERROR = 500;
}

final class Response
{
    private array $headers = [];
    private array $cookies = [];

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

    public static function created(string $content = '', array $headers = []): Response
    {
        return new Response($content, HttpStatus::CREATED, $headers);
    }

    public static function redirect(string $url, HttpStatus $status = HttpStatus::SEE_OTHER): Response
    {
        return new Response('', $status, ['Location' => $url]);
    }

    public static function json(array $data, HttpStatus $status = HttpStatus::OK): Response
    {
        return new Response(
            json_encode($data, JSON_UNESCAPED_UNICODE),
            $status,
            ['Content-Type' => 'application/json']
        );
    }

    public static function error(HttpStatus $status, string $message = ''): Response
    {
        return new Response($message, $status);
    }

    public static function notFound(string $message = 'Not Found'): Response
    {
        return new Response($message, HttpStatus::NOT_FOUND);
    }

    public static function forbidden(string $message = 'Forbidden'): Response
    {
        return new Response($message, HttpStatus::FORBIDDEN);
    }

    public static function unauthorized(string $message = 'Unauthorized'): Response
    {
        return new Response($message, HttpStatus::UNAUTHORIZED);
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getStatus(): HttpStatus
    {
        return $this->status;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function withHeader(string $name, string $value): Response
    {
        $response = clone $this;
        $response->headers[$name] = $value;
        return $response;
    }

    public function withCookie(string $name, string $value, array $options = []): Response
    {
        $response = clone $this;
        $response->cookies[$name] = array_merge([
            'value' => $value,
            'expire' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => false,
            'httponly' => true,
        ], $options);
        return $response;
    }

    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->status->value);

            foreach ($this->headers as $name => $value) {
                header("{$name}: {$value}");
            }

            foreach ($this->cookies as $name => $options) {
                setcookie(
                    $name,
                    $options['value'],
                    $options['expire'],
                    $options['path'],
                    $options['domain'],
                    $options['secure'],
                    $options['httponly']
                );
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

    public function isError(): bool
    {
        return $this->status->value >= 400;
    }
}