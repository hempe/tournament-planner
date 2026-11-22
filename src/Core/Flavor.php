<?php

declare(strict_types=1);

namespace TP\Core;

use RuntimeException;

/**
 * The club flavour this installation runs as (build-time choice, see docs/DEPLOYMENT.md).
 * dist: config/flavor.json (+ resources/i18n/flavor/); repository: frontend/flavors/$APP_FLAVOR (default elfaro).
 */
final class Flavor
{
    public const SWITCHES = ['socialEvents', 'guests', 'prices', 'description', 'deadline', 'genderSplit'];
    public const USER_FIELDS = ['rfeg', 'memberNumber', 'phone', 'handicap', 'priority'];

    /** @var array<string, mixed>|null */
    private static ?array $config = null;
    private static string $flavorName = '';
    private static ?string $textDir = null;
    /** @var array<string, mixed> */
    private static array $overrides = [];

    public static function name(): string
    {
        self::load();
        return self::$flavorName;
    }

    public static function enabled(string $feature): bool
    {
        return self::features()[$feature] === true;
    }

    public static function waitlistByPriority(): bool
    {
        return self::features()['waitlistOrder'] === 'priority';
    }

    public static function hasUserField(string $field): bool
    {
        return in_array($field, self::features()['userFields'], true);
    }

    public static function timezone(): string
    {
        return self::load()['timezone'];
    }

    /** 'firstLast' or 'lastFirst': word order of legacy usernames. */
    public static function usernameOrder(): string
    {
        return self::load()['usernameOrder'];
    }

    /** Folder with the flavour's {locale}.json text overrides, null when there is none. */
    public static function textDir(): ?string
    {
        self::load();
        return self::$textDir;
    }

    /** Tests: change single features (merged over the loaded flavour) until reset(). */
    public static function override(array $features): void
    {
        self::$overrides = $features + self::$overrides;
    }

    public static function reset(): void
    {
        self::$config = null;
        self::$flavorName = '';
        self::$textDir = null;
        self::$overrides = [];
    }

    /** @return array<string, mixed> */
    private static function features(): array
    {
        return self::$overrides + self::load()['features'];
    }

    /** @return array<string, mixed> */
    private static function load(): array
    {
        if (self::$config !== null) {
            return self::$config;
        }
        $root = dirname(__DIR__, 2);
        $dist = "$root/config/flavor.json";
        if (is_file($dist)) {
            self::$config = self::parse($dist);
            self::$flavorName = (string) (self::$config['id'] ?? 'dist');
            self::$textDir = is_dir("$root/resources/i18n/flavor") ? "$root/resources/i18n/flavor" : null;
            return self::$config;
        }

        $name = (string) ($_ENV['APP_FLAVOR'] ?? (getenv('APP_FLAVOR') ?: 'elfaro'));
        $dir = "$root/frontend/flavors/$name";
        if (!preg_match('/^[a-z]+$/', $name) || !is_file("$dir/flavor.json")) {
            throw new RuntimeException("Unknown flavour \"$name\" (frontend/flavors/<name>/flavor.json)");
        }
        self::$config = self::parse("$dir/flavor.json");
        self::$flavorName = $name;
        self::$textDir = is_dir("$dir/i18n") ? "$dir/i18n" : null;
        return self::$config;
    }

    /**
     * Read and validate a flavor.json. Throws with the offending field name.
     *
     * @return array<string, mixed>
     */
    public static function parse(string $file): array
    {
        try {
            $data = json_decode((string) file_get_contents($file), true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new RuntimeException("Invalid flavor.json ($file): " . $e->getMessage(), 0, $e);
        }
        $fail = static fn(string $field) => throw new RuntimeException("Invalid flavor.json ($file): $field");

        if (!is_array($data)) {
            $fail('root must be an object');
        }
        foreach (['name', 'shortName'] as $field) {
            if (!is_string($data[$field] ?? null) || $data[$field] === '') {
                $fail($field);
            }
        }
        if (!in_array($data['timezone'] ?? null, timezone_identifiers_list(), true)) {
            $fail('timezone');
        }
        if (!in_array($data['usernameOrder'] ?? null, ['firstLast', 'lastFirst'], true)) {
            $fail('usernameOrder');
        }
        $features = $data['features'] ?? null;
        if (!is_array($features)) {
            $fail('features');
        }
        foreach (self::SWITCHES as $switch) {
            if (!is_bool($features[$switch] ?? null)) {
                $fail($switch);
            }
        }
        if (!in_array($features['waitlistOrder'] ?? null, ['timestamp', 'priority'], true)) {
            $fail('waitlistOrder');
        }
        $fields = $features['userFields'] ?? null;
        if (!is_array($fields) || array_diff($fields, self::USER_FIELDS) !== []) {
            $fail('userFields');
        }
        return $data;
    }
}
