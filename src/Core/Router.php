<?php

require_once dirname(__FILE__) . '/Log.php';

function pass(Closure|bool $require)
{
    if ($require instanceof Closure) {
        $reflection = new ReflectionFunction($require);
        $parameters = $reflection->getParameters();

        // Extract named parameters
        $params = extractParameters($parameters);
        if (!$require->__invoke(...$params)) {
            Log::trace('pass($require:Closure)', 'did not pass');
            return false;
        }
    } elseif (!$require) {
        Log::trace('pass($require:bool)', 'did not pass');
        return false;
    }

    return true;
}

/**
 * Extracts parameters from the request based on the reflection parameters.
 *
 * @param ReflectionParameter[] $parameters The parameters to extract.
 * @return array The extracted parameters.
 */
function extractParameters(array $parameters): array
{
    $params = [];
    foreach ($parameters as $parameter) {
        $name = $parameter->getName();
        if (isset($_GET[$name])) {
            $params[$name] = $_GET[$name];
        } elseif (isset($_POST[$name])) {
            $params[$name] = $_POST[$name];
        } elseif ($parameter->isDefaultValueAvailable()) {
            $params[$name] = $parameter->getDefaultValue();
        } else {
            throw new InvalidArgumentException("Missing required parameter: $name");
        }
    }
    return $params;
}

// Initialize the dictionary for storing scripts
$scripts_dict = [];

function add_script(string $name, string $script): void
{
    global $scripts_dict;
    $scripts_dict[$name] = $script;
}


class Route
{
    private readonly string $id_template;
    private readonly array $id_keys;

    public function __construct(
        private string $method,
        private readonly  string $route,
        private readonly Closure $apply,
        private readonly string $title,
        private readonly string $header,
        private readonly string $footer,
        private readonly Closure|bool $require,
    ) {

        $this->method = strtoupper($method);
        $this->id_template = $this->generateRegexFromTemplate($route);
        $this->id_keys = $this->extractKeysFromTemplate($route);
    }

    private function generateRegexFromTemplate(string $template): string
    {
        // Replace placeholders with the corresponding regex pattern for numbers
        $pattern = preg_replace('/\{(\w+)\}/', '(?P<$1>\d+)', $template);

        // Escape any forward slashes
        $pattern = str_replace('/', '\/', $pattern);

        // Ensure it correctly matches both with and without a leading slash, and make trailing slash optional
        $pattern = '^\/?' . $pattern . '\/?$';

        return '/' . $pattern . '/';
    }

    // Method to extract the keys from the template
    private function extractKeysFromTemplate(string $template): array
    {
        // Match placeholders within curly braces, like {id}, {otherId}
        preg_match_all('/\{(\w+)\}/', $template, $matches);

        // Return the keys (e.g., ['id', 'otherId'])
        return $matches[1];
    }


    public function routeTo(string $request): bool
    {
        Log::trace('Route', "[$this->method] $this->route check");

        // Remove query parameters from $request to get the path only
        $parsedUrl = parse_url($request);
        $path = $parsedUrl['path'] ?? '';

        // Use the generated regex pattern from the route's template
        $pattern = $this->id_template;

        // Check if the request path matches the route pattern
        if (preg_match($pattern, $path, $matches)) {
            // Log and set the matched parameters to $_GET
            foreach ($matches as $key => $value) {
                if ($key !== 0) { // Skip the full match (index 0)
                    // Set each captured key (from id_keys) to $_GET
                    if (in_array($key, $this->id_keys)) {
                        $_GET[$key] = $value;
                        Log::trace('Route', "[$this->method] $this->route matched: $key = $value");
                    }
                }
            }

            if (!pass($this->require)) {
                return false;
            }

            // Log successful match and render the route
            Log::trace('Route', "[$this->method] $this->route Route matched: $path");
            $this->render($request);
            return true;
        }

        return false; // Return false if no match found
    }

    private function render(string $request): void
    {
        Log::trace('Route', "[$this->method] $this->route render");

        ob_start();
        $this->apply->__invoke($request);
        $output = ob_get_clean();

        global $title;
        $title = $this->title;
        global $scripts;
        global $scripts_dict;

        $scripts = implode('', array_map(function ($script) {
            return "<script>$script</script>";
        }, $scripts_dict));

        require_once $this->header;
        echo $output;
        require_once $this->footer;
    }
}

abstract class RouteItem
{
    function __construct(
        public readonly string $method,
        public readonly string $route,
        public readonly string $title,
        public readonly Closure|bool $require,
    ) {
        Log::trace('RouteItem', "Adding [$method]: $route");
    }

    public abstract function apply(string $request): void;
}

class RouterView extends RouteItem
{

    public function __construct(
        string $route,
        private readonly string $file,
        string $title,
        Closure|bool $require,
    ) {
        parent::__construct('GET', $route, $title, $require);
    }

    public function apply(string $request): void
    {
        if (isset($_SESSION['popup_error'])) {
            $error = json_encode($_SESSION['popup_error']);
            echo <<<HTML
                <script> document.addEventListener('DOMContentLoaded',  () => customError($error));</script>
            HTML;
            unset($_SESSION['popup_error']);
        }

        require $this->file;
    }
}

