<?php

declare(strict_types=1);

namespace NyonCode\LaravelPackageConsole\Support;

/**
 * Paths and settings of the console itself (not of the package being developed).
 */
class Config
{
    private string $basePath;

    private string $packagePath;

    private array $config;

    public function __construct(string $packagePath)
    {
        $this->basePath = dirname(__DIR__, 2);
        $this->packagePath = rtrim($packagePath, DIRECTORY_SEPARATOR);

        $configPath = $this->basePath('config/app.php');
        $this->config = file_exists($configPath) ? require $configPath : [];
    }

    /**
     * Root directory of the package being developed.
     */
    public function packagePath(string $path = ''): string
    {
        return $this->packagePath.($path !== '' ? DIRECTORY_SEPARATOR.ltrim($path, DIRECTORY_SEPARATOR) : '');
    }

    /**
     * Work on another package (in a monorepo).
     */
    public function usePackagePath(string $packagePath): void
    {
        $this->packagePath = rtrim($packagePath, DIRECTORY_SEPARATOR);
    }

    /**
     * Root directory of this console library.
     */
    public function basePath(string $path = ''): string
    {
        return $this->basePath.($path !== '' ? DIRECTORY_SEPARATOR.ltrim($path, DIRECTORY_SEPARATOR) : '');
    }

    /**
     * The console library's src/ directory.
     */
    public function appPath(string $path = ''): string
    {
        return $this->basePath('src'.($path !== '' ? DIRECTORY_SEPARATOR.ltrim($path, DIRECTORY_SEPARATOR) : ''));
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }
}
