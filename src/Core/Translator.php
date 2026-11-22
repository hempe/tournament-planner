<?php

declare(strict_types=1);

namespace TP\Core;

final class Translator
{
    private static ?Translator $instance = null;
    private array $translations = [];
    private string $currentLocale;
    private string $fallbackLocale;
    private array $loadedLocales = [];

    private function __construct(string $locale = 'de', string $fallbackLocale = 'en')
    {
        $this->currentLocale = $locale;
        $this->fallbackLocale = $fallbackLocale;
    }

    public static function getInstance(): Translator
    {
        if (self::$instance === null) {
            $config = Config::getInstance();
            $configured = $config->get('app.locale', 'de');
            self::$instance = new Translator(
                self::negotiateLocale($configured),
                $config->get('app.fallback_locale', 'en')
            );
        }
        return self::$instance;
    }

    /**
     * Directory holding the {locale}.json files.
     * In dist/ the build copies them to resources/i18n; in the repository they
     * live in the Angular workspace (single source of truth).
     */
    public static function langDir(): string
    {
        $dist = __DIR__ . '/../../resources/i18n';
        return is_dir($dist) ? $dist : __DIR__ . '/../../frontend/src/i18n';
    }

    private static function negotiateLocale(string $fallback): string
    {
        $available = array_map(
            fn($f) => basename($f, '.json'),
            glob(self::langDir() . '/*.json') ?: []
        );

        // Normalize the configured fallback to an available locale (e.g. de_CH → de)
        $normalizedFallback = in_array($fallback, $available, true)
            ? $fallback
            : strtolower(substr($fallback, 0, 2));

        $header = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
        if ($header === '') {
            return $normalizedFallback;
        }

        // Parse "de-CH,de;q=0.9,en-US;q=0.8,en;q=0.7" into [(lang => q), ...]
        $preferences = [];
        foreach (explode(',', $header) as $item) {
            $parts = explode(';q=', trim($item));
            $lang = trim($parts[0]);
            $q = isset($parts[1]) ? (float) $parts[1] : 1.0;
            $preferences[$lang] = $q;
        }
        arsort($preferences);

        foreach (array_keys($preferences) as $lang) {
            $primary = strtolower(substr($lang, 0, 2));
            if (in_array($primary, $available, true)) {
                return $primary;
            }
        }

        return $normalizedFallback;
    }

    public function setLocale(string $locale): void
    {
        $this->currentLocale = $locale;
        $this->loadTranslations($locale);
    }

    public function getLocale(): string
    {
        return $this->currentLocale;
    }

    public function translate(string $key, array $parameters = []): string
    {
        $translation = $this->getTranslation($key);

        if (empty($parameters)) {
            return $translation;
        }

        return $this->interpolate($translation, $parameters);
    }

    public function choice(string $key, int $count, array $parameters = []): string
    {
        $translation = $this->getTranslation($key);
        $pluralForm = $this->getPluralForm($count, $this->currentLocale);

        // Parse plural forms: "singular|plural" or "zero|one|many"
        $forms = explode('|', $translation);

        if (count($forms) === 1) {
            return $this->interpolate($forms[0], array_merge(['count' => $count], $parameters));
        }

        if (count($forms) === 2) {
            $form = $count === 1 ? $forms[0] : $forms[1];
        } else {
            $form = $forms[$pluralForm] ?? $forms[0];
        }

        return $this->interpolate($form, array_merge(['count' => $count], $parameters));
    }

    private function getTranslation(string $key): string
    {
        $this->loadTranslations($this->currentLocale);

        $translation = $this->getNestedValue($this->translations[$this->currentLocale] ?? [], $key);

        if ($translation === null && $this->currentLocale !== $this->fallbackLocale) {
            $this->loadTranslations($this->fallbackLocale);
            $translation = $this->getNestedValue($this->translations[$this->fallbackLocale] ?? [], $key);
        }

        return $translation ?? $key;
    }

    private function loadTranslations(string $locale): void
    {
        if (in_array($locale, $this->loadedLocales, true)) {
            return;
        }

        $translations = self::readJson(self::langDir() . "/{$locale}.json");
        $flavorDir = Flavor::textDir();
        if ($flavorDir !== null) {
            $translations = self::merge($translations, self::readJson("$flavorDir/{$locale}.json"));
        }
        $this->translations[$locale] = $translations;
        $this->loadedLocales[] = $locale;
    }

    /** @return array<string, mixed> */
    private static function readJson(string $file): array
    {
        return file_exists($file)
            ? (array) json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR)
            : [];
    }

    /** Deep merge: string leaves of $override replace those of $base (same rule as the frontend). */
    public static function merge(array $base, array $override): array
    {
        foreach ($override as $key => $value) {
            $base[$key] = is_array($value) && is_array($base[$key] ?? null) ? self::merge($base[$key], $value) : $value;
        }
        return $base;
    }

    private function getNestedValue(array $array, string $key): ?string
    {
        if (str_contains($key, '.')) {
            $keys = explode('.', $key);
            $current = $array;

            foreach ($keys as $k) {
                if (!is_array($current) || !array_key_exists($k, $current)) {
                    return null;
                }
                $current = $current[$k];
            }

            return is_string($current) ? $current : null;
        }

        return is_string($array[$key] ?? null) ? $array[$key] : null;
    }

    private function interpolate(string $message, array $parameters): string
    {
        $replacements = [];
        foreach ($parameters as $key => $value) {
            $replacements[':' . $key] = (string) $value;
        }

        return strtr($message, $replacements);
    }

    private function getPluralForm(int $count, string $locale): int
    {
        // Simplified plural rules - in production, use a more comprehensive system
        return match (substr($locale, 0, 2)) {
            'de', 'en' => $count === 1 ? 0 : 1,
            'fr' => $count <= 1 ? 0 : 1,
            default => $count === 1 ? 0 : 1,
        };
    }
}