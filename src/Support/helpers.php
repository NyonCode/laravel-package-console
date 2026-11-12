<?php

use NyonCode\LaravelPackageConsole\Kernel;
use NyonCode\LaravelPackageConsole\Support\Lang;
use NyonCode\LaravelPackageConsole\Support\Package;

if (! function_exists('anvil_package')) {
    /**
     * The Laravel package being developed.
     */
    function anvil_package(): Package
    {
        return Kernel::package();
    }
}

if (! function_exists('anvil_package_path')) {
    function anvil_package_path(string $path = ''): string
    {
        return Kernel::config()->packagePath($path);
    }
}

if (! function_exists('anvil_base_path')) {
    function anvil_base_path(string $path = ''): string
    {
        return Kernel::config()->basePath($path);
    }
}

if (! function_exists('anvil_app_path')) {
    function anvil_app_path(string $path = ''): string
    {
        return Kernel::config()->appPath($path);
    }
}

if (! function_exists('anvil_config')) {
    function anvil_config(string $key, mixed $default = null): mixed
    {
        return Kernel::config()->get($key, $default);
    }
}

if (! function_exists('anvil_trans')) {
    /**
     * An interactive question in the user's language.
     *
     * @param  array<string, string|int>  $replace
     */
    function anvil_trans(string $line, array $replace = []): string
    {
        return Lang::get($line, $replace);
    }
}

if (! function_exists('anvil_stubs_path')) {
    function anvil_stubs_path(string $path = ''): string
    {
        return anvil_base_path('stubs'.DIRECTORY_SEPARATOR.$path);
    }
}

if (! function_exists('anvil_packageRootNamespace')) {
    function anvil_packageRootNamespace(): string
    {
        return Kernel::package()->rootNamespace();
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
