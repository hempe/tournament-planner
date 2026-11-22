<?php

declare(strict_types=1);

namespace TP\Tests\Integration\Core;

use TP\Core\Flavor;
use TP\Models\DB;
use TP\Tests\Integration\IntegrationTestCase;

class FeatureRouteTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanDatabase();
    }

    public function testGuestRoutesAre404WhenGuestsAreOff(): void
    {
        $eventId = DB::$events->add('Open', '2099-05-01', 10);
        $this->assertSame(200, $this->json('GET', "/api/events/{$eventId}/guest-info")->statusCode);

        Flavor::override(['guests' => false]);
        $response = $this->json('GET', "/api/events/{$eventId}/guest-info");
        $this->assertSame(404, $response->statusCode);
        $this->assertSame('errors.not_found', $response->json()['error']);

        $this->apiLoginAs('admin', 'Admin123!');
        $this->assertSame(404, $this->json('GET', "/api/events/{$eventId}/guests/1")->statusCode);
    }

    public function testSocialEventRoutesAre404WhenSocialEventsAreOff(): void
    {
        $this->apiLoginAs('admin', 'Admin123!');
        Flavor::override(['socialEvents' => false]);

        $this->assertSame(404, $this->json('GET', '/api/social-events/1')->statusCode);
        $this->assertSame(404, $this->json('POST', '/api/social-events', ['name' => 'x'])->statusCode);
    }

    public function testFeatureCheckRunsBeforeAuth(): void
    {
        Flavor::override(['socialEvents' => false]);
        // anonymous: would be 401 if the feature were on
        $this->assertSame(404, $this->json('GET', '/api/social-events/1')->statusCode);
    }
}
