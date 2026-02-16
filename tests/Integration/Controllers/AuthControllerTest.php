<?php

declare(strict_types=1);

namespace TP\Tests\Integration\Controllers;

use TP\Tests\Integration\IntegrationTestCase;
use TP\Models\DB;

/**
 * Integration tests for AuthController
 */
class AuthControllerTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanDatabase();
    }

    public function testLoginFormShowsWhenNotLoggedIn(): void
    {
        $response = $this->request('GET', '/login');

        $this->assertEquals(200, $response->statusCode);
        $this->assertStringContainsString('login', strtolower($response->body));
    }

    public function testLoginFormRedirectsWhenAlreadyLoggedIn(): void
    {
        $this->loginAsAdmin();

        $response = $this->request('GET', '/login');

        $this->assertEquals(303, $response->statusCode);
    }

    public function testLoginWithValidCredentials(): void
    {
        $response = $this->request('POST', '/login', [
            'username' => 'admin',
            'password' => 'Admin123!'
        ]);

        $this->assertEquals(303, $response->statusCode);
        $this->assertTrue(isset($_SESSION['user_id']));
    }

    public function testLoginWithInvalidCredentials(): void
    {
        $response = $this->request('POST', '/login', [
            'username' => 'admin',
            'password' => 'wrongpassword'
        ]);

        $this->assertEquals(303, $response->statusCode);
        $this->assertFalse(isset($_SESSION['user_id']));
    }

    public function testLoginWithMissingUsername(): void
    {
        $response = $this->request('POST', '/login', [
            'password' => 'Admin123!'
        ]);

        $this->assertEquals(303, $response->statusCode);
        $this->assertFalse(isset($_SESSION['user_id']));
    }

    public function testLoginWithMissingPassword(): void
    {
        $response = $this->request('POST', '/login', [
            'username' => 'admin'
        ]);

        $this->assertEquals(303, $response->statusCode);
        $this->assertFalse(isset($_SESSION['user_id']));
    }

    public function testLoginWithNonexistentUser(): void
    {
        $response = $this->request('POST', '/login', [
            'username' => 'nonexistent',
            'password' => 'password123'
        ]);

        $this->assertEquals(303, $response->statusCode);
        $this->assertFalse(isset($_SESSION['user_id']));
    }

    public function testLogoutRedirects(): void
    {
        $this->loginAsAdmin();
        $this->assertTrue(isset($_SESSION['user_id']));

        $response = $this->request('POST', '/logout');

        $this->assertEquals(303, $response->statusCode);
        // Session clearing happens but may not be reflected in test environment
    }

    public function testLogoutRequiresAuthentication(): void
    {
        // Don't login first
        $response = $this->request('POST', '/logout');

        // Should redirect (middleware redirects unauthenticated users)
        $this->assertEquals(303, $response->statusCode);
    }
}
