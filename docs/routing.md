# Routing System Documentation

The Golf El Faro application features a modern, strongly-typed routing system with middleware support, parameter injection, and comprehensive security features.

## Architecture Overview

The routing system consists of several key Components:

- **Router**: Main routing engine that matches requests to handlers
- **Route**: Individual route definition with pattern, handler, and middleware
- **Middleware**: Request/response processing pipeline
- **Request/Response**: Strongly-typed HTTP abstraction
- **Application**: Main application container

## Core Components

### Router Class

**File**: `src/Core/RouterNew.php`

The `Router` class is the main routing engine that handles request matching and response generation.

```php
final class Router
{
    public function get(string $pattern, callable $handler, array $middleware = [], string $name = ''): void;
    public function post(string $pattern, callable $handler, array $middleware = [], string $name = ''): void;
    public function put(string $pattern, callable $handler, array $middleware = [], string $name = ''): void;
    public function delete(string $pattern, callable $handler, array $middleware = [], string $name = ''): void;
    public function group(RouteGroup $group, callable $callback): void;
    public function handle(Request $request): Response;
}
```

### Route Patterns

Routes support parameter placeholders using curly braces:

```php
$router->get('/events/{id}', $handler);           // Matches /events/123
$router->get('/users/{id}/posts/{postId}', $handler); // Matches /users/1/posts/456
```

**Pattern Rules**:
- Parameters are captured as named groups
- Parameters match any non-slash characters (`[^/]+`)
- Parameters are automatically validated and injected

## HTTP Methods

### GET Routes
Used for retrieving data and displaying pages:

```php
$router->get('/', function(Request $request): Response {
    return Response::ok(new HomePage());
});

$router->get('/events', function(Request $request): Response {
    $events = DB::$events->getAll();
    return Response::ok(new EventsPage($events));
});
```

### POST Routes
Used for form submissions and data creation:

```php
$router->post('/events', function(Request $request): Response {
    $validation = $request->validate([
        new ValidationRule('name', ['required', 'string', 'max' => 255]),
        new ValidationRule('date', ['required', 'date']),
        new ValidationRule('capacity', ['required', 'integer', 'min' => 1]),
    ]);
    
    if (!$validation->isValid) {
        return Response::error(HttpStatus::UNPROCESSABLE_ENTITY, $validation->getErrorMessages());
    }
    
    $data = $request->getValidatedData();
    $eventId = DB::$events->create($data);
    
    return Response::redirect("/events/{$eventId}");
});
```

### RESTful Routes
Support for REST operations:

```php
$router->get('/api/events', $listHandler);          // List events
$router->post('/api/events', $createHandler);       // Create event
$router->get('/api/events/{id}', $showHandler);     // Show event
$router->put('/api/events/{id}', $updateHandler);   // Update event
$router->delete('/api/events/{id}', $deleteHandler); // Delete event
```

## Middleware System

Middleware provides a powerful way to filter and process HTTP requests:

### Available Middleware

#### AuthMiddleware
Ensures user is authenticated:

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

#### AdminMiddleware
Requires admin privileges:

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

#### CsrfMiddleware
Validates CSRF tokens for POST requests:

```php
$router->post('/events', $handler, [new CsrfMiddleware()]);
```

#### RateLimitMiddleware
Limits request frequency:

```php
$router->group(
    new RouteGroup('', [new RateLimitMiddleware(100, 60)]),
    function(Router $router) {
        // API routes with rate limiting
    }
);
```

### Custom Middleware

Create custom middleware by implementing `MiddlewareInterface`:

```php
final class LoggingMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        $start = microtime(true);
        $response = $next($request);
        $duration = microtime(true) - $start;
        
        Logger::getInstance()->info('Request processed', [
            'method' => $request->getMethod()->value,
            'path' => $request->getPath(),
            'status' => $response->getStatus()->value,
            'duration' => $duration,
        ]);
        
        return $response;
    }
}
```

## Route Groups

Group routes with common middleware or URL prefixes:

```php
// Admin routes with authentication and admin middleware
$router->group(
    new RouteGroup('/admin', [new AuthMiddleware(), new AdminMiddleware()]),
    function(Router $router) {
        $router->get('/events', $adminEventsHandler);
        $router->get('/users', $adminUsersHandler);
        $router->post('/users', $createUserHandler);
    }
);

// API routes with rate limiting
$router->group(
    new RouteGroup('/api', [new RateLimitMiddleware(1000, 3600)]),
    function(Router $router) {
        $router->get('/events', $apiEventsHandler);
        $router->get('/events/{id}', $apiEventHandler);
    }
);
```

