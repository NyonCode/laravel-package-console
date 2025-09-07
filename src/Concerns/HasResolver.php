<?php

namespace NyonCode\LaravelPackageConsole\Concerns;

trait HasResolver
{
    /**
     * Checks if the given path is a nested file.
     *
     * A nested file is defined as a file that is not located in the root directory.
     * This method normalizes the path and checks if the directory is not equal to
     * the root directory.
     */
    protected function isNestedFile(string $path): bool
    {
        $normalizedPath = trim(str_replace('\\', '/', $path));

        if ($normalizedPath === '' || ! str_contains($normalizedPath, '/')) {
            return false;
        }

        $dir = dirname($normalizedPath);

        return $dir !== '.' && $dir !== '/' && $dir !== '\\';
    }

    protected function nestedFolder(string $path): string
    {
        $path = trim(str_replace('\\', '/', $path));

        return str_contains($path, '/') ? dirname($path) : '.';
    }

    protected function qualifyPath(string $qualifiedClass): string
    {
        $namespace = $this->packageRootNamespace();
        $relativePath = str_replace([$namespace, '\\'], ['', '/'], $qualifiedClass);

        return pcli_package_path($relativePath.'.php');
    }
}
