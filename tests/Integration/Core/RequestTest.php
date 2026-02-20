<?php

declare(strict_types=1);

namespace TP\Tests\Integration\Core;

use PHPUnit\Framework\TestCase;
use TP\Core\HttpMethod;
use TP\Core\Request;

class RequestTest extends TestCase
{
    private function makeRequest(
        string $method = 'GET',
        string $uri = '/',
        array $query = [],
        array $post = [],
        array $server = [],
        array $headers = [],
        array $files = []
    ): Request {
        return new Request(HttpMethod::from($method), $uri, $query, $post, $server, $headers, $files);
    }

    public function testGetPath(): void
    {
        $req = $this->makeRequest('GET', '/events?foo=bar');
        $this->assertEquals('/events', $req->getPath());
    }

    public function testGetQuery(): void
    {
        $req = $this->makeRequest('GET', '/', ['key' => 'value']);
        $this->assertEquals(['key' => 'value'], $req->getQuery());
    }

    public function testGetPost(): void
    {
        $req = $this->makeRequest('POST', '/', [], ['key' => 'value']);
        $this->assertEquals(['key' => 'value'], $req->getPost());
    }

    public function testGetIntReturnsInt(): void
    {
        $req = $this->makeRequest('GET', '/', ['n' => '42']);
        $this->assertEquals(42, $req->getInt('n'));
    }

    public function testGetIntDefaultsToZero(): void
    {
        $req = $this->makeRequest('GET', '/');
        $this->assertEquals(0, $req->getInt('missing'));
    }

    public function testGetIntWithNonNumericReturnsDefault(): void
    {
        $req = $this->makeRequest('GET', '/', ['n' => 'abc']);
        $this->assertEquals(0, $req->getInt('n'));
    }

    public function testGetStringWithNonStringReturnsDefault(): void
    {
        // Sanitized data is always string after sanitizeArray, but test the getString guard
        $req = $this->makeRequest('GET', '/', ['arr' => ['nested' => 'value']]);
        // Arrays get through sanitizeArray as arrays, getString falls back to default
        $this->assertEquals('', $req->getString('arr'));
    }

    public function testGetBoolWithOne(): void
    {
        $req = $this->makeRequest('GET', '/', ['flag' => '1']);
        $this->assertTrue($req->getBool('flag'));
    }

    public function testGetBoolWithTrue(): void
    {
        $req = $this->makeRequest('GET', '/', ['flag' => 'true']);
        $this->assertTrue($req->getBool('flag'));
    }

    public function testGetBoolWithZero(): void
    {
        $req = $this->makeRequest('GET', '/', ['flag' => '0']);
        $this->assertFalse($req->getBool('flag'));
    }

    public function testGetBoolDefaultFalse(): void
    {
        $req = $this->makeRequest('GET', '/');
        $this->assertFalse($req->getBool('missing'));
    }

    public function testGetArrayDefaultsToEmpty(): void
    {
        $req = $this->makeRequest('GET', '/');
        $this->assertEquals([], $req->getArray('missing'));
    }

    public function testGetArrayWithNonArrayReturnsDefault(): void
    {
        $req = $this->makeRequest('GET', '/', ['items' => 'not-array']);
        $this->assertEquals([], $req->getArray('items'));
    }

    public function testHasReturnsTrueForExistingKey(): void
    {
        $req = $this->makeRequest('GET', '/', ['key' => 'value']);
        $this->assertTrue($req->has('key'));
    }

    public function testHasReturnsFalseForMissingKey(): void
    {
        $req = $this->makeRequest('GET', '/');
        $this->assertFalse($req->has('missing'));
    }

    public function testHasFileReturnsTrueOnSuccess(): void
    {
        $files = ['photo' => ['error' => UPLOAD_ERR_OK]];
        $req = $this->makeRequest('POST', '/', [], [], [], [], $files);
        $this->assertTrue($req->hasFile('photo'));
    }

    public function testHasFileReturnsFalseForMissingFile(): void
    {
        $req = $this->makeRequest('POST', '/');
        $this->assertFalse($req->hasFile('missing'));
    }