## Request Handling

### Request Object

**File**: `src/Core/Request.php`

The `Request` class provides strongly-typed access to HTTP request data:

```php
final class Request
{
    public function getMethod(): HttpMethod;
    public function getUri(): string;
    public function getPath(): string;
    public function get(string $key, mixed $default = null): mixed;
    public function getString(string $key, string $default = ''): string;
    public function getInt(string $key, int $default = 0): int;
    public function getBool(string $key, bool $default = false): bool;
    public function getArray(string $key, array $default = []): array;
    public function validate(array $rules): ValidationResult;
}
```

### Parameter Access

```php
$router->get('/events/{id}', function(Request $request, array $params): Response {
    $eventId = (int)$params['id'];
    $event = DB::$events->get($eventId);
    
    if (!$event) {
        return Response::notFound();
    }
    
    return Response::ok(new EventDetailPage($event));
});
```

### Form Data Processing

```php
$router->post('/events/{id}/register', function(Request $request, array $params): Response {
    $eventId = (int)$params['id'];
    
    $validation = $request->validate([
        new ValidationRule('comment', ['string', 'max' => 500]),
        new ValidationRule('user_id', ['required', 'integer']),
    ]);
    
    if (!$validation->isValid) {
        return Response::json(['errors' => $validation->getErrorsByField()], HttpStatus::UNPROCESSABLE_ENTITY);
    }
    
    $data = $request->getValidatedData();
    DB::$events->register($eventId, $data['user_id'], $data['comment'] ?? '');
    
    return Response::redirect("/events/{$eventId}");
});
```

## Response Handling

### Response Object

**File**: `src/Core/Response.php`

The `Response` class provides strongly-typed HTTP responses:

```php
final class Response
{
    public static function ok(string $content = '', array $headers = []): Response;
    public static function created(string $content = '', array $headers = []): Response;
    public static function redirect(string $url, HttpStatus $status = HttpStatus::SEE_OTHER): Response;
    public static function json(array $data, HttpStatus $status = HttpStatus::OK): Response;
    public static function error(HttpStatus $status, string $message = ''): Response;
    public static function notFound(string $message = 'Not Found'): Response;
    public static function forbidden(string $message = 'Forbidden'): Response;
    public static function unauthorized(string $message = 'Unauthorized'): Response;
}
```

### Response Types

#### HTML Responses
```php
return Response::ok(new EventListPage($events));
```

#### JSON Responses
```php
return Response::json([
    'events' => $events,
    'total' => count($events),
]);
```

#### Redirects
```php
return Response::redirect('/events');
return Response::redirect('/login', HttpStatus::TEMPORARY_REDIRECT);
```

#### Error Responses
```php
return Response::notFound('Event not found');
return Response::forbidden('Access denied');
return Response::error(HttpStatus::INTERNAL_SERVER_ERROR, 'Something went wrong');
```

## Route Definition Examples

### Basic Routes

```php
// Home page
$router->get('/', function(Request $request): Response {
    if (!User::loggedIn()) {
        return Response::ok(new LoginPage());
    }
    
    $events = DB::$events->getUpcoming();
    return Response::ok(new HomePage($events));
});

// Login form
$router->post('/login', function(Request $request): Response {
    $validation = $request->validate([
        new ValidationRule('username', ['required', 'string']),
        new ValidationRule('password', ['required', 'string']),
    ]);
    
    if (!$validation->isValid) {
        return Response::redirect('/login');
    }
    
    $data = $request->getValidatedData();
    $user = User::authenticate($data['username'], $data['password']);
    
    if ($user) {
        User::setCurrent($user);
        return Response::redirect('/');
    }
    
    return Response::redirect('/login');
});
```

### Event Management Routes

