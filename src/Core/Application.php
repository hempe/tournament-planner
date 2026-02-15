<?php
declare(strict_types=1);
namespace TP\Core;

use TP\Models\DB;
use TP\Core\Router;
use Exception;
use Throwable;

final class Application
{
    private static ?Application $instance = null;
    private Config $config;
    private LoggerInterface $logger;
    private Router $router;
    private bool $booted = false;

    private function __construct()
    {
        $this->config = Config::getInstance();
        $logLevel = $this->config->get('logging.level', 'INFO');
        if (is_array($logLevel)) {
            $logLevel = 'INFO';
        }
        $this->logger = new Logger(
            $this->config->get('logging.file', 'php://stderr'),
            LogLevel::from($logLevel)
        );
        $this->router = new Router();
    }

    public static function getInstance(): Application
    {
        if (self::$instance === null) {
            self::$instance = new Application();
        }
        return self::$instance;
    }

    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        $this->logger->info('Booting application');

        // Set error reporting based on environment
        if ($this->config->isDevelopment()) {
            error_reporting(E_ALL);
            ini_set('display_errors', '1');
        } else {
            error_reporting(E_ERROR | E_WARNING | E_PARSE);
            ini_set('display_errors', '0');
        }

        // Set timezone
        date_default_timezone_set($this->config->get('app.timezone', 'UTC'));

        // Start session with secure settings
        $this->configureSession();

        // Set locale from session or config
        $this->configureLocale();

        // Initialize database
        // Initialize database (allow graceful failure)
        try {
            DB::initialize();
            $this->logger->info('Database initialized successfully');
        } catch (Exception $e) {
            $this->logger->warning('Database initialization failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            // Continue without database - some endpoints may still work
        }

        $this->booted = true;
        $this->logger->info('Application booted successfully');
    }

    public function getRouter(): Router
    {
        return $this->router;
    }

    public function getConfig(): Config
    {
        return $this->config;
    }

    public function getLogger(): LoggerInterface
    {
        return $this->logger;
    }

    public function run(): void
    {
        $this->boot();

        try {
            $request = Request::createFromGlobals();
            $this->logger->debug('Processing request', [
                'method' => $request->getMethod()->value,
                'uri' => $request->getUri(),
                'ip' => $request->getIp(),
            ]);

            $response = $this->router->handle($request);

            $this->logger->debug('Sending response', [
                'status' => $response->getStatus()->value,
                'is_redirect' => $response->isRedirect(),
            ]);

            $response->send();

        } catch (Throwable $e) {
            $this->logger->critical('Application error', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->handleError($e);
        }
    }

    private function configureSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            // Secure session configuration
            ini_set('session.cookie_httponly', '1');
            ini_set('session.cookie_secure', $this->config->get('app.secure', false) ? '1' : '0');
            ini_set('session.cookie_samesite', 'Strict');
            ini_set('session.use_strict_mode', '1');
            ini_set('session.gc_maxlifetime', (string) $this->config->get('security.session_lifetime', 3600));

            session_start();

            // Regenerate session ID periodically
            if (!isset($_SESSION['last_regeneration'])) {
                $_SESSION['last_regeneration'] = time();
            } elseif (time() - $_SESSION['last_regeneration'] > 300) { // 5 minutes
                session_regenerate_id(true);
                $_SESSION['last_regeneration'] = time();
            }
        }
    }

    private function configureLocale(): void
    {
        // Get locale from session or fall back to config
        $locale = $_SESSION['locale'] ?? $this->config->get('app.locale', 'de');

        // Set the locale in the translator
        Translator::getInstance()->setLocale($locale);
    }

    private function handleError(Throwable $e): void
    {
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/html; charset=UTF-8');
        }

        if ($this->config->isDevelopment()) {
            echo $this->renderDebugError($e);
        } else {
            echo $this->renderProductionError();
        }
    }

    private function renderDebugError(Throwable $e): string
    {
        return sprintf(
            '<h1>Application Error</h1><h2>%s</h2><p><strong>File:</strong> %s:%d</p><pre>%s</pre>',
            htmlspecialchars($e->getMessage()),
            htmlspecialchars($e->getFile()),
            $e->getLine(),
            htmlspecialchars($e->getTraceAsString())
        );
    }

    private function renderProductionError(): string
    {
        return '<h1>Internal Server Error</h1><p>Something went wrong. Please try again later.</p>';
    }
}