class RouterAction extends RouteItem
{
    public function __construct(
        string $route,
        private readonly string $redirect,
        string $name,
        private readonly Closure $action,
        string $title,
        Closure|bool $require,
    ) {
        $postFix = $name ? '/' . $name : '';
        parent::__construct('POST', $route . $postFix, $title, $require);
    }

    public function apply(string $request): void
    {
        Log::trace('RouterAction', "$this->route Start invoke.");
        try {
            // Use reflection to get the parameters required by the action
            $reflection = new ReflectionFunction($this->action);
            $parameters = $reflection->getParameters();

            // Extract named parameters
            $params = extractParameters($parameters);

            // Call the action with named parameters
            $this->action->__invoke(...$params);
            Log::trace('RouterAction', "$this->route Called invoke success.");
        } catch (Error $e) {
            Log::error('RouterAction', "$this->route Failed to invoke: " . $e->getMessage());
            $_SESSION['popup_error'] = $e->getMessage();
        } catch (Exception $e) {
            Log::error('RouterAction', "$this->route Failed to invoke: " . $e->getMessage());
            $_SESSION['popup_error'] = $e->getMessage();
        } finally {
            // Replace placeholders in $redirect with values from $_GET
            $redirect = $this->redirect;

            // Use regex to find all placeholders like {id}, {otherId}
            $redirect = preg_replace_callback('/\{(\w+)\}/', function ($matches) {
                // Check if the key exists in $_GET, otherwise return the placeholder itself
                $key = $matches[1];
                return isset($_GET[$key]) ? $_GET[$key] : $matches[0];  // Default to original placeholder if not found
            }, $redirect);

            // Get the referer URL
            $referer = $_SERVER['HTTP_REFERER'] ?? '';

            // Parse the referer URL to get query parameters and fragment
            $parsedUrl = parse_url($referer);
            $query = isset($parsedUrl['query']) ? $parsedUrl['query'] : '';
            $fragment = isset($parsedUrl['fragment']) ? '#' . $parsedUrl['fragment'] : '';

            // Append query parameters and fragment to the redirect URL
            if ($query) {
                $redirect .= (strpos($redirect, '?') === false ? '?' : '&') . $query;
            }
            $redirect .= $fragment;

            Log::trace('RouterAction', "$this->route Redirect to: $redirect");
            header('Location: /' . $redirect, true, 303);
            exit;
        }
    }
}

class RouterBuilder
{
    /** @var RouteItem[] $routes */
    public array $routes = [];
    private readonly string $method;

    public function __construct(
        private readonly  string $route_prefix,
        private readonly string $file_prefix,
        string $method
    ) {
        $this->method = strtoupper($method);
    }

    /**
     * Renders the specified view.
     *
     * @param string $file The name of the view to render.
     * @param string $title The title of the view.
     * @param Closure|bool $require Whether to require the view or not.
     * @param string $route The route (optional).
     * @param array<string, array{callback: callable, require?: Closure|bool}>|null $actions
     * An associative array of actions where each key is a string and the value is an array
     * containing a `callback` (callable) and an optional `require` (Closure or bool).
     * @return RouterBuilder
     */
    public function view(
        string $file,
        string $title,
        Closure|bool $require,
        string $route = '',
        array|null $actions = null,
    ): RouterBuilder {
        if ($this->method == 'GET') {
            $this->routes[] = new RouterView(
                $this->route_prefix . $route,
                $this->file_prefix . '/views/' . $file,
                $title,
                $require,
            );
        } else if ($this->method == 'POST') {
            if ($actions !== null) {
                foreach ($actions as $name => $action) {
                    $redirect = $this->route_prefix . $route;
                    $actionRequire = $require;

                    if (is_callable($action)) {
                        // Simple case: just a callable, use default $require
                        $callback = $action;
                    } elseif (isset($action['callback']) && is_callable($action['callback'])) {
                        // Advanced case: action with callback and optional 'require'
                        $callback = $action['callback'];
                        if (isset($action['require']))
                            $actionRequire = fn() => pass($require) && pass($action['require']);
                        if (isset($action['redirect']))
                            $redirect = $this->route_prefix . $action['redirect'];
                    } else {
                        throw new InvalidArgumentException("Action '$name' is not callable.");
                    }

                    $this->routes[] = new RouterAction(
                        $this->route_prefix . $route,
                        $redirect,
                        $name,
                        $callback,
                        $title,
                        $actionRequire
                    );
                }
            }
        }
        return $this;
    }
}

class Router
{
    /** @var Route[] $routes */
    private array $routes = [];

    public function __construct(
        string $view_header,
        string $view_footer,
        RouterBuilder $builder
    ) {
        foreach ($builder->routes as $route) {
            $this->routes[] = new Route(
                method: $route->method,
                route: $route->route,
                apply: fn(string $request) => $route->apply($request),
                title: $route->title,
                header: $view_header,
                footer: $view_footer,
                require: $route->require
            );
        }
    }

    public function route(string $request): bool
    {
        Log::trace('Router', 'Request path: ' . $request);
        foreach ($this->routes as $route) {
            if ($route->routeTo($request)) {
                return  true;
            }
        }

        return false;
    }
}
