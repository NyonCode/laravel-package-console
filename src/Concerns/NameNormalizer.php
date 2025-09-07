<?php

namespace NyonCode\LaravelPackageConsole\Concerns;

trait NameNormalizer
{
    /**
     * Normalize a given string by converting it into a method name.
     *
     * Replaces hyphens and underscores with spaces, capitalizes the first letter of each word,
     * removes spaces, and lowercases the first letter to form a valid method name.
     *
     * @param  string  $name  The name to normalize.
     * @return string The normalized method name in camelCase format.
     */
    public static function normalizeMethodName(string $name): string
    {
        return lcfirst(self::normalizeClassName($name));
    }

    /**
     * Normalize a given class name by converting it into PascalCase.
     *
     * Replaces hyphens and underscores with spaces, capitalizes the first letter of each word,
     * and removes spaces to form a PascalCase class name.
     *
     * @param  string  $name  The name to normalize.
     * @return string The normalized class name in PascalCase format.
     */
    public static function normalizeClassName(string $name): string
    {
        return str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $name)));
    }

    /**
     * Normalize a given string by converting it into a constant name.
     *
     * Replaces spaces and hyphens with underscores, and converts the string to
     * uppercase to form a valid constant name.
     *
     * @param  string  $name  The name to normalize.
     * @return string The normalized constant name in uppercase format.
     */
    public static function normalizeConstantName(string $name): string
    {
        return strtoupper(self::normalizeSnakeCase($name));
    }

    /**
     * Normalize a given string by converting it into snake_case.
     *
     * Replaces spaces and hyphens with underscores, and converts the string to
     * lowercase to form a snake_case string.
     *
     * @param  string  $value  The name to normalize.
     * @return string The normalized snake_case string.
     */
    public static function normalizeSnakeCase(string $value): string
    {
        $snakeCase = (string) preg_replace('/[^a-zA-Z0-9]/', '_', trim($value));
        $snakeCase = (string) preg_replace('/[A-Z]/', '_$0', lcfirst($snakeCase));
        $snakeCase = (string) preg_replace('/_{2,}/', '_', $snakeCase);

        return strtolower(string: $snakeCase);
    }

    /**
     * Normalize a given string by converting it into kebab-case.
     *
     * Replaces spaces and underscores with hyphens, and converts the string to
     * lowercase to form a kebab-case string.
     *
     * @param  string  $value  The name to normalize.
     * @return string The normalized kebab-case string.
     */
    public static function normalizeKebabCase(string $value): string
    {
        $camelCase = (string) preg_replace('/[^a-zA-Z0-9]/', ' ', $value);
        $camelCase = (string) preg_replace('/[_\s]/', '', ucwords($camelCase));

        return lcfirst($camelCase);
    }
}
