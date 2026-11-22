<?php

declare(strict_types=1);

namespace TP\Tests\Integration\Core;

use PHPUnit\Framework\TestCase;
use TP\Core\HttpStatus;
use TP\Core\Response;

class ResponseTest extends TestCase
{
    public function testOkCreates200Response(): void
    {
        $response = Response::ok('Hello');
        $this->assertEquals(HttpStatus::OK, $response->getStatus());
    }

    public function testJsonCreatesJsonResponse(): void
    {
        $response = Response::json(['key' => 'value']);
        $this->assertEquals(HttpStatus::OK, $response->getStatus());
    }

    public function testJsonWithCustomStatus(): void
    {
        $response = Response::json(['error' => 'bad'], HttpStatus::BAD_REQUEST);
        $this->assertEquals(HttpStatus::BAD_REQUEST, $response->getStatus());
    }

    public function testIsRedirectForSeeOther(): void
    {
        $response = Response::redirect('/somewhere');
        $this->assertTrue($response->isRedirect());
    }

    public function testIsRedirectForMovedPermanently(): void
    {
        $response = Response::redirect('/somewhere', HttpStatus::MOVED_PERMANENTLY);
        $this->assertTrue($response->isRedirect());
    }

    public function testIsRedirectForTemporaryRedirect(): void
    {
        $response = Response::redirect('/somewhere', HttpStatus::TEMPORARY_REDIRECT);
        $this->assertTrue($response->isRedirect());
    }

    public function testIsRedirectForPermanentRedirect(): void
    {
        $response = Response::redirect('/somewhere', HttpStatus::PERMANENT_REDIRECT);
        $this->assertTrue($response->isRedirect());
    }

    public function testIsRedirectReturnsFalseFor200(): void
    {
        $response = Response::ok('hello');
        $this->assertFalse($response->isRedirect());
    }

    public function testIsRedirectReturnsFalseFor404(): void
    {
        $response = Response::json([], HttpStatus::NOT_FOUND);
        $this->assertFalse($response->isRedirect());
    }

    public function testApiErrorWithoutFields(): void
    {
        $response = Response::apiError('errors.not_found', HttpStatus::NOT_FOUND);

        $this->assertSame(HttpStatus::NOT_FOUND, $response->getStatus());
        $this->assertSame(['error' => 'errors.not_found'], json_decode($response->getContent(), true));
    }

    public function testApiErrorWithFields(): void
    {
        $fields = ['name' => ['key' => 'validation.required']];
        $response = Response::apiError('validation.failed', HttpStatus::UNPROCESSABLE_ENTITY, $fields);

        $this->assertSame(
            ['error' => 'validation.failed', 'fields' => $fields],
            json_decode($response->getContent(), true)
        );
    }

    public function testApiErrorWithParams(): void
    {
        $response = Response::apiError('social_events.menu_in_use', HttpStatus::CONFLICT, [], ['menu' => 'Fisch', 'count' => 3]);

        $this->assertSame(
            ['error' => 'social_events.menu_in_use', 'params' => ['menu' => 'Fisch', 'count' => 3]],
            json_decode($response->getContent(), true)
        );
    }

    public function testConflictStatusExists(): void
    {
        $this->assertSame(409, HttpStatus::CONFLICT->value);
    }

    public function testNoContent(): void
    {
        $response = Response::noContent();

        $this->assertSame(HttpStatus::NO_CONTENT, $response->getStatus());
        $this->assertSame('', $response->getContent());
    }

    public function testJsonKeepsUnicodeAndSlashesReadable(): void
    {
        $response = Response::json(['text' => 'Apéro / Turnier']);

        $this->assertSame('{"text":"Apéro / Turnier"}', $response->getContent());
    }

    public function testWithHeaderReturnsCopy(): void
    {
        $original = Response::ok('x');
        $copy = $original->withHeader('X-App-Version', '1.0');

        $this->assertSame('1.0', $copy->getHeaders()['X-App-Version']);
        $this->assertArrayNotHasKey('X-App-Version', $original->getHeaders());
    }

    public function testWithCookieStoresOptions(): void
    {
        $response = Response::ok()->withCookie('XSRF-TOKEN', 'abc', httpOnly: false, secure: true);

        $cookie = $response->getCookies()['XSRF-TOKEN'];
        $this->assertSame('abc', $cookie['value']);
        $this->assertFalse($cookie['httponly']);
        $this->assertTrue($cookie['secure']);
        $this->assertSame('Strict', $cookie['samesite']);
        $this->assertSame('/', $cookie['path']);
    }

    public function testSendRecordsLastSentResponse(): void
    {
        Response::resetLastSent();
        $response = Response::json(['a' => 1])->withHeader('X-Test', 'yes')->withCookie('C', 'v');

        ob_start();
        $response->send();
        ob_end_clean();

        $sent = Response::lastSent();
        $this->assertSame(200, $sent['status']);
        $this->assertSame('yes', $sent['headers']['X-Test']);
        $this->assertSame('v', $sent['cookies']['C']['value']);
    }

    public function testJsonSerialisesDtoObjects(): void
    {
        $dto = new \TP\Api\Dto\SessionDto(null);

        $this->assertSame('{"user":null}', Response::json($dto)->getContent());
    }
}
