<?php

declare(strict_types=1);

namespace TP\Tests\Integration\Api;

use TP\Models\DB;
use TP\Tests\Integration\IntegrationTestCase;

class AuthApiTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanDatabase();
    }

    public function testMeReturnsNullForAnonymousAndIssuesCsrfCookie(): void
    {
        $response = $this->json('GET', '/api/auth/me');

        $this->assertSame(200, $response->statusCode);
        $this->assertSame(['user' => null], $response->json());
        $this->assertArrayHasKey('XSRF-TOKEN', $response->cookies);
    }

    public function testLoginReturnsUserAndSetsSession(): void
    {
        DB::$users->create('jane', 'Pass123!', firstName: 'Jane', lastName: 'Doe');

        $response = $this->apiLoginAs('jane', 'Pass123!');

        $this->assertSame(200, $response->statusCode);
        $user = $response->json()['user'];
        $this->assertSame('jane', $user['username']);
        $this->assertSame('Jane', $user['firstName']);
        $this->assertSame('Doe', $user['lastName']);
        $this->assertSame('Jane Doe', $user['displayName']);
        $this->assertFalse($user['isAdmin']);
        $this->assertIsInt($user['id']);
        $this->assertSame($user['id'], $_SESSION['user_id']);
    }

    public function testMeReturnsLoggedInAdmin(): void
    {
        $this->apiLoginAs('admin', 'Admin123!');

        $user = $this->json('GET', '/api/auth/me')->json()['user'];

        $this->assertSame('admin', $user['username']);
        $this->assertTrue($user['isAdmin']);
    }

    public function testLoginWithWrongPasswordReturns422(): void
    {
        $response = $this->apiLoginAs('admin', 'wrong');

        $this->assertSame(422, $response->statusCode);
        $this->assertSame(['error' => 'auth.login_failed'], $response->json());
        $this->assertArrayNotHasKey('user_id', $_SESSION);
    }

    public function testLoginWithMissingFieldsReturnsFieldErrors(): void
    {
        $this->json('GET', '/api/auth/me');

        $response = $this->json('POST', '/api/auth/login', ['username' => '']);

        $this->assertSame(422, $response->statusCode);
        $this->assertSame(
            [
                'error' => 'validation.failed',
                'fields' => [
                    'username' => ['key' => 'validation.required'],
                    'password' => ['key' => 'validation.required'],
                ],
            ],
            $response->json()
        );
    }

    public function testLoginWithoutCsrfTokenIsRejected(): void
    {
        $this->json('GET', '/api/auth/me');

        $response = $this->json('POST', '/api/auth/login', ['username' => 'admin', 'password' => 'Admin123!'], withCsrf: false);

        $this->assertSame(403, $response->statusCode);
        $this->assertArrayNotHasKey('user_id', $_SESSION);
    }

    public function testLogoutClearsSessionAndIssuesFreshToken(): void
    {
        $this->apiLoginAs('admin', 'Admin123!');
        $oldToken = $_SESSION['xsrf_token'];

        $response = $this->json('POST', '/api/auth/logout');

        $this->assertSame(204, $response->statusCode);
        $this->assertArrayNotHasKey('user_id', $_SESSION);
        $this->assertNotSame($oldToken, $response->cookies['XSRF-TOKEN']['value']);
        $this->assertSame(['user' => null], $this->json('GET', '/api/auth/me')->json());
    }

    public function testCanLogInAgainRightAfterLogout(): void
    {
        $this->apiLoginAs('admin', 'Admin123!');
        $this->json('POST', '/api/auth/logout');

        $response = $this->json('POST', '/api/auth/login', ['username' => 'admin', 'password' => 'Admin123!']);

        $this->assertSame(200, $response->statusCode);
    }

    public function testLogoutAnonymousReturns401(): void
    {
        $this->json('GET', '/api/auth/me');

        $response = $this->json('POST', '/api/auth/logout');

        $this->assertSame(401, $response->statusCode);
        $this->assertSame(['error' => 'auth.login_required'], $response->json());
    }

    public function testMeForDeletedUserReturnsNullAndClearsSession(): void
    {
        $id = DB::$users->create('ghost', 'Pass123!');
        $this->apiLoginAs('ghost', 'Pass123!');
        DB::$users->delete($id);

        $this->assertSame(['user' => null], $this->json('GET', '/api/auth/me')->json());
        $this->assertArrayNotHasKey('user_id', $_SESSION);
    }

    public function testSixthLoginAttemptIsRateLimitedEvenWithCorrectPassword(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->assertSame(422, $this->apiLoginAs('admin', 'wrong')->statusCode);
        }

        $response = $this->apiLoginAs('admin', 'Admin123!');

        $this->assertSame(429, $response->statusCode);
        $body = $response->json();
        $this->assertSame('auth.too_many_attempts', $body['error']);
        $this->assertSame(['minutes' => 15], $body['params']);
        $this->assertArrayNotHasKey('user_id', $_SESSION);
    }

    public function testRateLimitIsPerUsernameCaseInsensitive(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->apiLoginAs('Admin', 'wrong');
        }

        $this->assertSame(429, $this->apiLoginAs('ADMIN', 'Admin123!')->statusCode);

        DB::$users->create('jane', 'Pass123!', firstName: 'Jane', lastName: 'Doe');
        $this->assertSame(200, $this->apiLoginAs('jane', 'Pass123!')->statusCode);
    }

    public function testSuccessfulLoginClearsTheCounter(): void
    {
        for ($i = 0; $i < 4; $i++) {
            $this->apiLoginAs('admin', 'wrong');
        }
        $this->assertSame(200, $this->apiLoginAs('admin', 'Admin123!')->statusCode);
        $_SESSION = [];

        for ($i = 0; $i < 5; $i++) {
            $this->assertSame(422, $this->apiLoginAs('admin', 'wrong')->statusCode);
        }
        $this->assertSame(429, $this->apiLoginAs('admin', 'wrong')->statusCode);
    }

    public function testDifferentIpIsNotAffected(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->apiLoginAs('admin', 'wrong');
        }

        $_SERVER['REMOTE_ADDR'] = '203.0.113.9';
        try {
            $this->assertSame(200, $this->apiLoginAs('admin', 'Admin123!')->statusCode);
        } finally {
            unset($_SERVER['REMOTE_ADDR']);
        }
    }
}
