<?php

declare(strict_types=1);

namespace TP\Tests\Integration\Api;

use TP\Models\DB;
use TP\Tests\Integration\IntegrationTestCase;

class UserAdminApiTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanDatabase();
    }

    private function admin(): int
    {
        $this->apiLoginAs('admin', 'Admin123!');
        return array_column(DB::$users->all(), 'id', 'username')['admin'];
    }

    private function member(string $name = 'jane'): int
    {
        return DB::$users->create($name, 'Pass123!', true, 'RF1', 'M1', 'Jane', 'Doe');
    }

    private function createBody(array $override = []): array
    {
        return $override + [
            'male' => false,
            'username' => 'newuser',
            'password' => 'Secret123!',
            'firstName' => 'New',
            'lastName' => 'User',
            'rfeg' => 'RF9',
            'memberNumber' => 'M9',
        ];
    }

    private function updateBody(array $override = []): array
    {
        return $override + [
            'male' => true,
            'username' => 'jane',
            'password' => '',
            'firstName' => 'Jane',
            'lastName' => 'Doe',
            'rfeg' => null,
            'memberNumber' => null,
        ];
    }

    public function testAccessMatrix(): void
    {
        $id = $this->member();

        $this->assertSame(401, $this->json('GET', '/api/users')->statusCode);
        $this->assertSame(401, $this->json('GET', "/api/users/{$id}")->statusCode);
        $this->assertSame(401, $this->json('DELETE', "/api/users/{$id}")->statusCode);

        $this->apiLoginAs('jane', 'Pass123!');
        $this->assertSame(403, $this->json('GET', '/api/users')->statusCode);
        $this->assertSame(403, $this->json('POST', '/api/users', $this->createBody())->statusCode);
        $this->assertSame(403, $this->json('GET', "/api/users/{$id}")->statusCode);
        $this->assertSame(403, $this->json('PUT', "/api/users/{$id}", $this->updateBody())->statusCode);
        $this->assertSame(403, $this->json('PUT', "/api/users/{$id}/admin")->statusCode);
        $this->assertSame(403, $this->json('DELETE', "/api/users/{$id}/admin")->statusCode);
        $this->assertSame(403, $this->json('DELETE', "/api/users/{$id}")->statusCode);
    }

    public function testList(): void
    {
        $this->admin();
        $this->member();

        $response = $this->json('GET', '/api/users');

        $this->assertSame(200, $response->statusCode);
        $byName = array_column($response->json(), null, 'username');
        $this->assertTrue($byName['admin']['isAdmin']);
        $this->assertFalse($byName['jane']['isAdmin']);
        $this->assertSame('Jane Doe', $byName['jane']['displayName']);
        $this->assertSame('RF1', $byName['jane']['rfeg']);
        $this->assertSame('M1', $byName['jane']['memberNumber']);
        $this->assertTrue($byName['jane']['male']);
    }

    public function testCreate(): void
    {
        $this->admin();

        $response = $this->json('POST', '/api/users', $this->createBody());

        $this->assertSame(200, $response->statusCode);
        $data = $response->json();
        $this->assertSame('newuser', $data['username']);
        $this->assertSame('New User', $data['displayName']);
        $this->assertFalse($data['male']);
        $this->assertFalse($data['isAdmin']);
        $this->assertSame('RF9', $data['rfeg']);
        $this->assertSame('M9', $data['memberNumber']);

        $this->assertNotNull(DB::$users->get($data['id']));
    }

    public function testCreateValidationAndTakenUsername(): void
    {
        $this->admin();
        $this->member();

        $invalid = $this->json('POST', '/api/users', ['username' => 'ab']);
        $this->assertSame(422, $invalid->statusCode);
        $fields = $invalid->json()['fields'];
        $this->assertArrayHasKey('male', $fields);
        $this->assertArrayHasKey('username', $fields);
        $this->assertArrayHasKey('password', $fields);

        $taken = $this->json('POST', '/api/users', $this->createBody(['username' => 'jane']));
        $this->assertSame(422, $taken->statusCode);
        $json = $taken->json();
        $this->assertSame('validation.failed', $json['error']);
        $this->assertSame('users.username_taken', $json['fields']['username']['key']);
        $this->assertSame('jane', $json['fields']['username']['params']['username']);
    }

    public function testGetAndNotFound(): void
    {
        $this->admin();
        $id = $this->member();

        $this->assertSame('jane', $this->json('GET', "/api/users/{$id}")->json()['username']);

        $missing = $this->json('GET', '/api/users/99999');
        $this->assertSame(404, $missing->statusCode);
        $this->assertSame('users.not_found', $missing->json()['error']);
    }

    public function testUpdateAndPasswordChange(): void
    {
        $this->admin();
        $id = $this->member();

        $kept = $this->json('PUT', "/api/users/{$id}", $this->updateBody(['firstName' => 'Janet']));
        $this->assertSame(200, $kept->statusCode);
        $this->assertSame('Janet', $kept->json()['firstName']);
        $this->assertSame('jane', $kept->json()['username']);

        $changed = $this->json('PUT', "/api/users/{$id}", $this->updateBody(['username' => 'janet', 'password' => 'NewPass123!']));
        $this->assertSame(200, $changed->statusCode);
        $this->assertSame('janet', $changed->json()['username']);

        $this->json('POST', '/api/auth/logout');
        $this->assertSame(200, $this->apiLoginAs('janet', 'NewPass123!')->statusCode);
    }

    public function testEmptyPasswordLeavesPasswordUnchanged(): void
    {
        $this->admin();
        $id = $this->member();

        $this->json('PUT', "/api/users/{$id}", $this->updateBody(['password' => '']));

        $this->json('POST', '/api/auth/logout');
        $this->assertSame(200, $this->apiLoginAs('jane', 'Pass123!')->statusCode);
    }

    public function testUpdateUsernameTakenByOtherAndOwnAllowed(): void
    {
        $this->admin();
        $id = $this->member();
        $this->member('john');

        $taken = $this->json('PUT', "/api/users/{$id}", $this->updateBody(['username' => 'john']));
        $this->assertSame(422, $taken->statusCode);
        $this->assertSame('users.username_taken', $taken->json()['fields']['username']['key']);
        $this->assertSame('john', $taken->json()['fields']['username']['params']['username']);

        $own = $this->json('PUT', "/api/users/{$id}", $this->updateBody());
        $this->assertSame(200, $own->statusCode);

        $this->assertSame(404, $this->json('PUT', '/api/users/99999', $this->updateBody())->statusCode);
    }

    public function testGrantAndRevokeAdmin(): void
    {
        $this->admin();
        $id = $this->member();

        $granted = $this->json('PUT', "/api/users/{$id}/admin");
        $this->assertSame(200, $granted->statusCode);
        $this->assertTrue($granted->json()['isAdmin']);

        $revoked = $this->json('DELETE', "/api/users/{$id}/admin");
        $this->assertSame(200, $revoked->statusCode);
        $this->assertFalse($revoked->json()['isAdmin']);

        $this->assertSame(404, $this->json('PUT', '/api/users/99999/admin')->statusCode);
    }

    public function testDelete(): void
    {
        $this->admin();
        $id = $this->member();

        $this->assertSame(204, $this->json('DELETE', "/api/users/{$id}")->statusCode);
        $this->assertNull(DB::$users->get($id));
        $this->assertSame(404, $this->json('DELETE', "/api/users/{$id}")->statusCode);
    }

    public function testAdminCannotDeleteThemselves(): void
    {
        $adminId = $this->admin();

        $response = $this->json('DELETE', "/api/users/{$adminId}");

        $this->assertSame(409, $response->statusCode);
        $this->assertSame('errors.forbidden', $response->json()['error']);
        $this->assertNotNull(DB::$users->get($adminId));
    }
}
