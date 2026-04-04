<?php

declare(strict_types=1);

namespace TP\Tests\Integration;

use TP\Core\Translator;
use TP\Models\DB;

/**
 * Integration tests for localization and language switching
 */
class LocalizationTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanDatabase();
    }

    /**
     * Test that setLocale changes the active locale
     */
    public function testSetLocaleChangesActiveLocale(): void
    {
        echo "\n=== Testing Locale Switching ===\n";

        $translator = Translator::getInstance();

        // Test German
        echo "\n1. Setting locale to de...\n";
        $translator->setLocale('de');
        $this->assertEquals('de', $translator->getLocale());
        echo "   ✓ Locale set to de\n";

        // Test English
        echo "\n2. Setting locale to en...\n";
        $translator->setLocale('en');
        $this->assertEquals('en', $translator->getLocale());
        echo "   ✓ Locale set to en\n";

        // Test Spanish
        echo "\n3. Setting locale to es...\n";
        $translator->setLocale('es');
        $this->assertEquals('es', $translator->getLocale());
        echo "   ✓ Locale set to es\n";

        echo "\n=== Locale Switching Tests Passed! ===\n\n";
    }

    /**
     * Test that translations load correctly for each locale
     */
    public function testTranslationsLoadForEachLocale(): void
    {
        echo "\n=== Testing Translation Loading ===\n";

        $translator = Translator::getInstance();

        // Test German translations
        echo "\n1. Testing German translations...\n";
        $translator->setLocale('de');
        $this->assertEquals('Anlässe', __('nav.events'));
        $this->assertEquals('Benutzer', __('nav.users'));
        $this->assertEquals('Abmelden', __('nav.logout'));
        $this->assertEquals('Willkommen bei GOLF EL FARO', __('app.welcome'));
        echo "   ✓ German translations loaded correctly\n";

        // Test English translations
        echo "\n2. Testing English translations...\n";
        $translator->setLocale('en');
        $this->assertEquals('Events', __('nav.events'));
        $this->assertEquals('Users', __('nav.users'));
        $this->assertEquals('Logout', __('nav.logout'));
        $this->assertEquals('Welcome to GOLF EL FARO', __('app.welcome'));
        echo "   ✓ English translations loaded correctly\n";

        // Test Spanish translations
        echo "\n3. Testing Spanish translations...\n";
        $translator->setLocale('es');
        $this->assertEquals('Eventos', __('nav.events'));
        $this->assertEquals('Usuarios', __('nav.users'));
        $this->assertEquals('Cerrar sesión', __('nav.logout'));
        $this->assertEquals('Bienvenido a GOLF EL FARO', __('app.welcome'));
        echo "   ✓ Spanish translations loaded correctly\n";

        echo "\n=== Translation Loading Tests Passed! ===\n\n";
    }

    /**
     * Test that language names are translated correctly
     */
    public function testLanguageNamesAreTranslated(): void
    {
        echo "\n=== Testing Language Names ===\n";

        $translator = Translator::getInstance();

        // All locales should have the same language names
        $expectedLanguages = [
            'de' => 'Deutsch',
            'en' => 'English',
            'es' => 'Español',
        ];

        foreach (['de', 'en', 'es'] as $locale) {
            echo "\n1. Testing language names in {$locale}...\n";
            $translator->setLocale($locale);

            foreach ($expectedLanguages as $langCode => $langName) {
                $translated = __("languages.{$langCode}");
                $this->assertEquals($langName, $translated, "Language name for {$langCode} should be {$langName} in {$locale}");
            }
            echo "   ✓ All language names correct in {$locale}\n";
        }

        echo "\n=== Language Name Tests Passed! ===\n\n";
    }

    /**
     * Reset the Translator singleton so negotiateLocale() reruns on next getInstance() call.
     * Uses reflection — valid in PHP 8.1+ without setAccessible().
     */
    private function resetTranslator(): void
    {
        $ref = new \ReflectionProperty(Translator::class, 'instance');
        $ref->setValue(null, null);
    }

    /**
     * Restore the Translator to a known default state after tests that reset it.
     */
    private function restoreTranslator(): void
    {
        $this->resetTranslator();
        unset($_SERVER['HTTP_ACCEPT_LANGUAGE']);
        Translator::getInstance()->setLocale('de');
    }

    public function testAcceptLanguagePicksEnglish(): void
    {
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'en-US,en;q=0.9';
        $this->resetTranslator();

        $this->assertEquals('en', Translator::getInstance()->getLocale());

        $this->restoreTranslator();
    }

    public function testAcceptLanguagePicksSpanish(): void
    {
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'es-ES,es;q=0.9,en;q=0.5';
        $this->resetTranslator();

        $this->assertEquals('es', Translator::getInstance()->getLocale());

        $this->restoreTranslator();
    }

    public function testAcceptLanguageFallsBackToDefaultForUnsupportedLanguage(): void
    {
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'fr-FR,fr;q=0.9';
        $this->resetTranslator();

        $this->assertEquals('de', Translator::getInstance()->getLocale());

        $this->restoreTranslator();
    }

    public function testAcceptLanguagePicksHighestQualityMatch(): void
    {
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'fr-FR,fr;q=0.9,es;q=0.8,en;q=0.7';
        $this->resetTranslator();

        // fr is unsupported, next best is es
        $this->assertEquals('es', Translator::getInstance()->getLocale());

        $this->restoreTranslator();
    }

    public function testEmptyAcceptLanguageFallsBackToDefault(): void
    {
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = '';
        $this->resetTranslator();

        $this->assertEquals('de', Translator::getInstance()->getLocale());

        $this->restoreTranslator();
    }

    /**
     * Test fallback to default locale
     */
    public function testFallbackToDefaultLocale(): void
    {
        echo "\n=== Testing Fallback Behavior ===\n";

        $translator = Translator::getInstance();

        // Test that missing keys fall back to English
        echo "\n1. Testing missing translation key...\n";
        $translator->setLocale('de');
        $missingKey = 'some.nonexistent.key';
        $result = __($missingKey);
        $this->assertEquals($missingKey, $result, "Missing key should return the key itself");
        echo "   ✓ Missing key returns key as fallback\n";

        echo "\n=== Fallback Tests Passed! ===\n\n";
    }

}
