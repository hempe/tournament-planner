<?php

declare(strict_types=1);

namespace TP\Tests\Integration\Controllers;

use TP\Tests\Integration\IntegrationTestCase;
use TP\Core\Translator;

/**
 * Integration tests for LanguageController
 */
class LanguageControllerTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanDatabase();
    }

    public function testSwitchLanguageToEnglish(): void
    {
        $_SERVER['HTTP_REFERER'] = '/';

        $response = $this->request('POST', '/language/switch', [
            'locale' => 'en'
        ]);

        $this->assertEquals(303, $response->statusCode);
        $this->assertEquals('en', $_SESSION['locale']);
        $this->assertEquals('en', Translator::getInstance()->getLocale());
    }

    public function testSwitchLanguageToGerman(): void
    {
        $_SERVER['HTTP_REFERER'] = '/';

        $response = $this->request('POST', '/language/switch', [
            'locale' => 'de'
        ]);

        $this->assertEquals(303, $response->statusCode);
        $this->assertEquals('de', $_SESSION['locale']);
        $this->assertEquals('de', Translator::getInstance()->getLocale());
    }

    public function testSwitchLanguageToSpanish(): void
    {
        $_SERVER['HTTP_REFERER'] = '/';

        $response = $this->request('POST', '/language/switch', [
            'locale' => 'es'
        ]);

        $this->assertEquals(303, $response->statusCode);
        $this->assertEquals('es', $_SESSION['locale']);
        $this->assertEquals('es', Translator::getInstance()->getLocale());
    }

    public function testSwitchLanguageRejectsInvalidLocale(): void
    {
        $_SERVER['HTTP_REFERER'] = '/';
        $_SESSION['locale'] = 'de'; // Set initial locale

        $response = $this->request('POST', '/language/switch', [
            'locale' => 'fr' // Invalid locale
        ]);

        $this->assertEquals(303, $response->statusCode);
        $this->assertEquals('de', $_SESSION['locale']); // Should remain unchanged
    }

    public function testSwitchLanguageRequiresLocaleParameter(): void
    {
        $_SERVER['HTTP_REFERER'] = '/';

        $response = $this->request('POST', '/language/switch', []);

        $this->assertEquals(303, $response->statusCode);
    }

    public function testSwitchLanguageWithCustomRedirect(): void
    {
        $response = $this->request('POST', '/language/switch', [
            'locale' => 'en',
            'redirect' => '/events'
        ]);

        $this->assertEquals(303, $response->statusCode);
        $this->assertEquals('en', $_SESSION['locale']);
    }

    public function testSwitchLanguageRedirectsToReferer(): void
    {
        $_SERVER['HTTP_REFERER'] = '/events/123';

        $response = $this->request('POST', '/language/switch', [
            'locale' => 'en'
        ]);

        $this->assertEquals(303, $response->statusCode);
    }

    public function testGetCurrentLanguageReturnsJson(): void
    {
        $_SESSION['locale'] = 'en';
        Translator::getInstance()->setLocale('en');

        $response = $this->request('GET', '/language/current');

        $this->assertEquals(200, $response->statusCode);
        $this->assertStringContainsString('"locale":"en"', $response->body);
    }

    public function testGetCurrentLanguageReturnsCorrectLocale(): void
    {
        $_SESSION['locale'] = 'de';
        Translator::getInstance()->setLocale('de');

        $response = $this->request('GET', '/language/current');

        $this->assertEquals(200, $response->statusCode);
        $this->assertStringContainsString('"locale":"de"', $response->body);
    }
}