    public function testHasFileReturnsFalseOnUploadError(): void
    {
        $files = ['photo' => ['error' => UPLOAD_ERR_NO_FILE]];
        $req = $this->makeRequest('POST', '/', [], [], [], [], $files);
        $this->assertFalse($req->hasFile('photo'));
    }

    public function testGetFileReturnsFileArray(): void
    {
        $files = ['photo' => ['error' => UPLOAD_ERR_OK, 'name' => 'test.jpg']];
        $req = $this->makeRequest('POST', '/', [], [], [], [], $files);
        $this->assertEquals(['error' => UPLOAD_ERR_OK, 'name' => 'test.jpg'], $req->getFile('photo'));
    }

    public function testGetFileReturnsNullForMissing(): void
    {
        $req = $this->makeRequest('POST', '/');
        $this->assertNull($req->getFile('missing'));
    }

    public function testGetHeader(): void
    {
        $req = $this->makeRequest('GET', '/', [], [], [], ['content-type' => 'application/json']);
        $this->assertEquals('application/json', $req->getHeader('Content-Type'));
    }

    public function testGetHeaderReturnsNullForMissing(): void
    {
        $req = $this->makeRequest('GET', '/');
        $this->assertNull($req->getHeader('missing'));
    }

    public function testGetHeaders(): void
    {
        $req = $this->makeRequest('GET', '/', [], [], [], ['x-custom' => 'value']);
        $this->assertEquals(['x-custom' => 'value'], $req->getHeaders());
    }

    public function testIsAjaxReturnsTrueWithXmlHttpRequest(): void
    {
        $req = $this->makeRequest('GET', '/', [], [], [], ['x-requested-with' => 'XMLHttpRequest']);
        $this->assertTrue($req->isAjax());
    }

    public function testIsAjaxReturnsFalseWithoutHeader(): void
    {
        $req = $this->makeRequest('GET', '/');
        $this->assertFalse($req->isAjax());
    }

    public function testIsSecureWithHttps(): void
    {
        $req = $this->makeRequest('GET', '/', [], [], ['HTTPS' => 'on']);
        $this->assertTrue($req->isSecure());
    }

    public function testGetUserAgent(): void
    {
        $req = $this->makeRequest('GET', '/', [], [], ['HTTP_USER_AGENT' => 'Mozilla/5.0']);
        $this->assertEquals('Mozilla/5.0', $req->getUserAgent());
    }

    public function testGetUserAgentDefaultsToEmpty(): void
    {
        $req = $this->makeRequest('GET', '/');
        $this->assertEquals('', $req->getUserAgent());
    }

    public function testGetIpFromRemoteAddr(): void
    {
        $req = $this->makeRequest('GET', '/', [], [], ['REMOTE_ADDR' => '127.0.0.1']);
        $this->assertEquals('127.0.0.1', $req->getIp());
    }

    public function testGetIpFromForwardedFor(): void
    {
        $req = $this->makeRequest('GET', '/', [], [], [
            'HTTP_X_FORWARDED_FOR' => '192.168.1.1',
            'REMOTE_ADDR' => '127.0.0.1',
        ]);
        $this->assertEquals('192.168.1.1', $req->getIp());
    }

    public function testGetIpFromXRealIp(): void
    {
        $req = $this->makeRequest('GET', '/', [], [], [
            'HTTP_X_REAL_IP' => '10.0.0.1',
            'REMOTE_ADDR' => '127.0.0.1',
        ]);
        $this->assertEquals('10.0.0.1', $req->getIp());
    }

    public function testInputSanitizationRemovesNullBytes(): void
    {
        $req = $this->makeRequest('GET', '/', ['key' => "val\0ue"]);
        $this->assertEquals('value', $req->get('key'));
    }

    public function testInputSanitizationNormalizesLineEndings(): void
    {
        $req = $this->makeRequest('GET', '/', ['key' => "line1\r\nline2"]);
        $this->assertEquals("line1\nline2", $req->get('key'));
    }

    public function testGetAllInputMergesQueryAndPost(): void
    {
        $req = $this->makeRequest('POST', '/', ['q' => 'query'], ['p' => 'post']);
        $all = $req->getAllInput();
        $this->assertArrayHasKey('q', $all);
        $this->assertArrayHasKey('p', $all);
    }
}
