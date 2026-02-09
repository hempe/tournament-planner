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

    private function __construct(string $locale = 'de_CH', string $fallbackLocale = 'en_US')
    {
        $this->currentLocale = $locale;
        $this->fallbackLocale = $fallbackLocale;
    }

    public static function getInstance(): Translator
    {
        if (self::$instance === null) {
            $config = Config::getInstance();
            self::$instance = new Translator(
                $config->get('app.locale', 'de_CH'),
                $config->get('app.fallback_locale', 'en_US')
            );
        }
        return self::$instance;
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
        
        $translationFile = __DIR__ . "/../../resources/lang/{$locale}.php";
        
        if (file_exists($translationFile)) {
            $this->translations[$locale] = require $translationFile;
        } else {
            $this->translations[$locale] = [];
        }
        
        $this->loadedLocales[] = $locale;
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
            $replacements[':' . $key] = (string)$value;
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

// Global helper functions for convenience
function __(string $key, array $parameters = []): string
{
    return Translator::getInstance()->translate($key, $parameters);
}

function trans(string $key, array $parameters = []): string
{
    return Translator::getInstance()->translate($key, $parameters);
}

function trans_choice(string $key, int $count, array $parameters = []): string
{
    return Translator::getInstance()->choice($key, $count, $parameters);
}