<?php

use NyonCode\LaravelPackageConsole\Kernel;

if (! function_exists('pcli_package_path')) {
    function pcli_package_path(string $path = ''): string
    {
        return Kernel::config()->packagePath($path);
    }
}

if (! function_exists('pcli_base_path')) {
    function pcli_base_path(string $path = ''): string
    {
        return Kernel::config()->basePath($path);
    }
}

if (! function_exists('pcli_app_path')) {
    function pcli_app_path(string $path = ''): string
    {
        return Kernel::config()->appPath($path);
    }
}

if (! function_exists('pcli_config')) {
    function pcli_config(string $key, mixed $default = null): mixed
    {
        return Kernel::config()->get($key, $default);
    }
}

if (! function_exists('pcli_stubs_path')) {
    function pcli_stubs_path(string $path = ''): string
    {
        return pcli_base_path('stubs'.DIRECTORY_SEPARATOR.$path);
    }
}

if (! function_exists('pcli_packageRootNamespace')) {
    function pcli_packageRootNamespace(): string
    {
        return Kernel::config()->packageRootNamespace();
    }
}

if (! function_exists('windows_os')) {
    /**
     * Determine whether the current environment is Windows based.
     */
    function windows_os(): bool
    {
        return PHP_OS_FAMILY === 'Windows';
    }
}
