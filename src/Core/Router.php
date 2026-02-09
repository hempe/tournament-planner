<?php

declare(strict_types=1);

namespace GolfElFaro\Core;

use GolfElFaro\Models\User;
use Throwable;

interface MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response;
}

final class CsrfMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        if ($request->getMethod() === HttpMethod::POST) {
            $token = $request->getString('_token');
            
            if (!Security::getInstance()->validateCsrfToken($token)) {
                return Response::error(HttpStatus::UNPROCESSABLE_ENTITY, __('errors.csrf'));
            }
        }
        
        return $next($request);
    }
}

final class RateLimitMiddleware implements MiddlewareInterface
{
    public function __construct(
        private int $maxRequests = 60,
        private int $windowSeconds = 60
    ) {}

    public function handle(Request $request, callable $next): Response
    {
        $identifier = $request->getIp();
        
        if (!Security::getInstance()->rateLimitCheck($identifier, $this->maxRequests, $this->windowSeconds)) {
            return Response::error(HttpStatus::TOO_MANY_REQUESTS, 'Rate limit exceeded');
        }
        
        return $next($request);
    }
}

final class Route
{
    /** @param MiddlewareInterface[] $middleware */
    private readonly HttpMethod $method;
    private readonly string $pattern;
    private mixed $handler;
    private readonly array $middleware;
    private readonly string $name;

    /** @param MiddlewareInterface[] $middleware */
    public function __construct(
        HttpMethod $method,
        string $pattern,
        callable|array $handler,
        array $middleware = [],
        string $name = ''
    ) {
        $this->method = $method;
        $this->pattern = $pattern;
        $this->handler = $handler;
        $this->middleware = $middleware;
        $this->name = $name;
    }

    public function getMethod(): HttpMethod
    {
        return $this->method;
    }

    public function getPattern(): string
    {
        return $this->pattern;
    }

    public function getHandler(): callable|array
    {
        return $this->handler;
    }

    /** @return MiddlewareInterface[] */
    public function getMiddleware(): array
    {
        return $this->middleware;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function matches(Request $request): ?array
    {
        if ($request->getMethod() !== $this->method) {
            return null;
        }
        
        $pattern = $this->compilePattern($this->pattern);
        $path = $request->getPath();
        
        if (preg_match($pattern, $path, $matches)) {
            array_shift($matches); // Remove full match
            return $matches;
        }
        
        return null;
    }

    private function compilePattern(string $pattern): string
    {
        // Convert route pattern to regex
        $pattern = preg_replace('/\{(\w+)\}/', '(?P<$1>[^/]+)', $pattern);
        $pattern = str_replace('/', '\/', $pattern);
        return '/^' . $pattern . '$/';
    }
}

final class RouteGroup
{
    /** @param MiddlewareInterface[] $middleware */
    public function __construct(
        private readonly string $prefix = '',
        private readonly array $middleware = []
    ) {}

    public function getPrefix(): string
    {
        return $this->prefix;
    }

    /** @return MiddlewareInterface[] */
    public function getMiddleware(): array
    {
        return $this->middleware;
    }
}

final class Router
{
    /** @var Route[] */
    private array $routes = [];
    
    /** @var MiddlewareInterface[] */
    private array $globalMiddleware = [];
    
    private LoggerInterface $logger;

    public function __construct()
    {
        $this->logger = new Logger();
    }

    public function addGlobalMiddleware(MiddlewareInterface $middleware): void
    {
        $this->globalMiddleware[] = $middleware;
    }

    public function get(string $pattern, callable|array $handler, array $middleware = [], string $name = ''): void
    {
        $this->addRoute(HttpMethod::GET, $pattern, $handler, $middleware, $name);
    }

    public function post(string $pattern, callable|array $handler, array $middleware = [], string $name = ''): void
    {
        $this->addRoute(HttpMethod::POST, $pattern, $handler, $middleware, $name);
    }

    public function put(string $pattern, callable|array $handler, array $middleware = [], string $name = ''): void
    {
        $this->addRoute(HttpMethod::PUT, $pattern, $handler, $middleware, $name);
    }

    public function delete(string $pattern, callable|array $handler, array $middleware = [], string $name = ''): void
    {
        $this->addRoute(HttpMethod::DELETE, $pattern, $handler, $middleware, $name);
    }

