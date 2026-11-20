<?php

declare(strict_types=1);

namespace TP\Tests\Integration\Api;

use TP\Models\DB;
use TP\Tests\Integration\IntegrationTestCase;

class CalendarApiTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanDatabase();
    }

    public function testAnonymousSeesEventsOfTheMonthWithoutPersonalState(): void
    {
        DB::$events->add('June Cup', '2099-06-10', 20);
        DB::$events->add('Early June', '2099-06-02', 20);
        DB::$events->add('July Cup', '2099-07-01', 20);

        $response = $this->json('GET', '/api/calendar/2099-06');

        $this->assertSame(200, $response->statusCode);
        $data = $response->json();
        $this->assertSame('2099-06', $data['month']);
        $this->assertSame(['Early June', 'June Cup'], array_column($data['events'], 'name'));
        $this->assertSame('none', $data['events'][0]['userState']);
        $this->assertSame([], $data['socialEvents']);
    }

    public function testMemberSeesOwnRegistrationState(): void
    {
        $userId = DB::$users->create('jane', 'Pass123!');
        $eventId = DB::$events->add('Cup', '2099-06-10', 20);
        DB::$events->register($eventId, $userId, '');
        $this->apiLoginAs('jane', 'Pass123!');

        $event = $this->json('GET', '/api/calendar/2099-06')->json()['events'][0];

        $this->assertSame('registered', $event['userState']);
        $this->assertSame(1, $event['joined']);
        $this->assertSame(19, $event['available']);
    }

    public function testSocialEventsAppearInTheirMonth(): void
    {
        DB::$socialEvents->add('Dinner', '2099-06-10', null, null, null, 'Fisch,Fleisch', '4,6');
        DB::$socialEvents->add('July Dinner', '2099-07-10');

        $data = $this->json('GET', '/api/calendar/2099-06')->json();

        $this->assertCount(1, $data['socialEvents']);
        $this->assertSame('Dinner', $data['socialEvents'][0]['name']);
        $this->assertSame('2099-06-10', $data['socialEvents'][0]['date']);
        $this->assertFalse($data['socialEvents'][0]['userRegistered']);
    }

    public function testMalformedMonthIs404(): void
    {
        foreach (['2099-6', 'june', '2099-13', '2099-06-01'] as $month) {
            $response = $this->json('GET', "/api/calendar/{$month}");
            $this->assertSame(404, $response->statusCode, $month);
            $this->assertSame(['error' => 'errors.not_found'], $response->json(), $month);
        }
    }
}
