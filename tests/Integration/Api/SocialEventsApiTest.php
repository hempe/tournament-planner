<?php

declare(strict_types=1);

namespace TP\Tests\Integration\Api;

use TP\Models\DB;
use TP\Tests\Integration\IntegrationTestCase;

class SocialEventsApiTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanDatabase();
    }

    private function member(string $name = 'jane', string $first = 'Jane', string $last = 'Doe'): int
    {
        return DB::$users->create($name, 'Pass123!', firstName: $first, lastName: $last);
    }

    private function dinner(string $menus = 'Fisch,Fleisch', string $tables = '4,6'): int
    {
        return DB::$socialEvents->add('Dinner', '2099-06-10', null, 'Nice', null, $menus, $tables);
    }

    private function menuId(int $id, int $index = 0): int
    {
        return DB::$socialEvents->menus($id)[$index]->id;
    }

    private function tableId(int $id, int $index = 0): int
    {
        return DB::$socialEvents->tables($id)[$index]->id;
    }

    public function testAnonymousGets401(): void
    {
        $id = $this->dinner();

        $get = $this->json('GET', "/api/social-events/{$id}");
        $this->assertSame(401, $get->statusCode);
        $this->assertSame(['error' => 'auth.login_required'], $get->json());

        $post = $this->json('POST', "/api/social-events/{$id}/registrations", ['menuId' => null, 'tableId' => null]);
        $this->assertSame(401, $post->statusCode);
    }

    public function testDetailListsMenusTablesAndParticipants(): void
    {
        $this->member('jane');
        $bobId = $this->member('bob', 'Bob', 'Builder');
        $id = $this->dinner();
        DB::$socialEvents->register($id, $bobId, $this->menuId($id), $this->tableId($id));
        $this->apiLoginAs('jane', 'Pass123!');

        $response = $this->json('GET', "/api/social-events/{$id}");

        $this->assertSame(200, $response->statusCode);
        $data = $response->json();
        $this->assertSame('Dinner', $data['event']['name']);
        $this->assertSame('Nice', $data['description']);
        $this->assertSame(['Fisch', 'Fleisch'], array_column($data['menus'], 'name'));
        $this->assertSame([4, 6], array_column($data['tables'], 'capacity'));
        $this->assertNull($data['myRegistration']);
        $this->assertCount(1, $data['participants']);
        $this->assertSame(1, $data['participants'][0]['tableNumber']);
    }

    public function testRegisterWithMenuAndTable(): void
    {
        $this->member();
        $id = $this->dinner();
        $tableId = $this->tableId($id);
        $this->apiLoginAs('jane', 'Pass123!');

        $response = $this->json('POST', "/api/social-events/{$id}/registrations", [
            'menuId' => $this->menuId($id, 1),
            'tableId' => $tableId,
        ]);

        $this->assertSame(200, $response->statusCode);
        $data = $response->json();
        $this->assertSame('Fleisch', $data['myRegistration']['menuName']);
        $this->assertSame(1, $data['myRegistration']['tableNumber']);
        $this->assertSame(3, $data['tables'][0]['available']);
        $this->assertTrue($data['event']['userRegistered']);
    }

    public function testRegisterWithoutMenuWhenMenusExistIs422(): void
    {
        $this->member();
        $id = $this->dinner();
        $this->apiLoginAs('jane', 'Pass123!');

        $response = $this->json('POST', "/api/social-events/{$id}/registrations", ['menuId' => null, 'tableId' => null]);

        $this->assertSame(422, $response->statusCode);
        $body = $response->json();
        $this->assertSame('validation.failed', $body['error']);
        $this->assertSame('social_events.invalid_menu', $body['fields']['menuId']['key']);
    }

    public function testRegisterWithForeignMenuIs422(): void
    {
        $this->member();
        $id = $this->dinner();
        $other = $this->dinner();
        $this->apiLoginAs('jane', 'Pass123!');

        $response = $this->json('POST', "/api/social-events/{$id}/registrations", ['menuId' => $this->menuId($other), 'tableId' => null]);

        $this->assertSame(422, $response->statusCode);
        $this->assertSame('social_events.invalid_menu', $response->json()['fields']['menuId']['key']);
        $this->assertSame([], DB::$socialEvents->registrations($id));
    }

    public function testRegisterWithForeignTableIs422(): void
    {
        $this->member();
        $id = $this->dinner();
        $other = $this->dinner();
        $this->apiLoginAs('jane', 'Pass123!');

        $response = $this->json('POST', "/api/social-events/{$id}/registrations", [
            'menuId' => $this->menuId($id),
            'tableId' => $this->tableId($other),
        ]);

        $this->assertSame(422, $response->statusCode);
        $this->assertSame('social_events.invalid_table', $response->json()['fields']['tableId']['key']);
    }

    public function testRegisterOnLockedEventIs409(): void
    {
        $this->member();
        $id = $this->dinner();
        DB::$socialEvents->lock($id);
        $this->apiLoginAs('jane', 'Pass123!');

        $response = $this->json('POST', "/api/social-events/{$id}/registrations", ['menuId' => $this->menuId($id), 'tableId' => null]);

        $this->assertSame(409, $response->statusCode);
        $this->assertSame(['error' => 'social_events.locked_message'], $response->json());
        $this->assertSame([], DB::$socialEvents->registrations($id));
    }

    public function testRegisterOnFullEventIs409(): void
    {
        $this->member();
        $bobId = $this->member('bob', 'Bob', 'Builder');
        $id = $this->dinner('', '1');
        DB::$socialEvents->register($id, $bobId, null, null);
        $this->apiLoginAs('jane', 'Pass123!');

        $response = $this->json('POST', "/api/social-events/{$id}/registrations", ['menuId' => null, 'tableId' => null]);

        $this->assertSame(409, $response->statusCode);
        $this->assertSame(['error' => 'social_events.event_full'], $response->json());
    }

    public function testRegisterOnFullTableIs409(): void
    {
        $this->member();
        $bobId = $this->member('bob', 'Bob', 'Builder');
        $id = $this->dinner('', '1,5');
        $tableId = $this->tableId($id);
        DB::$socialEvents->register($id, $bobId, null, $tableId);
        $this->apiLoginAs('jane', 'Pass123!');

        $response = $this->json('POST', "/api/social-events/{$id}/registrations", ['menuId' => null, 'tableId' => $tableId]);

        $this->assertSame(409, $response->statusCode);
        $this->assertSame(['error' => 'social_events.table_full'], $response->json());
    }

    public function testRegisterWithoutMenusIgnoresMenuId(): void
    {
        $this->member();
        $id = $this->dinner('', '4');
        $this->apiLoginAs('jane', 'Pass123!');

        $response = $this->json('POST', "/api/social-events/{$id}/registrations", ['menuId' => null, 'tableId' => null]);

        $this->assertSame(200, $response->statusCode);
        $this->assertNull($response->json()['myRegistration']['menuName']);
    }

    public function testRegisteringTwiceIsIdempotent(): void
    {
        $this->member();
        $id = $this->dinner();
        $this->apiLoginAs('jane', 'Pass123!');
        $body = ['menuId' => $this->menuId($id), 'tableId' => null];
        $this->json('POST', "/api/social-events/{$id}/registrations", $body);

        $response = $this->json('POST', "/api/social-events/{$id}/registrations", $body);

        $this->assertSame(200, $response->statusCode);
        $this->assertCount(1, DB::$socialEvents->registrations($id));
        $this->assertNotNull($response->json()['myRegistration']);
    }

    public function testUnregisterClearsOwnRegistration(): void
    {
        $userId = $this->member();
        $id = $this->dinner();
        DB::$socialEvents->register($id, $userId, $this->menuId($id), null);
        $this->apiLoginAs('jane', 'Pass123!');

        $response = $this->json('DELETE', "/api/social-events/{$id}/registrations/me");

        $this->assertSame(200, $response->statusCode);
        $this->assertNull($response->json()['myRegistration']);
        $this->assertSame([], DB::$socialEvents->registrations($id));
    }

    public function testUnregisterOnLockedEventIs409(): void
    {
        $userId = $this->member();
        $id = $this->dinner();
        DB::$socialEvents->register($id, $userId, $this->menuId($id), null);
        DB::$socialEvents->lock($id);
        $this->apiLoginAs('jane', 'Pass123!');

        $response = $this->json('DELETE', "/api/social-events/{$id}/registrations/me");

        $this->assertSame(409, $response->statusCode);
        $this->assertSame(['error' => 'social_events.locked_message'], $response->json());
        $this->assertCount(1, DB::$socialEvents->registrations($id));
    }

    public function testUnknownEventIs404(): void
    {
        $this->member();
        $this->apiLoginAs('jane', 'Pass123!');

        foreach (['GET' => '', 'DELETE' => '/registrations/me'] as $method => $suffix) {
            $response = $this->json($method, '/api/social-events/999999' . $suffix);
            $this->assertSame(404, $response->statusCode, $method);
            $this->assertSame(['error' => 'social_events.not_found'], $response->json());
        }
        $post = $this->json('POST', '/api/social-events/999999/registrations', ['menuId' => null, 'tableId' => null]);
        $this->assertSame(404, $post->statusCode);
        $this->assertSame(['error' => 'social_events.not_found'], $post->json());
    }
}
