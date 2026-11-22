<?php

declare(strict_types=1);

namespace TP\Tests\Integration\Api;

use TP\Models\DB;
use TP\Tests\Integration\IntegrationTestCase;

class EventsApiTest extends IntegrationTestCase
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

    private function registrationsOf(int $eventId): array
    {
        return DB::$events->registrations($eventId);
    }

    public function testAnonymousGets401(): void
    {
        $eventId = DB::$events->add('Cup', '2099-06-01', 2);

        $get = $this->json('GET', "/api/events/{$eventId}");
        $this->assertSame(401, $get->statusCode);
        $this->assertSame(['error' => 'auth.login_required'], $get->json());

        $post = $this->json('POST', "/api/events/{$eventId}/registrations", ['comment' => '']);
        $this->assertSame(401, $post->statusCode);
    }

    public function testDetailShowsEventParticipantsGuestsAndOwnRegistration(): void
    {
        $this->member('jane');
        $otherId = $this->member('bob', 'Bob', 'Builder');
        $eventId = DB::$events->add('Cup', '2099-06-01', 2, false, true, 'Nice day', 'CHF 50', 'CHF 80', '2099-05-30 12:00:00');
        DB::$events->register($eventId, $otherId, 'Hi');
        DB::$guests->add($eventId, true, 'Gus', 'Guest', null, null, null);
        $this->apiLoginAs('jane', 'Pass123!');

        $response = $this->json('GET', "/api/events/{$eventId}");

        $this->assertSame(200, $response->statusCode);
        $data = $response->json();
        $this->assertNull($data['myRegistration']);
        $this->assertSame('Bob Builder', $data['participants'][0]['name']);
        $this->assertSame('registered', $data['participants'][0]['state']);
        $this->assertSame('Gus', $data['guests'][0]['firstName']);
        $this->assertSame('Guest', $data['guests'][0]['lastName']);
        $this->assertSame('none', $data['event']['userState']);
        $this->assertSame('Cup', $data['event']['name']);
        $this->assertSame('Nice day', $data['description']);
        $this->assertSame('CHF 50', $data['priceMembers']);
        $this->assertSame('CHF 80', $data['priceGuests']);
        $this->assertSame('2099-05-30T12:00:00', $data['registrationClose']);
        $this->assertNull($data['socialEvent']);
    }

    public function testRegisterReturnsDetailWithRegistration(): void
    {
        $this->member();
        $eventId = DB::$events->add('Cup', '2099-06-01', 2);
        $this->apiLoginAs('jane', 'Pass123!');

        $response = $this->json('POST', "/api/events/{$eventId}/registrations", ['comment' => 'Mit Trolley']);

        $this->assertSame(200, $response->statusCode);
        $data = $response->json();
        $this->assertSame(['state' => 'registered', 'comment' => 'Mit Trolley'], $data['myRegistration']);
        $this->assertSame(1, $data['event']['joined']);
        $this->assertSame('registered', $data['event']['userState']);
    }

    public function testRegisterWhenFullGoesToWaitlist(): void
    {
        $this->member();
        $otherId = $this->member('bob', 'Bob', 'Builder');
        $eventId = DB::$events->add('Cup', '2099-06-01', 1);
        DB::$events->register($eventId, $otherId, '');
        $this->apiLoginAs('jane', 'Pass123!');

        $data = $this->json('POST', "/api/events/{$eventId}/registrations", ['comment' => ''])->json();

        $this->assertSame('waitlist', $data['myRegistration']['state']);
        $this->assertSame(1, $data['event']['waitlist']);
    }

    public function testRegisterOnLockedEventIs409(): void
    {
        $this->member();
        $eventId = DB::$events->add('Cup', '2099-06-01', 2);
        DB::$events->lock($eventId);
        $this->apiLoginAs('jane', 'Pass123!');

        $response = $this->json('POST', "/api/events/{$eventId}/registrations", ['comment' => '']);

        $this->assertSame(409, $response->statusCode);
        $this->assertSame(['error' => 'events.locked_message'], $response->json());
        $this->assertSame([], $this->registrationsOf($eventId));
    }

    public function testRegisterAfterDeadlineIs409(): void
    {
        $this->member();
        $eventId = DB::$events->add('Closed', '2099-06-01', 10, false, true, null, null, null, '2000-01-01 00:00:00');
        $this->apiLoginAs('jane', 'Pass123!');

        $response = $this->json('POST', "/api/events/{$eventId}/registrations", ['comment' => '']);

        $this->assertSame(409, $response->statusCode);
        $this->assertSame([], $this->registrationsOf($eventId));
    }

    public function testRegisterAgainUpdatesComment(): void
    {
        $this->member();
        $eventId = DB::$events->add('Cup', '2099-06-01', 2);
        $this->apiLoginAs('jane', 'Pass123!');
        $this->json('POST', "/api/events/{$eventId}/registrations", ['comment' => 'a']);

        $data = $this->json('POST', "/api/events/{$eventId}/registrations", ['comment' => 'b'])->json();

        $this->assertSame('b', $data['myRegistration']['comment']);
        $this->assertCount(1, $data['participants']);
    }

    public function testUpdateCommentChangesOwnComment(): void
    {
        $userId = $this->member();
        $eventId = DB::$events->add('Cup', '2099-06-01', 2);
        DB::$events->register($eventId, $userId, 'Alt');
        $this->apiLoginAs('jane', 'Pass123!');

        $response = $this->json('PATCH', "/api/events/{$eventId}/registrations/me", ['comment' => 'Neu']);

        $this->assertSame(200, $response->statusCode);
        $this->assertSame('Neu', $response->json()['myRegistration']['comment']);
    }

    public function testUpdateCommentWhenNotRegisteredIs404(): void
    {
        $this->member();
        $eventId = DB::$events->add('Cup', '2099-06-01', 2);
        $this->apiLoginAs('jane', 'Pass123!');

        $response = $this->json('PATCH', "/api/events/{$eventId}/registrations/me", ['comment' => 'Neu']);

        $this->assertSame(404, $response->statusCode);
        $this->assertSame(['error' => 'auth.not_registered'], $response->json());
    }

    public function testUpdateCommentOnLockedEventIs409(): void
    {
        $userId = $this->member();
        $eventId = DB::$events->add('Cup', '2099-06-01', 2);
        DB::$events->register($eventId, $userId, 'Alt');
        DB::$events->lock($eventId);
        $this->apiLoginAs('jane', 'Pass123!');

        $response = $this->json('PATCH', "/api/events/{$eventId}/registrations/me", ['comment' => 'Neu']);

        $this->assertSame(409, $response->statusCode);
        $this->assertSame('Alt', $this->registrationsOf($eventId)[0]->comment);
    }

    public function testUnregisterRemovesOwnRegistrationAndPromotesWaitlist(): void
    {
        $userId = $this->member();
        $otherId = $this->member('bob', 'Bob', 'Builder');
        $eventId = DB::$events->add('Cup', '2099-06-01', 1);
        DB::$events->register($eventId, $userId, '');
        DB::$events->register($eventId, $otherId, '');
        $this->apiLoginAs('jane', 'Pass123!');

        $response = $this->json('DELETE', "/api/events/{$eventId}/registrations/me");

        $this->assertSame(200, $response->statusCode);
        $data = $response->json();
        $this->assertNull($data['myRegistration']);
        $this->assertCount(1, $data['participants']);
        $this->assertSame('Bob Builder', $data['participants'][0]['name']);
        $this->assertSame('registered', $data['participants'][0]['state']);
    }

    public function testUnregisterOnLockedEventIs409(): void
    {
        $userId = $this->member();
        $eventId = DB::$events->add('Cup', '2099-06-01', 2);
        DB::$events->register($eventId, $userId, '');
        DB::$events->lock($eventId);
        $this->apiLoginAs('jane', 'Pass123!');

        $response = $this->json('DELETE', "/api/events/{$eventId}/registrations/me");

        $this->assertSame(409, $response->statusCode);
        $this->assertCount(1, $this->registrationsOf($eventId));
    }

    public function testUnknownEventIs404(): void
    {
        $this->member();
        $this->apiLoginAs('jane', 'Pass123!');

        foreach ([
            ['GET', '/api/events/999999', []],
            ['POST', '/api/events/999999/registrations', ['comment' => '']],
            ['PATCH', '/api/events/999999/registrations/me', ['comment' => '']],
            ['DELETE', '/api/events/999999/registrations/me', []],
        ] as [$method, $uri, $body]) {
            $response = $this->json($method, $uri, $body);
            $this->assertSame(404, $response->statusCode, "$method $uri");
            $this->assertSame(['error' => 'events.not_found'], $response->json(), "$method $uri");
        }
    }

    public function testDetailIncludesLinkedSocialEvent(): void
    {
        $this->member();
        $eventId = DB::$events->add('Cup', '2099-06-01', 2);
        $socialId = DB::$socialEvents->add('Dinner', '2099-06-01', $eventId);
        $this->apiLoginAs('jane', 'Pass123!');

        $data = $this->json('GET', "/api/events/{$eventId}")->json();

        $this->assertSame($socialId, $data['socialEvent']['id']);
        $this->assertFalse($data['socialEvent']['userRegistered']);
    }

    public function testParticipantHandicapOnlyWhenTheFlavourHasIt(): void
    {
        $userId = DB::$users->create('hcp', 'Pass123!', true, null, null, 'Hans', 'Hcp');
        DB::$users->updateExtra($userId, ['handicap' => '12.4']);
        $eventId = DB::$events->add('Hcp', '2099-05-01', 10);
        DB::$events->register($eventId, $userId, '');
        $this->apiLoginAs('hcp', 'Pass123!');

        $this->assertNull($this->json('GET', "/api/events/{$eventId}")->json()['participants'][0]['handicap']);

        \TP\Core\Flavor::override(['userFields' => ['handicap']]);
        $this->assertSame(12.4, $this->json('GET', "/api/events/{$eventId}")->json()['participants'][0]['handicap']);
    }
}