    public function group(RouteGroup $group, callable $callback): void
    {
        $previousPrefix = $this->currentPrefix ?? '';
        $previousMiddleware = $this->currentGroupMiddleware ?? [];
        
        $this->currentPrefix = $previousPrefix . $group->getPrefix();
        $this->currentGroupMiddleware = array_merge($previousMiddleware, $group->getMiddleware());
        
        $callback($this);
        
        $this->currentPrefix = $previousPrefix;
        $this->currentGroupMiddleware = $previousMiddleware;
    }

    public function handle(Request $request): Response
    {
        $this->logger->info('Handling request', [
            'method' => $request->getMethod()->value,
            'path' => $request->getPath(),
        ]);

        foreach ($this->routes as $route) {
            $matches = $route->matches($request);
            
            if ($matches !== null) {
                $this->logger->debug('Route matched', [
                    'pattern' => $route->getPattern(),
                    'parameters' => $matches,
                ]);
                
                return $this->executeRoute($route, $request, $matches);
            }
        }
        
        $this->logger->warning('No route matched', [
            'method' => $request->getMethod()->value,
            'path' => $request->getPath(),
        ]);
        
        return Response::notFound();
    }

    private function addRoute(HttpMethod $method, string $pattern, callable|array $handler, array $middleware, string $name): void
    {
        $prefix = $this->currentPrefix ?? '';
        $groupMiddleware = $this->currentGroupMiddleware ?? [];
        
        $fullPattern = $prefix . $pattern;
        $allMiddleware = array_merge($groupMiddleware, $middleware);
        
        $this->routes[] = new Route($method, $fullPattern, $handler, $allMiddleware, $name);
        
        $this->logger->debug('Route registered', [
            'method' => $method->value,
            'pattern' => $fullPattern,
            'middleware_count' => count($allMiddleware),
        ]);
    }

    private function executeRoute(Route $route, Request $request, array $parameters): Response
    {
        // Add route parameters to request
        foreach ($parameters as $key => $value) {
            if (is_string($key)) {
                $request = $this->addParameterToRequest($request, $key, $value);
            }
        }
        
        // Build middleware stack
        $middleware = array_merge($this->globalMiddleware, $route->getMiddleware());
        
        // Create handler that executes the route
        $handler = function(Request $request) use ($route, $parameters): Response {
            try {
                $routeHandler = $route->getHandler();
                
                // Handle array format [ClassName::class, 'method']
                if (is_array($routeHandler)) {
                    [$className, $methodName] = $routeHandler;
                    $instance = new $className();
                    $result = $instance->$methodName($request, $parameters);
                } else {
                    // Handle callable format
                    $result = $routeHandler($request, $parameters);
                }
                
                if ($result instanceof Response) {
                    return $result;
                }
                
                if (is_string($result)) {
                    return Response::ok($result);
                }
                
                if (is_array($result)) {
                    return Response::json($result);
                }
                
                return Response::ok((string)$result);
                
            } catch (Throwable $e) {
                $this->logger->error('Route handler error', [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                ]);
                
                $config = Config::getInstance();
                if ($config->isDevelopment()) {
                    return Response::error(HttpStatus::INTERNAL_SERVER_ERROR, $e->getMessage());
                }
                
                return Response::error(HttpStatus::INTERNAL_SERVER_ERROR, __('errors.general'));
            }
        };
        
        // Execute middleware stack
        return $this->executeMiddlewareStack($middleware, $request, $handler);
    }

    private function executeMiddlewareStack(array $middleware, Request $request, callable $finalHandler): Response
    {
        $stack = array_reduce(
            array_reverse($middleware),
            function (callable $next, MiddlewareInterface $middleware): callable {
                return fn(Request $request): Response => $middleware->handle($request, $next);
            },
            $finalHandler
        );
        
        return $stack($request);
    }

    private function addParameterToRequest(Request $request, string $key, string $value): Request
    {
        // This would require modifying the Request class to support adding parameters
        // For now, we'll use a simple approach with global variables or session
        $_GET[$key] = $value;
        return $request;
    }

    private ?string $currentPrefix = null;
    private ?array $currentGroupMiddleware = null;
}