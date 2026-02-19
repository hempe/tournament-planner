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

    // Global Helper Function Tests

    public function testCsrfTokenHelperFunction(): void
    {
        $token = csrf_token();

        $this->assertNotEmpty($token);
        $this->assertEquals(64, strlen($token));
    }
}
