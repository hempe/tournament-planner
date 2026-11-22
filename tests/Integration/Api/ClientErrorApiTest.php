<?php

declare(strict_types=1);

namespace TP\Tests\Integration\Api;

use TP\Core\Application;
use TP\Core\Logger;
use TP\Core\LoggerInterface;
use TP\Core\LogLevel;
use TP\Models\DB;
use TP\Tests\Integration\IntegrationTestCase;

class ClientErrorApiTest extends IntegrationTestCase
{
    private string $logFile;
    private LoggerInterface $originalLogger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanDatabase();

        // Capture the application log in a temp file for this test
        $this->logFile = sys_get_temp_dir() . '/tp-test-log-' . bin2hex(random_bytes(6)) . '.log';
        $app = Application::getInstance();
        $this->originalLogger = $app->getLogger();
        $this->swapLogger(new Logger($this->logFile, LogLevel::DEBUG));
    }

    private function swapLogger(LoggerInterface $logger): void
    {
        (new \ReflectionProperty(Application::class, 'logger'))->setValue(Application::getInstance(), $logger);
    }

    protected function tearDown(): void
    {
        $this->swapLogger($this->originalLogger);
        if (is_file($this->logFile)) {
            unlink($this->logFile);
        }
        unset($_SERVER['HTTP_USER_AGENT'], $_SERVER['REMOTE_ADDR']);
        parent::tearDown();
    }

    /** Client error log lines written since setUp. */
    private function loggedReports(): array
    {
        $log = $this->log();
        $reports = [];
        foreach (explode("\n", $log) as $line) {
            if (str_contains($line, 'ERROR: Client error ')) {
                $reports[] = json_decode(substr($line, strpos($line, '{')), true);
            }
        }
        return $reports;
    }

    private function log(): string
    {
        return is_file($this->logFile) ? (string) file_get_contents($this->logFile) : '';
    }

    private function body(array $override = []): array
    {
        return array_merge([
            'message' => 'TypeError: x is undefined',
            'stack' => "TypeError: x is undefined\n    at foo (main.js:1:2)",
            'url' => 'https://example.com/events/3',
            'appVersion' => '1.2.3',
        ], $override);
    }

    private function report(array $override = []): \TP\Tests\Integration\TestResponse
    {
        $this->json('GET', '/api/auth/me');
        return $this->json('POST', '/api/client-errors', $this->body($override));
    }

    public function testReportIsLoggedAndAnswersWith204(): void
    {
        $_SERVER['REMOTE_ADDR'] = '203.0.113.5';
        $_SERVER['HTTP_USER_AGENT'] = 'TestBrowser/1.0';

        $response = $this->report();

        $this->assertSame(204, $response->statusCode);
        $reports = $this->loggedReports();
        $this->assertCount(1, $reports);
        $this->assertSame('TypeError: x is undefined', $reports[0]['message']);
        $this->assertStringContainsString('at foo', $reports[0]['stack']);
        $this->assertSame('https://example.com/events/3', $reports[0]['url']);
        $this->assertSame('1.2.3', $reports[0]['appVersion']);
        $this->assertSame('203.0.113.5', $reports[0]['clientIp']);
        $this->assertSame('TestBrowser/1.0', $reports[0]['userAgent']);
        $this->assertNull($reports[0]['userId']);
    }

    public function testLoggedInUserIdIsIncluded(): void
    {
        DB::$users->create('jane', 'Pass123!', firstName: 'Jane', lastName: 'Doe');
        $this->apiLoginAs('jane', 'Pass123!');

        $response = $this->json('POST', '/api/client-errors', $this->body());

        $this->assertSame(204, $response->statusCode);
        $this->assertSame($_SESSION['user_id'], $this->loggedReports()[0]['userId']);
    }

    public function testStackIsOptional(): void
    {
        $response = $this->report(['stack' => null]);

        $this->assertSame(204, $response->statusCode);
        $this->assertNull($this->loggedReports()[0]['stack']);
    }

    public function testLongValuesAreTruncated(): void
    {
        $_SERVER['HTTP_USER_AGENT'] = str_repeat('u', 500);

        $this->report([
            'message' => str_repeat('m', 900),
            'stack' => str_repeat('s', 9000),
            'url' => 'https://example.com/' . str_repeat('p', 900),
        ]);

        $report = $this->loggedReports()[0];
        $this->assertSame(500, mb_strlen($report['message']));
        $this->assertSame(4000, mb_strlen($report['stack']));
        $this->assertSame(300, mb_strlen($report['url']));
        $this->assertSame(200, mb_strlen($report['userAgent']));
    }

    public function testControlCharactersAreStripped(): void
    {
        $this->report([
            'message' => "bad\x01\x1b[31m thing\x7f",
            'url' => "https://example.com/\x00a\nb",
        ]);

        $report = $this->loggedReports()[0];
        $this->assertSame('bad[31m thing', $report['message']);
        $this->assertSame('https://example.com/ab', $report['url']);
    }

    public function testLogLinesCannotBeForged(): void
    {
        $this->report(['message' => "x\n[2026-01-01 00:00:00] CRITICAL: forged"]);

        $this->assertStringNotContainsString("\n[2026-01-01 00:00:00] CRITICAL: forged", $this->log());
        $this->assertCount(1, $this->loggedReports());
    }

    public function testRateLimitBeyondTwentyPerTenMinutesIsSilent(): void
    {
        $this->json('GET', '/api/auth/me');
        for ($i = 0; $i < 20; $i++) {
            $this->assertSame(204, $this->json('POST', '/api/client-errors', $this->body(['message' => "e{$i}"]))->statusCode);
        }

        $response = $this->json('POST', '/api/client-errors', $this->body(['message' => 'one too many']));

        $this->assertSame(204, $response->statusCode);
        $this->assertSame('', $response->body);
        $messages = array_column($this->loggedReports(), 'message');
        $this->assertCount(20, $messages);
        $this->assertNotContains('one too many', $messages);
    }

    public function testRateLimitIsPerClientIp(): void
    {
        $this->json('GET', '/api/auth/me');
        for ($i = 0; $i < 21; $i++) {
            $this->json('POST', '/api/client-errors', $this->body());
        }

        $_SERVER['REMOTE_ADDR'] = '203.0.113.6';
        $this->json('POST', '/api/client-errors', $this->body(['message' => 'other ip']));

        $this->assertContains('other ip', array_column($this->loggedReports(), 'message'));
    }

    public function testInvalidBodyIs422(): void
    {
        $this->json('GET', '/api/auth/me');

        $response = $this->json('POST', '/api/client-errors', ['url' => 'https://example.com/']);

        $this->assertSame(422, $response->statusCode);
        $fields = $response->json()['fields'];
        $this->assertArrayHasKey('message', $fields);
        $this->assertArrayHasKey('appVersion', $fields);
        $this->assertSame([], $this->loggedReports());
    }

    public function testWithoutCsrfIs403(): void
    {
        $response = $this->json('POST', '/api/client-errors', $this->body(), false);

        $this->assertSame(403, $response->statusCode);
        $this->assertSame([], $this->loggedReports());
    }
}
