<?php

declare(strict_types=1);

namespace TP\Tests\Integration;

/**
 * Validates translation files for completeness and consistency
 */
class TranslationValidationTest extends IntegrationTestCase
{
    private const LOCALES = ['de', 'en', 'es'];
    private const REFERENCE_LOCALE = 'de'; // Reference locale to compare against

    protected function setUp(): void
    {
        parent::setUp();
        // No database cleanup needed for translation validation
    }

    /**
     * Test that all locale files exist
     */
    public function testAllLocaleFilesExist(): void
    {
        echo "\n=== Testing Locale Files Existence ===\n";

        foreach (self::LOCALES as $locale) {
            $filePath = __DIR__ . "/../../resources/lang/{$locale}.php";
            $this->assertFileExists($filePath, "Locale file for {$locale} must exist");
            echo "   ✓ Locale file exists: {$locale}.php\n";
        }

        echo "\n=== Locale Files Existence Tests Passed! ===\n\n";
    }

    /**
     * Test that all locale files have identical translation keys
     */
    public function testAllLocalesHaveIdenticalKeys(): void
    {
        echo "\n=== Testing Translation Key Consistency ===\n";

        $localeData = [];
        foreach (self::LOCALES as $locale) {
            $filePath = __DIR__ . "/../../resources/lang/{$locale}.php";
            $localeData[$locale] = require $filePath;
        }

        $referenceKeys = $this->extractKeys($localeData[self::REFERENCE_LOCALE]);
        $referenceCount = count($referenceKeys);
        echo "\n1. Reference locale ({self::REFERENCE_LOCALE}) has {$referenceCount} keys\n";

        foreach (self::LOCALES as $locale) {
            if ($locale === self::REFERENCE_LOCALE) {
                continue;
            }

            $keys = $this->extractKeys($localeData[$locale]);
            $keyCount = count($keys);

            // Check for missing keys
            $missing = array_diff($referenceKeys, $keys);
            $this->assertEmpty(
                $missing,
                "Locale '{$locale}' is missing keys: " . implode(', ', $missing)
            );

            // Check for extra keys
            $extra = array_diff($keys, $referenceKeys);
            $this->assertEmpty(
                $extra,
                "Locale '{$locale}' has extra keys not in reference: " . implode(', ', $extra)
            );

            $this->assertEquals(
                $referenceCount,
                $keyCount,
                "Locale '{$locale}' must have same number of keys as reference"
            );

            echo "   ✓ Locale '{$locale}' has all {$keyCount} keys\n";
        }

        echo "\n=== Translation Key Consistency Tests Passed! ===\n\n";
    }

    /**
     * Test that no translation values are empty
     */
    public function testNoEmptyTranslations(): void
    {
        echo "\n=== Testing for Empty Translations ===\n";

        foreach (self::LOCALES as $locale) {
            echo "\n1. Checking {$locale} for empty values...\n";

            $filePath = __DIR__ . "/../../resources/lang/{$locale}.php";
            $translations = require $filePath;
            $keysWithValues = $this->extractKeysWithValues($translations);

            $emptyKeys = [];
            foreach ($keysWithValues as $key => $value) {
                if (empty(trim($value))) {
                    $emptyKeys[] = $key;
                }
            }

            $this->assertEmpty(
                $emptyKeys,
                "Locale '{$locale}' has empty translations: " . implode(', ', $emptyKeys)
            );

            echo "   ✓ No empty translations in {$locale}\n";
        }

        echo "\n=== Empty Translation Tests Passed! ===\n\n";
    }

