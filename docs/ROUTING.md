# Routing System Documentation

The routing system is attribute-based: routes are defined via PHP 8 attributes on controller classes and methods. A `RouteLoader` scans all controllers at startup and registers the discovered routes with the `Router`.

## Defining Routes

### Route Prefix

Apply a URL prefix to all routes in a controller:

```php
#[RoutePrefix('/events')]
final class EventController
{
    // ...
}
```

### HTTP Method Attributes

Use `#[Get]`, `#[Post]`, `#[Put]`, or `#[Delete]` on controller methods:

```php
#[Get('/')]
public function index(Request $request): Response { ... }

#[Get('/{id}')]
public function detail(Request $request, array $params): Response
{
    $eventId = (int) $params['id'];
    // ...
}

#[Post('/{id}/register')]
public function register(Request $request, array $params): Response { ... }
```

URL parameters (e.g. `{id}`) are matched as `[^/]+` and injected via the `$params` array. Methods without URL parameters omit the `$params` argument.

### Middleware Attribute

Apply middleware at the class or method level:

```php
#[RoutePrefix('/events')]
#[Middleware(AuthMiddleware::class)]   // applies to all methods
final class EventController
{
    #[Get('/new')]
    #[Middleware(AdminMiddleware::class)]  // additionally requires admin
    public function create(Request $request): Response { ... }
}
```

When applied to the class, the middleware runs for every route in that controller. When applied to a method, it runs in addition to any class-level middleware.

## Available Middleware

### AuthMiddleware

Redirects unauthenticated users to `/login` (303):

```php
final class AuthMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        if (!User::loggedIn()) {
            return Response::redirect('/login');
        }
        return $next($request);
    }
}
```

### AdminMiddleware

Returns 403 Forbidden for non-admin users:

```php
final class AdminMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        if (!User::admin()) {
            return Response::forbidden();
        }
        return $next($request);
    }
}
```

## Closure-Based Routes

Simple routes that don't belong to a controller can be registered directly on the router (e.g. in `index.php`). The `/health` endpoint uses this pattern:

```php
$router->get('/health', function (Request $request): Response {
    return Response::json(['status' => 'ok']);
});
```

## Route Loading

Routes are discovered automatically at startup:

```php
// index.php
$routeLoader = new RouteLoader();
$routeLoader->load($router);
```

`RouteLoader` uses `ControllerDiscovery` to find all PHP files under `src/Controllers/` and `RouteScanner` to read their attributes. Route caching can be enabled via config:

```php
// .env or config
routing.cache_enabled = true
routing.cache_file = storage/cache/routes.php
```

When caching is enabled, scanned routes are serialized to disk. The cache is invalidated automatically when any controller file changes.

## Request Object

**File**: `src/Core/Request.php`

```php
$request->getMethod(): HttpMethod
$request->getPath(): string
$request->get(string $key, mixed $default = null): mixed
$request->getString(string $key, string $default = ''): string
$request->getInt(string $key, int $default = 0): int
$request->getBool(string $key, bool $default = false): bool
$request->getArray(string $key, array $default = []): array
$request->validate(array $rules): ValidationResult
$request->getValidatedData(): array
```

### Validation

```php
$validation = $request->validate([
    new ValidationRule('name', ['required', 'string', 'max' => 255]),
    new ValidationRule('capacity', ['required', 'integer', 'min' => 1]),
]);

if (!$validation->isValid) {
    flash('error', $validation->getErrorMessages());
    return Response::redirect("/events/{$eventId}");
}

$data = $request->getValidatedData();
```

## Response Object

**File**: `src/Core/Response.php`

```php
Response::ok(string $content = ''): Response          // 200
Response::redirect(string $url): Response             // 303
Response::json(array|object $data): Response          // 200 application/json
Response::unauthorized(): Response                    // 401
Response::notFound(string $message = ''): Response    // 404 text/plain
Response::forbidden(): Response                       // 403 text/plain
```

`notFound()`, `forbidden()` and `unauthorized()` return a plain-text body; API controllers use `Response::apiError()` for JSON errors.

## Registered Routes

All application routes are JSON endpoints under `/api` (see [API.md](API.md)),
plus the public `GET /health`. Every other URL is an Angular route served by `.htaccess` (`index.html`).
