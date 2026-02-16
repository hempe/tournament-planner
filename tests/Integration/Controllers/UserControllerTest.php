<?php

declare(strict_types=1);

namespace TP\Tests\Integration\Controllers;

use TP\Tests\Integration\IntegrationTestCase;
use TP\Models\DB;

/**
 * Integration tests for UserController
 */
class UserControllerTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanDatabase();
    }

    public function testIndexListsUsers(): void
    {
        $this->loginAsAdmin();

        // Create test users
        DB::$users->create('user1', 'Pass123!');
        DB::$users->create('user2', 'Pass123!');

        $response = $this->request('GET', '/users');

        $this->assertEquals(200, $response->statusCode);
        $this->assertStringContainsString('user1', $response->body);
        $this->assertStringContainsString('user2', $response->body);
    }

    public function testIndexRequiresAdmin(): void
    {
        $this->loginAsAdmin();
        DB::$users->create('regularuser', 'Pass123!');

        $this->loginAs('regularuser', 'Pass123!');

        $response = $this->request('GET', '/users');

        $this->assertEquals(403, $response->statusCode);
    }

    public function testCreateFormShows(): void
    {
        $this->loginAsAdmin();

        $response = $this->request('GET', '/users/new');

        $this->assertEquals(200, $response->statusCode);
        $this->assertStringContainsString('username', strtolower($response->body));
        $this->assertStringContainsString('password', strtolower($response->body));
    }

    public function testCreateFormRequiresAdmin(): void
    {
        $this->loginAsAdmin();
        DB::$users->create('regularuser', 'Pass123!');

        $this->loginAs('regularuser', 'Pass123!');

        $response = $this->request('GET', '/users/new');

        $this->assertEquals(403, $response->statusCode);
    }

    public function testStoreCreatesUser(): void
    {
        $this->loginAsAdmin();

        $response = $this->request('POST', '/users', [
            'username' => 'newuser',
            'password' => 'NewPass123!'
        ]);

        $this->assertEquals(303, $response->statusCode);

        // Verify user was created
        $users = DB::$users->all();
        $usernames = array_map(fn($u) => $u->username, $users);
        $this->assertContains('newuser', $usernames);
    }

    public function testStoreRequiresUsername(): void
    {
        $this->loginAsAdmin();

        $response = $this->request('POST', '/users', [
            'password' => 'Pass123!'
        ]);

        $this->assertEquals(303, $response->statusCode);
    }

    public function testStoreRequiresPassword(): void
    {
        $this->loginAsAdmin();

        $response = $this->request('POST', '/users', [
            'username' => 'newuser'
        ]);

        $this->assertEquals(303, $response->statusCode);
    }

    public function testStoreRequiresMinimumUsernameLength(): void
    {
        $this->loginAsAdmin();

        $response = $this->request('POST', '/users', [
            'username' => 'ab', // Too short (min 3)
            'password' => 'Pass123!'
        ]);

        $this->assertEquals(303, $response->statusCode);
    }

    public function testStorePreventsDuplicateUsername(): void
    {
        $this->loginAsAdmin();

        DB::$users->create('existinguser', 'Pass123!');

        $response = $this->request('POST', '/users', [
            'username' => 'existinguser',
            'password' => 'NewPass123!'
        ]);

        $this->assertEquals(303, $response->statusCode);

        // Verify only one user with that name exists
        $users = DB::$users->all();
        $matchingUsers = array_filter($users, fn($u) => $u->username === 'existinguser');
        $this->assertCount(1, $matchingUsers);
    }

    public function testStoreRequiresAdmin(): void
    {
        $this->loginAsAdmin();
        DB::$users->create('regularuser', 'Pass123!');

        $this->loginAs('regularuser', 'Pass123!');

        $response = $this->request('POST', '/users', [
            'username' => 'newuser',
            'password' => 'Pass123!'
        ]);

        $this->assertEquals(403, $response->statusCode);
    }

    public function testDeleteRemovesUser(): void
    {
        $this->loginAsAdmin();

        $userId = DB::$users->create('todelete', 'Pass123!');

        $response = $this->request('POST', "/users/$userId/delete");

        $this->assertEquals(303, $response->statusCode);

        // Verify user was deleted
        $users = DB::$users->all();
        $usernames = array_map(fn($u) => $u->username, $users);
        $this->assertNotContains('todelete', $usernames);
    }

    public function testDeleteRequiresAdmin(): void
    {
        $this->loginAsAdmin();
        $userId = DB::$users->create('regularuser', 'Pass123!');

        $this->loginAs('regularuser', 'Pass123!');

        $response = $this->request('POST', "/users/$userId/delete");

        $this->assertEquals(403, $response->statusCode);
    }

    public function testToggleAdminGrantsAdminRights(): void
    {
        $this->loginAsAdmin();

        $userId = DB::$users->create('testuser', 'Pass123!');

        $response = $this->request('POST', "/users/$userId/admin", [
            'admin' => '1'
        ]);

        $this->assertEquals(303, $response->statusCode);

        // Verify user is now admin
        $users = DB::$users->all();
        $user = array_values(array_filter($users, fn($u) => $u->id === $userId))[0] ?? null;
        $this->assertNotNull($user);
        $this->assertTrue($user->isAdmin);
    }

    public function testToggleAdminRevokesAdminRights(): void
    {
        $this->loginAsAdmin();

        $userId = DB::$users->create('testuser', 'Pass123!');
        DB::$users->setAdmin($userId, true);

        $response = $this->request('POST', "/users/$userId/admin", [
            'admin' => '0'
        ]);

        $this->assertEquals(303, $response->statusCode);

        // Verify user is no longer admin
        $users = DB::$users->all();
        $user = array_values(array_filter($users, fn($u) => $u->id === $userId))[0] ?? null;
        $this->assertNotNull($user);
        $this->assertFalse($user->isAdmin);
    }

    public function testToggleAdminRequiresAdmin(): void
    {
        $this->loginAsAdmin();
        $userId = DB::$users->create('regularuser', 'Pass123!');

        $this->loginAs('regularuser', 'Pass123!');

        $response = $this->request('POST', "/users/$userId/admin", [
            'admin' => '1'
        ]);

        $this->assertEquals(403, $response->statusCode);
    }

    public function testChangePasswordUpdatesPassword(): void
    {
        $this->loginAsAdmin();

        $userId = DB::$users->create('testuser', 'OldPass123!');

        $response = $this->request('POST', "/users/$userId/password", [
            'password' => 'NewPass123!'
        ]);

        $this->assertEquals(303, $response->statusCode);

        // Verify password was changed by trying to login with new password
        $_SESSION = [];
        $this->loginAs('testuser', 'NewPass123!');
        $this->assertTrue(isset($_SESSION['user_id']));
    }

    public function testChangePasswordRequiresPassword(): void
    {
        $this->loginAsAdmin();

        $userId = DB::$users->create('testuser', 'Pass123!');

        $response = $this->request('POST', "/users/$userId/password", []);

        $this->assertEquals(303, $response->statusCode);
    }

    public function testChangePasswordRequiresAdmin(): void
    {
        $this->loginAsAdmin();
        $userId = DB::$users->create('regularuser', 'Pass123!');

        $this->loginAs('regularuser', 'Pass123!');

        $response = $this->request('POST', "/users/$userId/password", [
            'password' => 'NewPass123!'
        ]);

        $this->assertEquals(403, $response->statusCode);
    }
}
