<?php

declare(strict_types=1);

namespace NyonCode\LaravelPackageConsole\Support;

/**
 * Translations of the interactive questions. The English text is the key, so a line without
 * a translation is shown as written. Everything else Anvil prints stays in English.
 *
 * The language is ANVIL_LANG, or else the system's (LC_ALL, LC_MESSAGES, LANG), when
 * lang/{language}.php exists.
 */
final class Lang
{
    public const DEFAULT = 'en';

    private static ?string $locale = null;

    /** @var array<string, array<string, string>> */
    private static array $lines = [];

    public static function locale(): string
    {
        return self::$locale ??= self::detect();
    }

    /**
     * Use a language regardless of the environment; null detects it again.
     */
    public static function setLocale(?string $locale): void
    {
        self::$locale = $locale === null ? null : self::supported($locale);
    }

    /**
     * @return list<string>
     */
    public static function available(): array
    {
        $locales = array_map(fn (string $file) => basename($file, '.php'), glob(self::path('*')) ?: []);
        sort($locales);

        return [self::DEFAULT, ...$locales];
    }

    /**
     * @param  array<string, string|int>  $replace  Values for the :placeholders of the line.
     */
    public static function get(string $line, array $replace = []): string
    {
        $line = self::lines(self::locale())[$line] ?? $line;

        foreach ($replace as $key => $value) {
            $line = str_replace(':'.$key, (string) $value, $line);
        }

        return $line;
    }

    private static function detect(): string
    {
        foreach (['ANVIL_LANG', 'LC_ALL', 'LC_MESSAGES', 'LANG'] as $variable) {
            $value = getenv($variable);

            if (is_string($value) && $value !== '') {
                return self::supported($value);
            }
        }

        return self::DEFAULT;
    }

    /**
     * "cs_CZ.UTF-8" -> "cs" when lang/cs.php exists, otherwise English.
     */
    private static function supported(string $locale): string
    {
        $language = strtolower((string) preg_replace('/[^A-Za-z].*$/', '', $locale));

        return $language !== '' && is_file(self::path($language)) ? $language : self::DEFAULT;
    }

    /**
     * @return array<string, string>
     */
    private static function lines(string $locale): array
    {
        if ($locale === self::DEFAULT) {
            return [];
        }

        return self::$lines[$locale] ??= (array) require self::path($locale);
    }

    private static function path(string $locale): string
    {
        return dirname(__DIR__, 2).'/lang/'.$locale.'.php';
    }
}
