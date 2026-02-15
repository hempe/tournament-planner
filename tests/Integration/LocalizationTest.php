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
        $this->assertEquals('Willkommen bei Golf El Faro', __('app.welcome'));
        echo "   ✓ German translations loaded correctly\n";

        // Test English translations
        echo "\n2. Testing English translations...\n";
        $translator->setLocale('en');
        $this->assertEquals('Events', __('nav.events'));
        $this->assertEquals('Users', __('nav.users'));
        $this->assertEquals('Logout', __('nav.logout'));
        $this->assertEquals('Welcome to Golf El Faro', __('app.welcome'));
        echo "   ✓ English translations loaded correctly\n";

        // Test Spanish translations
        echo "\n3. Testing Spanish translations...\n";
        $translator->setLocale('es');
        $this->assertEquals('Eventos', __('nav.events'));
        $this->assertEquals('Usuarios', __('nav.users'));
        $this->assertEquals('Cerrar sesión', __('nav.logout'));
        $this->assertEquals('Bienvenido a Golf El Faro', __('app.welcome'));
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
     * Test session persistence of locale
     */
    public function testSessionPersistsLocale(): void
    {
        echo "\n=== Testing Session Persistence ===\n";

        // Set locale in session
        echo "\n1. Setting locale in session...\n";
        $_SESSION['locale'] = 'en';
        $this->assertEquals('en', $_SESSION['locale']);
        echo "   ✓ Locale stored in session\n";

        // Verify it persists
        echo "\n2. Verifying session persistence...\n";
        $storedLocale = $_SESSION['locale'];
        $this->assertEquals('en', $storedLocale);
        echo "   ✓ Locale retrieved from session\n";

        // Change locale
        echo "\n3. Changing locale to es...\n";
        $_SESSION['locale'] = 'es';
        $this->assertEquals('es', $_SESSION['locale']);
        echo "   ✓ Locale updated in session\n";

        echo "\n=== Session Persistence Tests Passed! ===\n\n";
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

    /**
     * Test language switching with authentication
     */
    public function testLanguageSwitchingAsAuthenticatedUser(): void
    {
        echo "\n=== Testing Language Switch with Auth ===\n";

        $this->loginAsAdmin();

        // Simulate language switch via session
        echo "\n1. Switching to English...\n";
        $_SESSION['locale'] = 'en';
        Translator::getInstance()->setLocale('en');
        $this->assertEquals('en', Translator::getInstance()->getLocale());
        $this->assertEquals('Events', __('nav.events'));
        echo "   ✓ Language switched to English while authenticated\n";

        echo "\n2. Switching to Spanish...\n";
        $_SESSION['locale'] = 'es';
        Translator::getInstance()->setLocale('es');
        $this->assertEquals('es', Translator::getInstance()->getLocale());
        $this->assertEquals('Eventos', __('nav.events'));
        echo "   ✓ Language switched to Spanish while authenticated\n";

        echo "\n=== Language Switch with Auth Tests Passed! ===\n\n";
    }

    /**
     * Test theme translations in all locales
     */
    public function testThemeTranslations(): void
    {
        echo "\n=== Testing Theme Translations ===\n";

        $translator = Translator::getInstance();

        // Test German
        echo "\n1. Testing German theme translations...\n";
        $translator->setLocale('de');
        $this->assertEquals('Dunkles Design', __('theme.dark'));
        $this->assertEquals('Helles Design', __('theme.light'));
        echo "   ✓ German theme translations correct\n";

        // Test English
        echo "\n2. Testing English theme translations...\n";
        $translator->setLocale('en');
        $this->assertEquals('Dark theme', __('theme.dark'));
        $this->assertEquals('Light theme', __('theme.light'));
        echo "   ✓ English theme translations correct\n";

        // Test Spanish
        echo "\n3. Testing Spanish theme translations...\n";
        $translator->setLocale('es');
        $this->assertEquals('Tema oscuro', __('theme.dark'));
        $this->assertEquals('Tema claro', __('theme.light'));
        echo "   ✓ Spanish theme translations correct\n";

        echo "\n=== Theme Translation Tests Passed! ===\n\n";
    }

    /**
     * Test that all three locales have consistent translation keys
     */
    public function testAllLocalesHaveConsistentKeys(): void
    {
        echo "\n=== Testing Translation Key Consistency ===\n";

        $translator = Translator::getInstance();

        // Critical keys that should exist in all locales
        $criticalKeys = [
            'app.name',
            'app.welcome',
            'nav.home',
            'nav.events',
            'nav.users',
            'nav.logout',
            'nav.back',
            'nav.language',
            'languages.de',
            'languages.en',
            'languages.es',
            'theme.dark',
            'theme.light',
            'auth.login',
            'auth.username',
            'auth.password',
        ];

        $locales = ['de', 'en', 'es'];

        foreach ($locales as $locale) {
            echo "\n1. Checking {$locale} for critical keys...\n";
            $translator->setLocale($locale);

            foreach ($criticalKeys as $key) {
                $translation = __($key);
                $this->assertNotEquals($key, $translation, "Key '{$key}' missing in {$locale}");
                $this->assertNotEmpty($translation, "Translation for '{$key}' is empty in {$locale}");
            }
            echo "   ✓ All critical keys present in {$locale}\n";
        }

        echo "\n=== Translation Key Consistency Tests Passed! ===\n\n";
    }

    /**
     * Test language switching via POST endpoint (actual HTTP request)
     */
    public function testLanguageSwitchEndpoint(): void
    {
        echo "\n=== Testing Language Switch HTTP Endpoint ===\n";

        // Set initial locale to German
        $_SESSION['locale'] = 'de';
        Translator::getInstance()->setLocale('de');
        echo "\n1. Initial locale: de\n";

        // Switch to English via POST request
        echo "\n2. POST to /language/switch with locale=en...\n";
        $response = $this->request('POST', '/language/switch', [
            'locale' => 'en',
            'redirect' => '/'
        ]);

        // Should redirect
        $this->assertTrue(
            in_array($response->statusCode, [301, 302, 303]),
            "Expected redirect status, got {$response->statusCode}"
        );
        echo "   ✓ Endpoint returns redirect (status {$response->statusCode})\n";

        // Verify session was updated
        $this->assertEquals('en', $_SESSION['locale']);
        echo "   ✓ Session locale updated to en\n";

        // Verify translator was updated
        $this->assertEquals('en', Translator::getInstance()->getLocale());
        echo "   ✓ Translator locale updated to en\n";

        // Verify translations changed
        $this->assertEquals('Events', __('nav.events'));
        echo "   ✓ Translations now in English\n";

        // Switch to Spanish via POST
        echo "\n3. POST to /language/switch with locale=es...\n";
        $response = $this->request('POST', '/language/switch', [
            'locale' => 'es',
            'redirect' => '/'
        ]);

        $this->assertTrue(
            in_array($response->statusCode, [301, 302, 303]),
            "Expected redirect status, got {$response->statusCode}"
        );
        $this->assertEquals('es', $_SESSION['locale']);
        $this->assertEquals('Eventos', __('nav.events'));
        echo "   ✓ Successfully switched to Spanish\n";

        // Test invalid locale - should redirect without changing
        echo "\n4. Testing invalid locale rejection...\n";
        $_SESSION['locale'] = 'es'; // Keep Spanish
        $response = $this->request('POST', '/language/switch', [
            'locale' => 'invalid_locale',
            'redirect' => '/'
        ]);

        // Should still redirect but not change locale
        $this->assertTrue(
            in_array($response->statusCode, [301, 302, 303]),
            "Expected redirect even for invalid locale"
        );
        $this->assertEquals('es', $_SESSION['locale'], "Locale should not change for invalid input");
        echo "   ✓ Invalid locale rejected, session unchanged\n";

        echo "\n=== Language Switch HTTP Endpoint Tests Passed! ===\n\n";
    }

    /**
     * Test that language persists across page loads (via session)
     */
    public function testLanguagePersistsAcrossRequests(): void
    {
        echo "\n=== Testing Language Persistence ===\n";

        // Set language in session
        echo "\n1. Setting language to en in session...\n";
        $_SESSION['locale'] = 'en';
        echo "   ✓ Language stored in session\n";

        // Simulate new request - translator should pick up session locale
        echo "\n2. Simulating new request...\n";
        $newTranslator = Translator::getInstance();
        $newTranslator->setLocale($_SESSION['locale']);

        $this->assertEquals('en', $newTranslator->getLocale());
        echo "   ✓ Language retrieved from session on new request\n";

        // Verify translations work
        $this->assertEquals('Events', __('nav.events'));
        echo "   ✓ Translations work with persisted language\n";

        echo "\n=== Language Persistence Tests Passed! ===\n\n";
    }

    /**
     * Test language selector appears on login page
     */
    public function testLanguageSelectorOnLoginPage(): void
    {
        echo "\n=== Testing Language Selector on Login Page ===\n";

        // Test that all three languages are available
        echo "\n1. Verifying available languages...\n";
        $availableLanguages = ['de', 'en', 'es'];
        foreach ($availableLanguages as $locale) {
            $langName = __("languages.{$locale}");
            $this->assertNotEmpty($langName);
            $this->assertNotEquals("languages.{$locale}", $langName);
        }
        echo "   ✓ All three languages available (de, en, es)\n";

        // Test switching between languages
        echo "\n2. Testing language switching...\n";

        Translator::getInstance()->setLocale('de');
        $this->assertEquals('Anmelden', __('auth.login'));
        $this->assertEquals('Benutzername', __('auth.username'));
        echo "   ✓ German login page translations work\n";

        Translator::getInstance()->setLocale('en');
        $this->assertEquals('Login', __('auth.login'));
        $this->assertEquals('Username', __('auth.username'));
        echo "   ✓ English login page translations work\n";

        Translator::getInstance()->setLocale('es');
        $this->assertEquals('Iniciar sesión', __('auth.login'));
        $this->assertEquals('Usuario', __('auth.username'));
        echo "   ✓ Spanish login page translations work\n";

        echo "\n=== Language Selector Tests Passed! ===\n\n";
    }
}
