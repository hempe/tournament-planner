<?php

declare(strict_types=1);

namespace TP\Tests\Integration\Models;

use TP\Models\DB;
use TP\Tests\Integration\IntegrationTestCase;

class SocialEventRepositoryTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanDatabase();
    }

    private function addSocialEvent(string $tables): int
    {
        return DB::$socialEvents->add('Dinner', '2099-06-01', null, null, null, 'Meat,Fish', $tables);
    }

    public function testIsFullReturnsFalseWhenNoTablesConfigured(): void
    {
        $this->assertFalse(DB::$socialEvents->isFull($this->addSocialEvent('')));
    }

    public function testIsFullReturnsFalseWhenSeatsAvailable(): void
    {
        $this->assertFalse(DB::$socialEvents->isFull($this->addSocialEvent('10')));
    }

    public function testIsFullReturnsTrueWhenAllSeatsTaken(): void
    {
        $userId = DB::$users->create('member', 'Pass123!');
        $id = $this->addSocialEvent('1');
        DB::$socialEvents->register($id, $userId, DB::$socialEvents->menus($id)[0]->id, DB::$socialEvents->tables($id)[0]->id);

        $this->assertTrue(DB::$socialEvents->isFull($id));
    }

    public function testIsTableFullReturnsTrueOnlyForTheFullTable(): void
    {
        $userId = DB::$users->create('member', 'Pass123!');
        $id = $this->addSocialEvent('1, 10');
        $tables = DB::$socialEvents->tables($id);
        DB::$socialEvents->register($id, $userId, DB::$socialEvents->menus($id)[0]->id, $tables[0]->id);

        $this->assertTrue(DB::$socialEvents->isTableFull($tables[0]->id));
        $this->assertFalse(DB::$socialEvents->isTableFull($tables[1]->id));
    }

    public function testDeleteWorksWhenRegistrationsReferenceMenus(): void
    {
        // social_registrations.menuId has no ON DELETE rule, so the cascade from
        // social_events to social_menus used to fail as soon as someone picked a menu.
        $userId = DB::$users->create('diner', 'Pass123!');
        $id = DB::$socialEvents->add('Dinner', '2099-06-01', null, null, null, 'Fisch,Fleisch', '4');
        $menu = DB::$socialEvents->menus($id)[0];
        DB::$socialEvents->register($id, $userId, $menu->id, null);
        DB::$socialEvents->registerGuest($id, 'Gus', 'Guest', 'g@example.com', $menu->id, null);

        DB::$socialEvents->delete($id);

        $this->assertNull(DB::$socialEvents->get($id, 0));
        $this->assertSame([], DB::$socialEvents->registrations($id));
    }
}
