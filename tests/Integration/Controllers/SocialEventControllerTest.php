<?php

declare(strict_types=1);

namespace TP\Tests\Integration\Controllers;

use TP\Tests\Integration\IntegrationTestCase;
use TP\Models\DB;

class SocialEventControllerTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanDatabase();
    }

    // ===== Helper =====

    private function addSocialEvent(
        string $name = 'Summer Dinner',
        string $date = '2099-06-15',
        ?int $tournamentId = null,
        string $menus = 'Meat, Fish, Vegetables',
        string $tables = '10, 10, 8',
    ): int {
        return DB::$socialEvents->add($name, $date, $tournamentId, null, null, $menus, $tables);
    }

    private function addUser(string $username = 'member', string $password = 'Pass123!'): int
    {
        return DB::$users->create($username, $password);
    }

    // ===== GET /social-events/new — access control =====

    public function testCreateFormRequiresAdmin(): void
    {
        $response = $this->request('GET', '/social-events/new');
        $this->assertEquals(403, $response->statusCode);
    }

    public function testCreateFormForbiddenForRegularUser(): void
    {
        $this->addUser();
        $this->loginAs('member', 'Pass123!');

        $response = $this->request('GET', '/social-events/new');
        $this->assertEquals(403, $response->statusCode);
    }

    public function testCreateFormShowsForAdmin(): void
    {
        $this->loginAsAdmin();

        $response = $this->request('GET', '/social-events/new');
        $this->assertEquals(200, $response->statusCode);
    }

    public function testCreateFormPreFillsDateFromLinkedTournament(): void
    {
        $this->loginAsAdmin();
        $tournamentId = DB::$events->add('Club Championship', '2099-07-20', 50);

        $response = $this->request('GET', '/social-events/new', ['tournamentId' => (string) $tournamentId]);

        $this->assertEquals(200, $response->statusCode);
        $this->assertStringContainsString('2099-07-20', $response->body);
    }

    // ===== POST /social-events/new =====

    public function testStoreRequiresAdmin(): void
    {
        $response = $this->request('POST', '/social-events/new', [
            'name' => 'Summer Dinner',
            'date' => '2099-06-15',
            'menus' => 'Meat, Fish',
            'tables' => '10, 8',
        ]);
        $this->assertEquals(403, $response->statusCode);
    }

    public function testStoreCreatesSocialEvent(): void
    {
        $this->loginAsAdmin();

        $response = $this->request('POST', '/social-events/new', [
            'name' => 'Summer Dinner',
            'date' => '2099-06-15',
            'menus' => 'Meat, Fish, Vegetables',
            'tables' => '10, 10, 8',
        ]);

        $this->assertEquals(303, $response->statusCode);

        $events = DB::$socialEvents->all();
        $this->assertCount(1, $events);
        $this->assertEquals('Summer Dinner', $events[0]->name);
        $this->assertEquals('2099-06-15', $events[0]->date);
        $this->assertEquals(28, $events[0]->totalCapacity); // 10+10+8
    }

    public function testStoreCreatesMenusAndTables(): void
    {
        $this->loginAsAdmin();

        $this->request('POST', '/social-events/new', [
            'name' => 'Gala Dinner',
            'date' => '2099-08-10',
            'menus' => 'Beef, Salmon, Vegan',
            'tables' => '10, 10, 8, 8',
        ]);

        $events = DB::$socialEvents->all();
        $id = $events[0]->id;

        $menus = DB::$socialEvents->menus($id);
        $this->assertCount(3, $menus);
        $this->assertEquals('Beef', $menus[0]->name);
        $this->assertEquals('Salmon', $menus[1]->name);
        $this->assertEquals('Vegan', $menus[2]->name);

        $tables = DB::$socialEvents->tables($id);
        $this->assertCount(4, $tables);
        $this->assertEquals(1, $tables[0]->number);
        $this->assertEquals(10, $tables[0]->capacity);
        $this->assertEquals(4, $tables[3]->number);
        $this->assertEquals(8, $tables[3]->capacity);
    }

    public function testStoreTablesAndMenusAreOptional(): void
    {
        $this->loginAsAdmin();

        $response = $this->request('POST', '/social-events/new', [
            'name' => 'No-Table Dinner',
            'date' => '2099-06-15',
        ]);

        $this->assertEquals(303, $response->statusCode);
        $events = DB::$socialEvents->all();
        $this->assertCount(1, $events);
        $this->assertCount(0, DB::$socialEvents->menus($events[0]->id));
        $this->assertCount(0, DB::$socialEvents->tables($events[0]->id));
    }

    public function testStoreLinksToTournament(): void
    {
        $this->loginAsAdmin();
        $tournamentId = DB::$events->add('Club Championship', '2099-07-20', 50);

        $this->request('POST', '/social-events/new', [
            'name' => 'Gala Dinner',
            'date' => '2099-07-20',
            'menus' => 'Meat, Fish',
            'tables' => '10',
            'tournamentId' => (string) $tournamentId,
        ]);

        $events = DB::$socialEvents->all();
        $this->assertEquals($tournamentId, $events[0]->tournamentId);
    }

    public function testStoreValidatesRequiredFields(): void
    {
        $this->loginAsAdmin();

        $response = $this->request('POST', '/social-events/new', [
            'name' => '',
            'date' => '',
            'menus' => '',
            'tables' => '',
        ]);

        $this->assertEquals(303, $response->statusCode);
        $this->assertCount(0, DB::$socialEvents->all());
    }

    // ===== GET /social-events/{id} — access control =====

    public function testDetailRequiresLogin(): void
    {
        $id = $this->addSocialEvent();

        $response = $this->request('GET', "/social-events/$id");
        $this->assertEquals(303, $response->statusCode);
    }

    public function testDetailShowsForRegularUser(): void
    {
        $this->addUser();
        $this->loginAs('member', 'Pass123!');
        $id = $this->addSocialEvent();

        $response = $this->request('GET', "/social-events/$id");
        $this->assertEquals(200, $response->statusCode);
        $this->assertStringContainsString('Summer Dinner', $response->body);
    }

    public function testDetailShowsForAdmin(): void
    {
        $this->loginAsAdmin();
        $id = $this->addSocialEvent();

        $response = $this->request('GET', "/social-events/$id");
        $this->assertEquals(200, $response->statusCode);
    }

    public function testDetailReturns404ForNonexistentEvent(): void
    {
        $this->addUser();
        $this->loginAs('member', 'Pass123!');

        $response = $this->request('GET', '/social-events/99999');
        $this->assertEquals(404, $response->statusCode);
    }

    public function testDetailShowsMenuOptions(): void
    {
        $this->addUser();
        $this->loginAs('member', 'Pass123!');
        $id = $this->addSocialEvent(menus: 'Beef, Salmon, Vegan');

        $response = $this->request('GET', "/social-events/$id");
        $this->assertEquals(200, $response->statusCode);
        $this->assertStringContainsString('Beef', $response->body);
        $this->assertStringContainsString('Salmon', $response->body);
        $this->assertStringContainsString('Vegan', $response->body);
    }

    public function testDetailShowsTableOptions(): void
    {
        $this->addUser();
        $this->loginAs('member', 'Pass123!');
        $id = $this->addSocialEvent(tables: '10, 8');

        $response = $this->request('GET', "/social-events/$id");
        $this->assertEquals(200, $response->statusCode);
        $this->assertStringContainsString('1', $response->body); // table 1
    }

    // ===== GET /social-events/{id}/admin =====

    public function testAdminViewRequiresAdmin(): void
    {
        $id = $this->addSocialEvent();

        $response = $this->request('GET', "/social-events/$id/admin");
        $this->assertEquals(403, $response->statusCode);
    }

    public function testAdminViewForbiddenForRegularUser(): void
    {
        $this->addUser();
        $this->loginAs('member', 'Pass123!');
        $id = $this->addSocialEvent();

        $response = $this->request('GET', "/social-events/$id/admin");
        $this->assertEquals(403, $response->statusCode);
    }

    public function testAdminViewShowsForAdmin(): void
    {
        $this->loginAsAdmin();
        $id = $this->addSocialEvent();

        $response = $this->request('GET', "/social-events/$id/admin");
        $this->assertEquals(200, $response->statusCode);
        $this->assertStringContainsString('Summer Dinner', $response->body);
    }

    // ===== POST /social-events/{id} (update) =====

    public function testUpdateRequiresAdmin(): void
    {
        $id = $this->addSocialEvent();

        $response = $this->request('POST', "/social-events/$id", [
            'name' => 'Updated Name',
            'date' => '2099-06-15',
            'menus' => 'Meat',
            'tables' => '10',
        ]);
        $this->assertEquals(403, $response->statusCode);
    }

    public function testUpdateSocialEvent(): void
    {
        $this->loginAsAdmin();
        $id = $this->addSocialEvent();

        $response = $this->request('POST', "/social-events/$id", [
            'name' => 'Winter Gala',
            'date' => '2099-12-20',
            'menus' => 'Chicken, Fish',
            'tables' => '12, 12',
        ]);

        $this->assertEquals(303, $response->statusCode);

        $updated = DB::$socialEvents->get($id, 0);
        $this->assertEquals('Winter Gala', $updated->name);
        $this->assertEquals('2099-12-20', $updated->date);
        $this->assertEquals(24, $updated->totalCapacity);
    }

    public function testUpdateReplacesMenusAndTables(): void
    {
        $this->loginAsAdmin();
        $id = $this->addSocialEvent(menus: 'Old Menu', tables: '5');

        $this->request('POST', "/social-events/$id", [
            'name' => 'Summer Dinner',
            'date' => '2099-06-15',
            'menus' => 'New A, New B',
            'tables' => '10, 8',
        ]);

        $menus = DB::$socialEvents->menus($id);
        $this->assertCount(2, $menus);
        $this->assertEquals('New A', $menus[0]->name);

        $tables = DB::$socialEvents->tables($id);
        $this->assertCount(2, $tables);
        $this->assertEquals(10, $tables[0]->capacity);
    }

    public function testUpdateCannotRemoveMenuInUse(): void
    {
        $this->loginAsAdmin();
        $id = $this->addSocialEvent(menus: 'Meat, Fish', tables: '10');
        $userId = $this->addUser();
        $menus = DB::$socialEvents->menus($id);
        DB::$socialEvents->register($id, $userId, $menus[0]->id, null);

        $response = $this->request('POST', "/social-events/$id", [
            'name' => 'Summer Dinner',
            'date' => '2099-06-15',
            'menus' => 'Fish',
            'tables' => '10',
        ]);

        $this->assertEquals(303, $response->statusCode);
        $this->assertCount(2, DB::$socialEvents->menus($id));
    }

    public function testUpdateCannotRemoveTableInUse(): void
    {
        $this->loginAsAdmin();
        $id = $this->addSocialEvent(menus: 'Meat', tables: '10, 10');
        $userId = $this->addUser();
        $menus = DB::$socialEvents->menus($id);
        $tables = DB::$socialEvents->tables($id);
        DB::$socialEvents->register($id, $userId, $menus[0]->id, $tables[1]->id);

        $response = $this->request('POST', "/social-events/$id", [
            'name' => 'Summer Dinner',
            'date' => '2099-06-15',
            'menus' => 'Meat',
            'tables' => '10',
        ]);

        $this->assertEquals(303, $response->statusCode);
        $this->assertCount(2, DB::$socialEvents->tables($id));
    }

    public function testUpdateCannotReduceTableCapacityBelowRegistered(): void
    {
        $this->loginAsAdmin();
        $id = $this->addSocialEvent(menus: 'Meat', tables: '10');
        $userId = $this->addUser();
        $menus = DB::$socialEvents->menus($id);
        $tables = DB::$socialEvents->tables($id);
        DB::$socialEvents->register($id, $userId, $menus[0]->id, $tables[0]->id);

        $response = $this->request('POST', "/social-events/$id", [
            'name' => 'Summer Dinner',
            'date' => '2099-06-15',
            'menus' => 'Meat',
            'tables' => '0',
        ]);

        $this->assertEquals(303, $response->statusCode);
        $this->assertEquals(10, DB::$socialEvents->tables($id)[0]->capacity);
    }

    public function testUpdateCanExpandTablesWhenRegistrationsExist(): void
    {
        $this->loginAsAdmin();
        $id = $this->addSocialEvent(menus: 'Meat', tables: '10, 10, 10');
        $userId = $this->addUser();
        $menus = DB::$socialEvents->menus($id);
        $tables = DB::$socialEvents->tables($id);
        DB::$socialEvents->register($id, $userId, $menus[0]->id, $tables[0]->id);

        $response = $this->request('POST', "/social-events/$id", [
            'name' => 'Summer Dinner',
            'date' => '2099-06-15',
            'menus' => 'Meat',
            'tables' => '5, 5, 5, 5, 5, 5',
        ]);

        $this->assertEquals(303, $response->statusCode);
        $this->assertCount(6, DB::$socialEvents->tables($id));
        $this->assertEquals(5, DB::$socialEvents->tables($id)[0]->capacity);
    }

    // ===== POST /social-events/{id}/delete =====

    public function testDeleteRequiresAdmin(): void
    {
        $id = $this->addSocialEvent();

        $response = $this->request('POST', "/social-events/$id/delete");
        $this->assertEquals(403, $response->statusCode);
    }

    public function testDeleteSocialEvent(): void
    {
        $this->loginAsAdmin();
        $id = $this->addSocialEvent();

        $response = $this->request('POST', "/social-events/$id/delete");
        $this->assertEquals(303, $response->statusCode);

        $this->assertNull(DB::$socialEvents->get($id, 0));
    }

    public function testDeleteRedirectsToTournamentIfLinked(): void
    {
        $this->loginAsAdmin();
        $tournamentId = DB::$events->add('Club Championship', '2099-07-20', 50);
        $id = $this->addSocialEvent(tournamentId: $tournamentId);

        $response = $this->request('POST', "/social-events/$id/delete");
        $this->assertEquals(303, $response->statusCode);
        $this->assertNull(DB::$socialEvents->get($id, 0));
    }

    // ===== POST /social-events/{id}/lock and unlock =====

    public function testLockRequiresAdmin(): void
    {
        $id = $this->addSocialEvent();

        $response = $this->request('POST', "/social-events/$id/lock");
        $this->assertEquals(403, $response->statusCode);
    }

    public function testLockSocialEvent(): void
    {
        $this->loginAsAdmin();
        $id = $this->addSocialEvent();

        $this->request('POST', "/social-events/$id/lock");

        $this->assertTrue(DB::$socialEvents->isLocked($id));
    }

    public function testUnlockSocialEvent(): void
    {
        $this->loginAsAdmin();
        $id = $this->addSocialEvent();
        DB::$socialEvents->lock($id);

        $this->request('POST', "/social-events/$id/unlock");

        $this->assertFalse(DB::$socialEvents->isLocked($id));
    }

    // ===== POST /social-events/{id}/register =====

    public function testRegisterRequiresLogin(): void
    {
        $id = $this->addSocialEvent();
        $menus = DB::$socialEvents->menus($id);

        $response = $this->request('POST', "/social-events/$id/register", [
            'menu_id' => (string) $menus[0]->id,
        ]);
        $this->assertEquals(303, $response->statusCode);
    }

    public function testRegisterMemberForSocialEvent(): void
    {
        $userId = $this->addUser();
        $this->loginAs('member', 'Pass123!');
        $id = $this->addSocialEvent();
        $menus = DB::$socialEvents->menus($id);

        $response = $this->request('POST', "/social-events/$id/register", [
            'menu_id' => (string) $menus[0]->id,
            'table_id' => '',
        ]);

        $this->assertEquals(303, $response->statusCode);

        $event = DB::$socialEvents->get($id, $userId);
        $this->assertEquals(1, $event->userRegistered);
        $this->assertEquals(1, $event->registered);
    }

    public function testRegisterWithTableSelection(): void
    {
        $userId = $this->addUser();
        $this->loginAs('member', 'Pass123!');
        $id = $this->addSocialEvent();
        $menus = DB::$socialEvents->menus($id);
        $tables = DB::$socialEvents->tables($id);

        $this->request('POST', "/social-events/$id/register", [
            'menu_id' => (string) $menus[1]->id,
            'table_id' => (string) $tables[1]->id,
        ]);

        $reg = DB::$socialEvents->getUserRegistration($id, $userId);
        $this->assertNotNull($reg);
        $this->assertEquals($tables[1]->id, $reg->tableId);
        $this->assertEquals($menus[1]->name, $reg->menuName);
    }

    public function testRegisterLiberoNoTable(): void
    {
        $userId = $this->addUser();
        $this->loginAs('member', 'Pass123!');
        $id = $this->addSocialEvent();
        $menus = DB::$socialEvents->menus($id);

        $this->request('POST', "/social-events/$id/register", [
            'menu_id' => (string) $menus[0]->id,
            'table_id' => '',
        ]);

        $reg = DB::$socialEvents->getUserRegistration($id, $userId);
        $this->assertNotNull($reg);
        $this->assertNull($reg->tableId);
    }

    public function testRegisterBlockedWhenEventFull(): void
    {
        $userId = $this->addUser();
        $this->loginAs('member', 'Pass123!');

        // Event with only 1 seat
        $id = $this->addSocialEvent(tables: '1');
        $menus = DB::$socialEvents->menus($id);
        $tables = DB::$socialEvents->tables($id);

        // Fill the seat
        DB::$socialEvents->register($id, $userId, $menus[0]->id, $tables[0]->id);

        // Second user tries to register
        $userId2 = $this->addUser('member2', 'Pass123!');
        $this->loginAs('member2', 'Pass123!');

        $response = $this->request('POST', "/social-events/$id/register", [
            'menu_id' => (string) $menus[0]->id,
            'table_id' => '',
        ]);

        $this->assertEquals(303, $response->statusCode);
        // Should still be 1 registration
        $event = DB::$socialEvents->get($id, 0);
        $this->assertEquals(1, $event->registered);
    }

    public function testRegisterBlockedWhenTableFull(): void
    {
        $userId = $this->addUser();
        $this->loginAs('member', 'Pass123!');

        $id = $this->addSocialEvent(tables: '1, 10');
        $menus = DB::$socialEvents->menus($id);
        $tables = DB::$socialEvents->tables($id);

        // Fill table 1 (capacity 1)
        DB::$socialEvents->register($id, $userId, $menus[0]->id, $tables[0]->id);

        $userId2 = $this->addUser('member2', 'Pass123!');
        $this->loginAs('member2', 'Pass123!');

        $response = $this->request('POST', "/social-events/$id/register", [
            'menu_id' => (string) $menus[0]->id,
            'table_id' => (string) $tables[0]->id, // try to take full table
        ]);

        $this->assertEquals(303, $response->statusCode);
        $event = DB::$socialEvents->get($id, 0);
        $this->assertEquals(1, $event->registered); // second user was not registered
    }

    public function testRegisterBlockedWithInvalidMenu(): void
    {
        $this->addUser();
        $this->loginAs('member', 'Pass123!');
        $id = $this->addSocialEvent();

        $response = $this->request('POST', "/social-events/$id/register", [
            'menu_id' => '99999',
        ]);

        $this->assertEquals(303, $response->statusCode);
        $event = DB::$socialEvents->get($id, 0);
        $this->assertEquals(0, $event->registered);
    }

    public function testRegisterBlockedWhenLocked(): void
    {
        $userId = $this->addUser();
        $this->loginAs('member', 'Pass123!');
        $id = $this->addSocialEvent();
        DB::$socialEvents->lock($id);
        $menus = DB::$socialEvents->menus($id);

        $this->request('POST', "/social-events/$id/register", [
            'menu_id' => (string) $menus[0]->id,
        ]);

        $event = DB::$socialEvents->get($id, $userId);
        $this->assertEquals(0, $event->registered);
    }

    public function testCannotRegisterTwice(): void
    {
        $userId = $this->addUser();
        $this->loginAs('member', 'Pass123!');
        $id = $this->addSocialEvent();
        $menus = DB::$socialEvents->menus($id);

        $this->request('POST', "/social-events/$id/register", [
            'menu_id' => (string) $menus[0]->id,
        ]);
        $this->request('POST', "/social-events/$id/register", [
            'menu_id' => (string) $menus[1]->id,
        ]);

        $event = DB::$socialEvents->get($id, $userId);
        $this->assertEquals(1, $event->registered);
    }

    // ===== POST /social-events/{id}/unregister =====

    public function testUnregisterRequiresLogin(): void
    {
        $id = $this->addSocialEvent();

        $response = $this->request('POST', "/social-events/$id/unregister");
        $this->assertEquals(303, $response->statusCode);
    }

    public function testUnregisterMember(): void
    {
        $userId = $this->addUser();
        $this->loginAs('member', 'Pass123!');
        $id = $this->addSocialEvent();
        $menus = DB::$socialEvents->menus($id);
        DB::$socialEvents->register($id, $userId, $menus[0]->id, null);

        $response = $this->request('POST', "/social-events/$id/unregister");

        $this->assertEquals(303, $response->statusCode);
        $event = DB::$socialEvents->get($id, $userId);
        $this->assertEquals(0, $event->userRegistered);
    }

    public function testUnregisterBlockedWhenLocked(): void
    {
        $userId = $this->addUser();
        $this->loginAs('member', 'Pass123!');
        $id = $this->addSocialEvent();
        $menus = DB::$socialEvents->menus($id);
        DB::$socialEvents->register($id, $userId, $menus[0]->id, null);
        DB::$socialEvents->lock($id);

        $this->request('POST', "/social-events/$id/unregister");

        $event = DB::$socialEvents->get($id, $userId);
        $this->assertEquals(1, $event->userRegistered); // still registered
    }

    // ===== GET /social-events/{id}/guests/new =====

    public function testGuestFormIsPublic(): void
    {
        $id = $this->addSocialEvent();

        $response = $this->request('GET', "/social-events/$id/guests/new");

        $this->assertNotEquals(403, $response->statusCode);
        $this->assertNotEquals(404, $response->statusCode);
    }

    public function testGuestFormReturns404ForUnknownEvent(): void
    {
        $response = $this->request('GET', '/social-events/99999/guests/new');
        $this->assertEquals(404, $response->statusCode);
    }

    public function testGuestFormShowsMenuOptions(): void
    {
        $id = $this->addSocialEvent(menus: 'Lamb, Tuna');

        $response = $this->request('GET', "/social-events/$id/guests/new");

        $this->assertStringContainsString('Lamb', $response->body);
        $this->assertStringContainsString('Tuna', $response->body);
    }

    // ===== POST /social-events/{id}/guests/new =====

    public function testStoreGuestRegistration(): void
    {
        $id = $this->addSocialEvent();
        $menus = DB::$socialEvents->menus($id);

        $response = $this->request('POST', "/social-events/$id/guests/new", [
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane@example.com',
            'menu_id' => (string) $menus[0]->id,
            'table_id' => '',
        ]);

        $this->assertEquals(303, $response->statusCode);

        $event = DB::$socialEvents->get($id, 0);
        $this->assertEquals(1, $event->registered);

        $regs = DB::$socialEvents->registrations($id);
        $this->assertCount(1, $regs);
        $this->assertEquals('Jane', $regs[0]->firstName);
        $this->assertEquals('Doe', $regs[0]->lastName);
        $this->assertEquals('jane@example.com', $regs[0]->email);
        $this->assertNull($regs[0]->userId);
    }

    public function testStoreGuestWithTableSelection(): void
    {
        $id = $this->addSocialEvent();
        $menus = DB::$socialEvents->menus($id);
        $tables = DB::$socialEvents->tables($id);

        $this->request('POST', "/social-events/$id/guests/new", [
            'first_name' => 'Bob',
            'last_name' => 'Smith',
            'email' => 'bob@example.com',
            'menu_id' => (string) $menus[2]->id,
            'table_id' => (string) $tables[2]->id,
        ]);

        $regs = DB::$socialEvents->registrations($id);
        $this->assertEquals($tables[2]->id, $regs[0]->tableId);
        $this->assertEquals($tables[2]->number, $regs[0]->tableNumber);
    }

    public function testStoreGuestBlockedWhenEventFull(): void
    {
        $id = $this->addSocialEvent(tables: '1');
        $menus = DB::$socialEvents->menus($id);
        $tables = DB::$socialEvents->tables($id);
        DB::$socialEvents->registerGuest($id, 'First', 'Person', 'first@example.com', $menus[0]->id, $tables[0]->id);

        $response = $this->request('POST', "/social-events/$id/guests/new", [
            'first_name' => 'Second',
            'last_name' => 'Person',
            'email' => 'second@example.com',
            'menu_id' => (string) $menus[0]->id,
            'table_id' => '',
        ]);

        $this->assertEquals(303, $response->statusCode);
        $event = DB::$socialEvents->get($id, 0);
        $this->assertEquals(1, $event->registered);
    }

    public function testStoreGuestValidatesRequiredFields(): void
    {
        $id = $this->addSocialEvent();

        $response = $this->request('POST', "/social-events/$id/guests/new", [
            'first_name' => '',
            'last_name' => '',
            'email' => 'not-an-email',
            'menu_id' => '',
        ]);

        $this->assertEquals(303, $response->statusCode);
        $event = DB::$socialEvents->get($id, 0);
        $this->assertEquals(0, $event->registered);
    }

    // ===== POST /social-events/{id}/registrations/{regId}/delete =====

    public function testDeleteRegistrationRequiresAdmin(): void
    {
        $id = $this->addSocialEvent();
        $menus = DB::$socialEvents->menus($id);
        DB::$socialEvents->registerGuest($id, 'Jane', 'Doe', 'j@example.com', $menus[0]->id, null);
        $regs = DB::$socialEvents->registrations($id);

        $response = $this->request('POST', "/social-events/$id/registrations/{$regs[0]->id}/delete");
        $this->assertEquals(403, $response->statusCode);
    }

    public function testDeleteRegistration(): void
    {
        $this->loginAsAdmin();
        $id = $this->addSocialEvent();
        $menus = DB::$socialEvents->menus($id);
        DB::$socialEvents->registerGuest($id, 'Jane', 'Doe', 'j@example.com', $menus[0]->id, null);
        $regs = DB::$socialEvents->registrations($id);

        $response = $this->request('POST', "/social-events/$id/registrations/{$regs[0]->id}/delete");

        $this->assertEquals(303, $response->statusCode);
        $this->assertCount(0, DB::$socialEvents->registrations($id));
    }

    // ===== Repository: isFull / isTableFull =====

    public function testIsFullReturnsFalseWhenSeatsAvailable(): void
    {
        $id = $this->addSocialEvent(tables: '10');
        $this->assertFalse(DB::$socialEvents->isFull($id));
    }

    public function testIsFullReturnsTrueWhenAllSeatsTaken(): void
    {
        $userId = $this->addUser();
        $id = $this->addSocialEvent(tables: '1');
        $menus = DB::$socialEvents->menus($id);
        $tables = DB::$socialEvents->tables($id);
        DB::$socialEvents->register($id, $userId, $menus[0]->id, $tables[0]->id);

        $this->assertTrue(DB::$socialEvents->isFull($id));
    }

    public function testIsTableFullReturnsTrueWhenFull(): void
    {
        $userId = $this->addUser();
        $id = $this->addSocialEvent(tables: '1, 10');
        $menus = DB::$socialEvents->menus($id);
        $tables = DB::$socialEvents->tables($id);
        DB::$socialEvents->register($id, $userId, $menus[0]->id, $tables[0]->id);

        $this->assertTrue(DB::$socialEvents->isTableFull($tables[0]->id));
        $this->assertFalse(DB::$socialEvents->isTableFull($tables[1]->id));
    }

    // ===== Tournament integration: Events/Detail.php shows social event prompt =====

    public function testTournamentDetailShowsSocialEventPrompt(): void
    {
        $this->addUser();
        $this->loginAs('member', 'Pass123!');
        $tournamentId = DB::$events->add('Club Championship', '2099-07-20', 50);
        $this->addSocialEvent(name: 'Gala Dinner', tournamentId: $tournamentId);

        $response = $this->request('GET', "/events/$tournamentId");

        $this->assertEquals(200, $response->statusCode);
        $this->assertStringContainsString('Gala Dinner', $response->body);
    }

    public function testTournamentDetailNoSocialEventPromptWhenNoneLinked(): void
    {
        $this->addUser();
        $this->loginAs('member', 'Pass123!');
        $tournamentId = DB::$events->add('Club Championship', '2099-07-20', 50);

        $response = $this->request('GET', "/events/$tournamentId");

        $this->assertEquals(200, $response->statusCode);
        $this->assertStringNotContainsString('social_events.', $response->body);
    }

    public function testTournamentAdminShowsCreateSocialEventButton(): void
    {
        $this->loginAsAdmin();
        $tournamentId = DB::$events->add('Club Championship', '2099-07-20', 50);

        $response = $this->request('GET', "/events/$tournamentId/admin");

        $this->assertEquals(200, $response->statusCode);
        $this->assertStringContainsString("/social-events/new?tournamentId=$tournamentId", $response->body);
    }

    public function testTournamentAdminShowsEditSocialEventWhenLinked(): void
    {
        $this->loginAsAdmin();
        $tournamentId = DB::$events->add('Club Championship', '2099-07-20', 50);
        $socialId = $this->addSocialEvent(tournamentId: $tournamentId);

        $response = $this->request('GET', "/events/$tournamentId/admin");

        $this->assertEquals(200, $response->statusCode);
        $this->assertStringContainsString("/social-events/$socialId/admin", $response->body);
    }

    // ===== Social event detail links back to linked tournament =====

    public function testSocialEventDetailShowsTournamentLink(): void
    {
        $this->addUser();
        $this->loginAs('member', 'Pass123!');
        $tournamentId = DB::$events->add('Club Championship', '2099-07-20', 50);
        $socialId = $this->addSocialEvent(name: 'Gala Dinner', tournamentId: $tournamentId);

        $response = $this->request('GET', "/social-events/$socialId");

        $this->assertEquals(200, $response->statusCode);
        $this->assertStringContainsString("/events/$tournamentId", $response->body);
        $this->assertStringContainsString('Club Championship', $response->body);
    }

    public function testSocialEventDetailNoTournamentLinkWhenUnlinked(): void
    {
        $this->addUser();
        $this->loginAs('member', 'Pass123!');
        $socialId = $this->addSocialEvent(name: 'Standalone Dinner');

        $response = $this->request('GET', "/social-events/$socialId");

        $this->assertEquals(200, $response->statusCode);
        $this->assertStringNotContainsString('/events/', $response->body);
    }

    public function testTournamentDetailShowsSocialEventLink(): void
    {
        $this->addUser();
        $this->loginAs('member', 'Pass123!');
        $tournamentId = DB::$events->add('Club Championship', '2099-07-20', 50);
        $socialId = $this->addSocialEvent(name: 'Gala Dinner', tournamentId: $tournamentId);

        $response = $this->request('GET', "/events/$tournamentId");

        $this->assertEquals(200, $response->statusCode);
        $this->assertStringContainsString("/social-events/$socialId", $response->body);
        $this->assertStringContainsString('Gala Dinner', $response->body);
    }

    // ===== Home calendar shows social events =====

    public function testHomeCalendarShowsSocialEvents(): void
    {
        $this->addUser();
        $this->loginAs('member', 'Pass123!');
        $this->addSocialEvent(name: 'Autumn Dinner', date: '2099-09-15');

        $response = $this->request('GET', '/', ['date' => '2099-09-1']);

        $this->assertEquals(200, $response->statusCode);
        $this->assertStringContainsString('Autumn Dinner', $response->body);
    }
}
