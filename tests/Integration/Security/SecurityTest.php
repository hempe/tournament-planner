<?php

declare(strict_types=1);

namespace TP\Tests\Integration\Security;

use TP\Tests\Integration\IntegrationTestCase;
use TP\Core\Security;

/**
 * Integration tests for Security class
 */
class SecurityTest extends IntegrationTestCase
{
    private Security $security;

    protected function setUp(): void
    {
        parent::setUp();
        $_SESSION = [];
        $this->security = Security::getInstance();
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        parent::tearDown();
    }

    // CSRF Token Tests

    public function testGenerateCsrfTokenCreatesValidToken(): void
    {
        $token = $this->security->generateCsrfToken();

        $this->assertNotEmpty($token);
        $this->assertEquals(64, strlen($token)); // 32 bytes = 64 hex chars
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $token);
    }

    public function testGenerateCsrfTokenStoresInSession(): void
    {
        $token = $this->security->generateCsrfToken();

        $this->assertArrayHasKey('csrf_tokens', $_SESSION);
        $this->assertArrayHasKey('_token', $_SESSION['csrf_tokens']);
        $this->assertEquals($token, $_SESSION['csrf_tokens']['_token']);
    }

    public function testGenerateCsrfTokenCreatesUniqueTokens(): void
    {
        $token1 = $this->security->generateCsrfToken();
        $_SESSION = []; // Clear session
        $token2 = $this->security->generateCsrfToken();

        $this->assertNotEquals($token1, $token2);
    }

    public function testValidateCsrfTokenSucceedsWithValidToken(): void
    {
        $token = $this->security->generateCsrfToken();

        $isValid = $this->security->validateCsrfToken($token);

        $this->assertTrue($isValid);
    }

    public function testValidateCsrfTokenFailsWithInvalidToken(): void
    {
        $this->security->generateCsrfToken();

        $isValid = $this->security->validateCsrfToken('invalid_token');

        $this->assertFalse($isValid);
    }

    public function testValidateCsrfTokenFailsWithNoStoredToken(): void
    {
        $isValid = $this->security->validateCsrfToken('some_token');

        $this->assertFalse($isValid);
    }

    public function testValidateCsrfTokenRemovesTokenAfterValidation(): void
    {
        $token = $this->security->generateCsrfToken();

        $this->security->validateCsrfToken($token);

        $this->assertArrayNotHasKey('_token', $_SESSION['csrf_tokens'] ?? []);
    }

    public function testValidateCsrfTokenFailsWithEmptySession(): void
    {
        unset($_SESSION);

        $isValid = $this->security->validateCsrfToken('token');

        $this->assertFalse($isValid);
    }

    public function testGetCsrfTokenNameReturnsDefaultName(): void
    {
        $name = $this->security->getCsrfTokenName();

        $this->assertEquals('_token', $name);
    }

    // Password Hashing Tests

    public function testHashPasswordCreatesValidHash(): void
    {
        $password = 'MySecurePassword123!';

        $hash = $this->security->hashPassword($password);

        $this->assertNotEmpty($hash);
        $this->assertStringStartsWith('$argon2id$', $hash);
        $this->assertNotEquals($password, $hash);
    }

    public function testHashPasswordCreatesUniqueHashes(): void
    {
        $password = 'SamePassword123!';

        $hash1 = $this->security->hashPassword($password);
        $hash2 = $this->security->hashPassword($password);

        $this->assertNotEquals($hash1, $hash2); // Different salts
    }

    public function testVerifyPasswordSucceedsWithCorrectPassword(): void
    {
        $password = 'TestPassword123!';
        $hash = $this->security->hashPassword($password);

        $isValid = $this->security->verifyPassword($password, $hash);

        $this->assertTrue($isValid);
    }

    public function testVerifyPasswordFailsWithIncorrectPassword(): void
    {
        $hash = $this->security->hashPassword('CorrectPassword');

        $isValid = $this->security->verifyPassword('WrongPassword', $hash);

        $this->assertFalse($isValid);
    }

    public function testVerifyPasswordIsCaseSensitive(): void
    {
        $hash = $this->security->hashPassword('Password');

        $isValid = $this->security->verifyPassword('password', $hash);

        $this->assertFalse($isValid);
    }

    public function testIsPasswordSecureAlwaysReturnsTrue(): void
    {
        // Password validation is disabled per user request
        $this->assertTrue($this->security->isPasswordSecure('a'));
        $this->assertTrue($this->security->isPasswordSecure(''));
        $this->assertTrue($this->security->isPasswordSecure('any_password'));
    }

    // HTML Escaping Tests

    public function testEscapeHtmlEscapesSpecialCharacters(): void
    {
        $input = '<script>alert("XSS")</script>';

        $output = $this->security->escapeHtml($input);

        $this->assertEquals('&lt;script&gt;alert(&quot;XSS&quot;)&lt;/script&gt;', $output);
    }

    public function testEscapeHtmlHandlesApostrophes(): void
    {
        $input = "It's a test";

        $output = $this->security->escapeHtml($input);

        // htmlspecialchars can encode ' as &#039;, &#39;, &apos;, or leave it
        $validOutputs = ['It&#039;s a test', 'It&#39;s a test', 'It&apos;s a test', "It's a test"];
        $this->assertContains($output, $validOutputs, "Got unexpected output: $output");
    }

    public function testEscapeHtmlHandlesAmpersands(): void
    {
        $input = 'Tom & Jerry';

        $output = $this->security->escapeHtml($input);

        $this->assertEquals('Tom &amp; Jerry', $output);
    }

    public function testEscapeHtmlPreservesNormalText(): void
    {
        $input = 'Normal text without special chars';

        $output = $this->security->escapeHtml($input);

        $this->assertEquals($input, $output);
    }

    // Attribute Escaping Tests

    public function testEscapeAttrEscapesQuotes(): void
    {
        $input = 'value" onclick="alert(1)';

        $output = $this->security->escapeAttr($input);

        $this->assertEquals('value&quot; onclick=&quot;alert(1)', $output);
    }

    public function testEscapeAttrEscapesSpecialChars(): void
    {
        $input = '<>&"\'';

        $output = $this->security->escapeAttr($input);

        $this->assertStringContainsString('&lt;', $output);
        $this->assertStringContainsString('&gt;', $output);
        $this->assertStringContainsString('&amp;', $output);
        $this->assertStringContainsString('&quot;', $output);
    }

    // URL Escaping Tests

    public function testEscapeUrlEncodesSpaces(): void
    {
        $input = 'hello world';

        $output = $this->security->escapeUrl($input);

        $this->assertEquals('hello%20world', $output);
    }

    public function testEscapeUrlEncodesSpecialCharacters(): void
    {
        $input = 'param=value&other=123';

        $output = $this->security->escapeUrl($input);

        $this->assertEquals('param%3Dvalue%26other%3D123', $output);
    }

    public function testEscapeUrlPreservesAlphanumeric(): void
    {
        $input = 'abc123XYZ';

        $output = $this->security->escapeUrl($input);

        $this->assertEquals('abc123XYZ', $output);
    }

    // Filename Cleaning Tests

    public function testCleanFilenameRemovesDirectoryTraversal(): void
    {
        $input = '../../etc/passwd';

        $output = $this->security->cleanFilename($input);

        $this->assertEquals('passwd', $output);
    }

    public function testCleanFilenameReplacesDangerousCharacters(): void
    {
        $input = 'file<>:"|?*.txt';

        $output = $this->security->cleanFilename($input);

        $this->assertMatchesRegularExpression('/^[a-zA-Z0-9._-]+$/', $output);
        $this->assertStringContainsString('.txt', $output);
    }

    public function testCleanFilenamePreservesValidCharacters(): void
    {
        $input = 'valid-file_name.123.txt';

        $output = $this->security->cleanFilename($input);

        $this->assertEquals('valid-file_name.123.txt', $output);
    }

    public function testCleanFilenameLimitsLength(): void
    {
        $input = str_repeat('a', 300) . '.txt';

        $output = $this->security->cleanFilename($input);

        $this->assertLessThanOrEqual(255, strlen($output));
    }

    public function testCleanFilenameHandlesNullBytes(): void
    {
        $input = "file\0name.txt";

        $output = $this->security->cleanFilename($input);

        $this->assertStringNotContainsString("\0", $output);
    }

    // Rate Limiting Tests

    public function testRateLimitCheckAllowsFirstRequest(): void
    {
        $allowed = $this->security->rateLimitCheck('test_action', 5, 60);

        $this->assertTrue($allowed);
    }

    public function testRateLimitCheckAllowsRequestsUnderLimit(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $allowed = $this->security->rateLimitCheck('test_action', 10, 60);
            $this->assertTrue($allowed);
        }
    }

    public function testRateLimitCheckBlocksRequestsOverLimit(): void
    {
        // Make 5 requests (limit)
        for ($i = 0; $i < 5; $i++) {
            $this->security->rateLimitCheck('test_action', 5, 60);
        }

        // 6th request should be blocked
        $allowed = $this->security->rateLimitCheck('test_action', 5, 60);

        $this->assertFalse($allowed);
    }

    public function testRateLimitCheckIsolatesIdentifiers(): void
    {
        // Max out one identifier
        for ($i = 0; $i < 5; $i++) {
            $this->security->rateLimitCheck('action1', 5, 60);
        }

        // Different identifier should still work
        $allowed = $this->security->rateLimitCheck('action2', 5, 60);

        $this->assertTrue($allowed);
    }

    public function testRateLimitCheckCleansOldRequests(): void
    {
        // Simulate old requests by manipulating session
        $_SESSION['rate_limits']['test'] = [
            time() - 120, // 2 minutes ago (outside window)
            time() - 120,
        ];

        // Should allow new request since old ones are cleaned
        $allowed = $this->security->rateLimitCheck('test', 1, 60);

        $this->assertTrue($allowed);
    }

    // Global Helper Function Tests

    public function testCsrfTokenHelperFunction(): void
    {
        $token = csrf_token();

        $this->assertNotEmpty($token);
        $this->assertEquals(64, strlen($token));
    }

    public function testEscapeHelperFunction(): void
    {
        $output = e('<script>');

        $this->assertEquals('&lt;script&gt;', $output);
    }

    public function testAttrHelperFunction(): void
    {
        $output = attr('"value"');

        $this->assertStringContainsString('&quot;', $output);
    }
}