```php
// Events listing
$router->get('/events', function(Request $request): Response {
    $events = DB::$events->getAll();
    return Response::ok(new EventListPage($events));
}, [new AuthMiddleware()]);

// Event creation form
$router->get('/events/new', function(Request $request): Response {
    return Response::ok(new EventCreatePage());
}, [new AuthMiddleware(), new AdminMiddleware()]);

// Event creation handler
$router->post('/events', function(Request $request): Response {
    $validation = $request->validate([
        new ValidationRule('name', ['required', 'string', 'max' => 255]),
        new ValidationRule('date', ['required', 'date']),
        new ValidationRule('capacity', ['required', 'integer', 'min' => 1]),
    ]);
    
    if (!$validation->isValid) {
        return Response::redirect('/events/new');
    }
    
    $data = $request->getValidatedData();
    $eventId = DB::$events->create($data);
    
    return Response::redirect("/events/{$eventId}");
}, [new AuthMiddleware(), new AdminMiddleware()]);

// Event detail page
$router->get('/events/{id}', function(Request $request, array $params): Response {
    $eventId = (int)$params['id'];
    $event = DB::$events->get($eventId, User::id());
    
    if (!$event) {
        return Response::notFound();
    }
    
    if (User::admin()) {
        return Response::ok(new EventAdminPage($event));
    }
    
    return Response::ok(new EventDetailPage($event));
}, [new AuthMiddleware()]);
```

## Error Handling

### Route-Level Error Handling

```php
$router->get('/events/{id}', function(Request $request, array $params): Response {
    try {
        $eventId = (int)$params['id'];
        $event = DB::$events->get($eventId);
        
        if (!$event) {
            return Response::notFound(__('events.not_found'));
        }
        
        return Response::ok(new EventDetailPage($event));
        
    } catch (DatabaseException $e) {
        Logger::getInstance()->error('Database error in event detail', [
            'event_id' => $eventId,
            'error' => $e->getMessage(),
        ]);
        
        return Response::error(HttpStatus::INTERNAL_SERVER_ERROR, __('errors.database'));
        
    } catch (Throwable $e) {
        Logger::getInstance()->error('Unexpected error in event detail', [
            'event_id' => $eventId,
            'error' => $e->getMessage(),
        ]);
        
        return Response::error(HttpStatus::INTERNAL_SERVER_ERROR, __('errors.general'));
    }
});
```

### Global Error Handling

The application automatically handles uncaught exceptions and provides appropriate error responses based on the environment.

## Security Features

### CSRF Protection
All POST requests are automatically protected against CSRF attacks:

```php
// CSRF token automatically validated for POST requests
$router->post('/events', $handler); // Automatically includes CSRF validation
```

### Rate Limiting
Global and route-specific rate limiting:

```php
// Global rate limiting (100 requests per minute)
$app->getRouter()->addGlobalMiddleware(new RateLimitMiddleware(100, 60));

// Route-specific rate limiting
$router->post('/api/events', $handler, [new RateLimitMiddleware(10, 60)]);
```

### Input Sanitization
All request data is automatically sanitized:

- Null bytes removed
- Line endings normalized
- Whitespace trimmed

### Parameter Validation
Strongly-typed parameter access with validation:

```php
$eventId = $request->getInt('id'); // Always returns an integer
$name = $request->getString('name'); // Always returns a string
$isActive = $request->getBool('active'); // Always returns a boolean
```

## Performance Considerations

### Route Caching
For production, consider caching compiled routes:

```php
// Compile routes to cache
$compiledRoutes = $router->compile();
file_put_contents('cache/routes.php', serialize($compiledRoutes));
```

### Middleware Optimization
Order middleware for optimal performance:

1. Rate limiting (fastest rejection)
2. Authentication (lightweight check)
3. Authorization (more expensive check)
4. CSRF validation (token comparison)

### Lazy Loading
Use lazy loading for expensive operations:

```php
$router->get('/events', function(Request $request): Response {
    $events = lazy(fn() => DB::$events->getAll());
    return Response::ok(new EventListPage($events));
});
```

## Testing Routes

### Unit Testing

```php
public function testEventDetailRoute(): void
{
    $request = new Request(HttpMethod::GET, '/events/123', [], [], [], []);
    $router = new Router();
    $router->get('/events/{id}', $this->eventDetailHandler);
    
    $response = $router->handle($request);
    
    $this->assertEquals(HttpStatus::OK, $response->getStatus());
}
```

### Integration Testing

```php
public function testEventCreationFlow(): void
{
    $this->actingAs($this->adminUser);
    
    $response = $this->post('/events', [
        'name' => 'Test Event',
        'date' => '2024-12-25',
        'capacity' => 20,
        '_token' => csrf_token(),
    ]);
    
    $this->assertRedirect('/events/1');
    $this->assertDatabaseHas('events', ['name' => 'Test Event']);
}
```