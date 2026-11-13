<?php

declare(strict_types=1);

namespace NyonCode\LaravelPackageConsole\Support;

/**
 * Lexical path helpers (no filesystem access, symlinks are not resolved).
 */
final class Path
{
    public static function isAbsolute(string $path): bool
    {
        return str_starts_with($path, '/') || str_starts_with($path, '\\') || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1;
    }

    /**
     * Forward slashes, no "." segments, ".." resolved where possible, no trailing slash;
     * "." for an empty relative path.
     */
    public static function normalize(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $prefix = '';

        if (preg_match('{^([A-Za-z]:)?/}', $path, $match)) {
            $prefix = $match[0];
            $path = substr($path, strlen($prefix));
        }

        $segments = [];

        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..' && $segments !== [] && end($segments) !== '..') {
                array_pop($segments);
            } elseif ($segment !== '..' || $prefix === '') {
                $segments[] = $segment;
            }
        }

        $normalized = $prefix.implode('/', $segments);

        return $normalized === '' ? '.' : $normalized;
    }

    /**
     * $path inside $base; $path itself when it is absolute.
     */
    public static function join(string $base, string $path): string
    {
        if (self::isAbsolute($path)) {
            return $path;
        }

        $base = rtrim($base, '/\\');

        return $path === '' || $path === '.' ? $base : $base.DIRECTORY_SEPARATOR.ltrim($path, '/\\');
    }
}
