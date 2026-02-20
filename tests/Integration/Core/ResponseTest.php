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

    public function testUnauthorizedCreates401Response(): void
    {
        $response = Response::unauthorized('Access denied');
        $this->assertEquals(HttpStatus::UNAUTHORIZED, $response->getStatus());
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
        $response = Response::unauthorized();
        $this->assertFalse($response->isRedirect());
    }
}