    /**
     * Test that specific required translation keys exist
     */
    public function testRequiredKeysExist(): void
    {
        echo "\n=== Testing Required Translation Keys ===\n";

        $requiredKeys = [
            'app.name',
            'app.welcome',
            'nav.home',
            'nav.events',
            'nav.users',
            'nav.logout',
            'languages.de',
            'languages.en',
            'languages.es',
            'auth.login',
            'auth.username',
            'auth.password',
            'events.title',
            'events.register',
            'events.unregister',
            'users.title',
            'validation.required',
            'errors.not_found',
        ];

        foreach (self::LOCALES as $locale) {
            echo "\n1. Checking required keys in {$locale}...\n";

            $filePath = __DIR__ . "/../../resources/lang/{$locale}.php";
            $translations = require $filePath;
            $keysWithValues = $this->extractKeysWithValues($translations);

            foreach ($requiredKeys as $requiredKey) {
                $this->assertArrayHasKey(
                    $requiredKey,
                    $keysWithValues,
                    "Required key '{$requiredKey}' missing in locale '{$locale}'"
                );
                $this->assertNotEmpty(
                    trim($keysWithValues[$requiredKey]),
                    "Required key '{$requiredKey}' is empty in locale '{$locale}'"
                );
            }

            echo "   ✓ All " . count($requiredKeys) . " required keys present in {$locale}\n";
        }

        echo "\n=== Required Keys Tests Passed! ===\n\n";
    }

    /**
     * Test that language names are consistently defined across all locales
     */
    public function testLanguageNamesConsistent(): void
    {
        echo "\n=== Testing Language Name Consistency ===\n";

        $expectedLanguageNames = [
            'de' => 'Deutsch',
            'en' => 'English',
            'es' => 'Español',
        ];

        foreach (self::LOCALES as $locale) {
            echo "\n1. Checking language names in {$locale}...\n";

            $filePath = __DIR__ . "/../../resources/lang/{$locale}.php";
            $translations = require $filePath;

            foreach ($expectedLanguageNames as $langCode => $expectedName) {
                $this->assertArrayHasKey('languages', $translations);
                $this->assertArrayHasKey($langCode, $translations['languages']);
                $this->assertEquals(
                    $expectedName,
                    $translations['languages'][$langCode],
                    "Language name for '{$langCode}' should be '{$expectedName}' in all locales"
                );
            }

            echo "   ✓ All language names correct in {$locale}\n";
        }

        echo "\n=== Language Name Consistency Tests Passed! ===\n\n";
    }

    /**
     * Test that the union of all keys across all locales is present in every locale.
     * This catches keys that exist in en or es but are missing from de (reference check alone misses this).
     */
    public function testAllLocalesHaveUnionOfAllKeys(): void
    {
        echo "\n=== Testing Union of All Translation Keys ===\n";

        $localeData = [];
        foreach (self::LOCALES as $locale) {
            $filePath = __DIR__ . "/../../resources/lang/{$locale}.php";
            $localeData[$locale] = $this->extractKeys(require $filePath);
        }

        // Compute union of all keys
        $unionKeys = array_unique(array_merge(...array_values($localeData)));
        sort($unionKeys);

        foreach (self::LOCALES as $locale) {
            $missing = array_diff($unionKeys, $localeData[$locale]);
            $this->assertEmpty(
                $missing,
                "Locale '{$locale}' is missing keys from the union set: " . implode(', ', $missing)
            );
            echo "   ✓ Locale '{$locale}' has all " . count($unionKeys) . " union keys\n";
        }

        echo "\n=== Union Key Tests Passed! ===\n\n";
    }

    /**
     * Extract all translation keys from nested array
     */
    private function extractKeys(array $array, string $prefix = ''): array
    {
        $keys = [];
        foreach ($array as $key => $value) {
            $fullKey = $prefix ? "{$prefix}.{$key}" : $key;
            if (is_array($value)) {
                $keys = array_merge($keys, $this->extractKeys($value, $fullKey));
            } else {
                $keys[] = $fullKey;
            }
        }
        return $keys;
    }

    /**
     * Extract all translation keys with their values
     */
    private function extractKeysWithValues(array $array, string $prefix = ''): array
    {
        $result = [];
        foreach ($array as $key => $value) {
            $fullKey = $prefix ? "{$prefix}.{$key}" : $key;
            if (is_array($value)) {
                $result = array_merge($result, $this->extractKeysWithValues($value, $fullKey));
            } else {
                $result[$fullKey] = $value;
            }
        }
        return $result;
    }
}
