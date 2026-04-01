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
            'male' => '1',
            'username' => 'newuser',
            'password' => 'NewPass123!'
        ]);

        $this->assertEquals(303, $response->statusCode);

        $users = DB::$users->all();
        $usernames = array_map(fn($u) => $u->username, $users);
        $this->assertContains('newuser', $usernames);
    }

    public function testStoreCreatesUserWithAllFields(): void
    {
        $this->loginAsAdmin();

        $response = $this->request('POST', '/users', [
            'male' => '1',
            'username' => 'fulluser',
            'password' => 'Pass123!',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'rfeg' => 'RF999',
            'member_number' => 'M42',
        ]);

        $this->assertEquals(303, $response->statusCode);

        $users = DB::$users->all();
        $user = array_values(array_filter($users, fn($u) => $u->username === 'fulluser'))[0] ?? null;
        $this->assertNotNull($user);
        $this->assertEquals('John', $user->firstName);
        $this->assertEquals('Doe', $user->lastName);
        $this->assertEquals('RF999', $user->rfeg);
        $this->assertEquals('M42', $user->memberNumber);
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

    // ===== GET /users/{id}/edit =====

    public function testEditFormShowsForAdmin(): void
    {
        $this->loginAsAdmin();

        $userId = DB::$users->create('testuser', 'Pass123!', true, 'RF1', 'M1', 'John', 'Doe');

        $response = $this->request('GET', "/users/$userId/edit");

        $this->assertEquals(200, $response->statusCode);
        $this->assertStringContainsString('testuser', $response->body);
    }

    public function testEditFormReturns404ForNonexistentUser(): void
    {
        $this->loginAsAdmin();

        $response = $this->request('GET', '/users/99999/edit');

        $this->assertEquals(404, $response->statusCode);
    }

    public function testEditFormRequiresAdmin(): void
    {
        $this->loginAsAdmin();
        $userId = DB::$users->create('regularuser', 'Pass123!');

        $this->loginAs('regularuser', 'Pass123!');

        $response = $this->request('GET', "/users/$userId/edit");

        $this->assertEquals(403, $response->statusCode);
    }

    // ===== POST /users/{id}/update =====

    public function testUpdateModifiesUser(): void
    {
        $this->loginAsAdmin();

        $userId = DB::$users->create('testuser', 'Pass123!');

        $response = $this->request('POST', "/users/$userId/update", [
            'male' => '0',
            'username' => 'testuser',
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'rfeg' => 'RF123',
            'member_number' => 'M99',
        ]);

        $this->assertEquals(303, $response->statusCode);

        $user = DB::$users->get($userId);
        $this->assertFalse($user->male);
        $this->assertEquals('Jane', $user->firstName);
        $this->assertEquals('Doe', $user->lastName);
        $this->assertEquals('RF123', $user->rfeg);
        $this->assertEquals('M99', $user->memberNumber);
    }

    public function testUpdateChangesUsername(): void
    {
        $this->loginAsAdmin();

        $userId = DB::$users->create('oldname', 'Pass123!');

        $response = $this->request('POST', "/users/$userId/update", [
            'male' => '1',
            'username' => 'newname',
        ]);

        $this->assertEquals(303, $response->statusCode);

        $user = DB::$users->get($userId);
        $this->assertEquals('newname', $user->username);
    }

    public function testUpdatePreventsDuplicateUsername(): void
    {
        $this->loginAsAdmin();

        DB::$users->create('existinguser', 'Pass123!');
        $userId = DB::$users->create('testuser', 'Pass123!');

        $response = $this->request('POST', "/users/$userId/update", [
            'male' => '1',
            'username' => 'existinguser',
        ]);

        $this->assertEquals(303, $response->statusCode);

        // Username should not have changed
        $user = DB::$users->get($userId);
        $this->assertEquals('testuser', $user->username);
    }

    public function testUpdateAllowsKeepingSameUsername(): void
    {
        $this->loginAsAdmin();

        $userId = DB::$users->create('testuser', 'Pass123!');

        $response = $this->request('POST', "/users/$userId/update", [
            'male' => '1',
            'username' => 'testuser',
            'first_name' => 'Updated',
        ]);

        $this->assertEquals(303, $response->statusCode);

        $user = DB::$users->get($userId);
        $this->assertEquals('testuser', $user->username);
        $this->assertEquals('Updated', $user->firstName);
    }

    public function testUpdateChangesPassword(): void
    {
        $this->loginAsAdmin();

        $userId = DB::$users->create('testuser', 'OldPass123!');

        $response = $this->request('POST', "/users/$userId/update", [
            'male' => '1',
            'username' => 'testuser',
            'password' => 'NewPass123!',
        ]);

        $this->assertEquals(303, $response->statusCode);

        // Verify new password works
        $_SESSION = [];
        $this->loginAs('testuser', 'NewPass123!');
        $this->assertTrue(isset($_SESSION['user_id']));
    }

    public function testUpdateEmptyPasswordDoesNotChangePassword(): void
    {
        $this->loginAsAdmin();

        $userId = DB::$users->create('testuser', 'OldPass123!');

        $response = $this->request('POST', "/users/$userId/update", [
            'male' => '1',
            'username' => 'testuser',
            'password' => '',
        ]);

        $this->assertEquals(303, $response->statusCode);

        // Old password should still work
        $_SESSION = [];
        $this->loginAs('testuser', 'OldPass123!');
        $this->assertTrue(isset($_SESSION['user_id']));
    }

    public function testUpdateClearsOptionalFields(): void
    {
        $this->loginAsAdmin();

        $userId = DB::$users->create('testuser', 'Pass123!', true, 'RF1', 'M1', 'John', 'Doe');

        $response = $this->request('POST', "/users/$userId/update", [
            'male' => '1',
            'username' => 'testuser',
        ]);

        $this->assertEquals(303, $response->statusCode);

        $user = DB::$users->get($userId);
        $this->assertNull($user->rfeg);
        $this->assertNull($user->memberNumber);
        $this->assertNull($user->firstName);
        $this->assertNull($user->lastName);
    }

    public function testUpdateRequiresAdmin(): void
    {
        $this->loginAsAdmin();
        $userId = DB::$users->create('regularuser', 'Pass123!');

        $this->loginAs('regularuser', 'Pass123!');

        $response = $this->request('POST', "/users/$userId/update", [
            'male' => '1',
            'username' => 'hacked',
        ]);

        $this->assertEquals(403, $response->statusCode);

        $user = DB::$users->get($userId);
        $this->assertEquals('regularuser', $user->username);
    }

    public function testUpdateReturns404ForNonexistentUser(): void
    {
        $this->loginAsAdmin();

        $response = $this->request('POST', '/users/99999/update', [
            'male' => '1',
            'username' => 'ghost',
        ]);

        $this->assertEquals(404, $response->statusCode);
    }

    // ===== POST /users/{id}/admin =====

    public function testToggleAdminGrantsAdminRights(): void
    {
        $this->loginAsAdmin();

        $userId = DB::$users->create('testuser', 'Pass123!');

        $response = $this->request('POST', "/users/$userId/admin", [
            'admin' => '1'
        ]);

        $this->assertEquals(303, $response->statusCode);

        $user = DB::$users->get($userId);
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

        $user = DB::$users->get($userId);
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

    // ===== POST /users/{id}/delete =====

    public function testDeleteRemovesUser(): void
    {
        $this->loginAsAdmin();

        $userId = DB::$users->create('todelete', 'Pass123!');

        $response = $this->request('POST', "/users/$userId/delete");

        $this->assertEquals(303, $response->statusCode);

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

    // ===== Anonymous access =====

    public function testIndexAsAnonymous(): void
    {
        $response = $this->request('GET', '/users');
        $this->assertEquals(303, $response->statusCode);
    }

    public function testCreateFormAsAnonymous(): void
    {
        $response = $this->request('GET', '/users/new');
        $this->assertEquals(303, $response->statusCode);
    }

    public function testStoreAsAnonymous(): void
    {
        $response = $this->request('POST', '/users', [
            'male' => '1', 'username' => 'anonuser', 'password' => 'Pass123!',
        ]);
        $this->assertEquals(303, $response->statusCode);
    }

    public function testEditFormAsAnonymous(): void
    {
        $this->loginAsAdmin();
        $userId = DB::$users->create('targetuser', 'Pass123!');
        $_SESSION = [];

        $response = $this->request('GET', "/users/$userId/edit");
        $this->assertEquals(303, $response->statusCode);
    }

    public function testUpdateAsAnonymous(): void
    {
        $this->loginAsAdmin();
        $userId = DB::$users->create('targetuser', 'Pass123!');
        $_SESSION = [];

        $response = $this->request('POST', "/users/$userId/update", [
            'male' => '1', 'username' => 'targetuser',
        ]);
        $this->assertEquals(303, $response->statusCode);
    }

    public function testDeleteAsAnonymous(): void
    {
        $this->loginAsAdmin();
        $userId = DB::$users->create('targetuser', 'Pass123!');
        $_SESSION = [];

        $response = $this->request('POST', "/users/$userId/delete");
        $this->assertEquals(303, $response->statusCode);
    }

    public function testToggleAdminAsAnonymous(): void
    {
        $this->loginAsAdmin();
        $userId = DB::$users->create('targetuser', 'Pass123!');
        $_SESSION = [];

        $response = $this->request('POST', "/users/$userId/admin", ['admin' => '1']);
        $this->assertEquals(303, $response->statusCode);
    }
}
