<?php

declare(strict_types=1);

namespace GolfElFaro\Core;

enum HttpMethod: string
{
    case GET = 'GET';
    case POST = 'POST';
    case PUT = 'PUT';
    case DELETE = 'DELETE';
    case PATCH = 'PATCH';
    case HEAD = 'HEAD';
    case OPTIONS = 'OPTIONS';
}

final class Request
{
    private array $sanitizedData = [];
    private array $validatedData = [];

    public function __construct(
        private readonly HttpMethod $method,
        private readonly string $uri,
        private readonly array $query,
        private readonly array $post,
        private readonly array $server,
        private readonly array $headers,
        private readonly array $files = []
    ) {
        $this->sanitizeInput();
    }

    public static function createFromGlobals(): Request
    {
        $method = HttpMethod::from($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $query = $_GET ?? [];
        $post = $_POST ?? [];
        $server = $_SERVER ?? [];
        $files = $_FILES ?? [];
        
        // Extract headers from server variables
        $headers = [];
        foreach ($server as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $headerName = str_replace('_', '-', substr($key, 5));
                $headers[strtolower($headerName)] = $value;
            }
        }
        
        return new Request($method, $uri, $query, $post, $server, $headers, $files);
    }

    public function getMethod(): HttpMethod
    {
        return $this->method;
    }

    public function getUri(): string
    {
        return $this->uri;
    }

    public function getPath(): string
    {
        return parse_url($this->uri, PHP_URL_PATH) ?? '/';
    }

    public function getQuery(): array
    {
        return $this->sanitizedData['query'] ?? [];
    }

    public function getPost(): array
    {
        return $this->sanitizedData['post'] ?? [];
    }

    public function getAllInput(): array
    {
        return array_merge($this->getQuery(), $this->getPost());
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->getAllInput()[$key] ?? $default;
    }

    public function getString(string $key, string $default = ''): string
    {
        $value = $this->get($key, $default);
        return is_string($value) ? $value : $default;
    }

    public function getInt(string $key, int $default = 0): int
    {
        $value = $this->get($key, $default);
        return is_numeric($value) ? (int)$value : $default;
    }

    public function getBool(string $key, bool $default = false): bool
    {
        $value = $this->get($key, $default);
        if (is_bool($value)) return $value;
        if (is_string($value)) {
            return in_array(strtolower($value), ['1', 'true', 'on', 'yes'], true);
        }
        return (bool)$value;
    }

    public function getArray(string $key, array $default = []): array
    {
        $value = $this->get($key, $default);
        return is_array($value) ? $value : $default;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->getAllInput());
    }

    public function hasFile(string $key): bool
    {
        return isset($this->files[$key]) && $this->files[$key]['error'] === UPLOAD_ERR_OK;
    }

    public function getFile(string $key): ?array
    {
        return $this->files[$key] ?? null;
    }

    public function getHeader(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function isAjax(): bool
    {
        return $this->getHeader('x-requested-with') === 'XMLHttpRequest';
    }

    public function isSecure(): bool
    {
        return $this->server['HTTPS'] ?? false || 
               $this->server['HTTP_X_FORWARDED_PROTO'] ?? '' === 'https';
    }

    public function getUserAgent(): string
    {
        return $this->server['HTTP_USER_AGENT'] ?? '';
    }

    public function getIp(): string
    {
        return $this->server['HTTP_X_FORWARDED_FOR'] ?? 
               $this->server['HTTP_X_REAL_IP'] ?? 
               $this->server['REMOTE_ADDR'] ?? '';
    }

    public function validate(array $rules): ValidationResult
    {
        $validator = new Validator();
        $result = $validator->validate($this->getAllInput(), $rules);
        
        if ($result->isValid) {
            $this->validatedData = array_intersect_key(
                $this->getAllInput(),
                array_flip(array_map(fn($rule) => $rule->field, $rules))
            );
        }
        
        return $result;
    }

    public function getValidatedData(): array
    {
        return $this->validatedData;
    }

    private function sanitizeInput(): void
    {
        $this->sanitizedData = [
            'query' => $this->sanitizeArray($this->query),
            'post' => $this->sanitizeArray($this->post),
        ];
    }

    private function sanitizeArray(array $data): array
    {
        $sanitized = [];
        
        foreach ($data as $key => $value) {
            $sanitizedKey = $this->sanitizeString($key);
            
            if (is_array($value)) {
                $sanitized[$sanitizedKey] = $this->sanitizeArray($value);
            } else {
                $sanitized[$sanitizedKey] = $this->sanitizeString((string)$value);
            }
        }
        
        return $sanitized;
    }

    private function sanitizeString(string $value): string
    {
        // Remove null bytes and normalize line endings
        $value = str_replace("\0", '', $value);
        $value = str_replace(["\r\n", "\r"], "\n", $value);
        
        // Trim whitespace
        return trim($value);
    }
}