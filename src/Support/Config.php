<?php

namespace NyonCode\LaravelPackageConsole\Support;

use RuntimeException;

class Config
{
    private string $basePath;

    private string $appPath;

    private string $packagePath;

    private ?string $packageRootNamespace;

    private array $config;

    public function __construct(string $packagePath)
    {
        $this->basePath = dirname(__DIR__, 2);
        $this->appPath = $this->path('src');
        $this->packagePath = rtrim($packagePath, DIRECTORY_SEPARATOR);
        $this->packageRootNamespace = $this->getNamespace();

        $configPath = $this->basePath.'/config/app.php';
        $this->config = file_exists($configPath) ? require $configPath : [];
    }

    public function path(string $path = ''): string
    {
        return $this->basePath.
            DIRECTORY_SEPARATOR.
            ltrim($path, DIRECTORY_SEPARATOR);
    }

    private function getNamespace(): int|string
    {
        $composerFile = $this->packagePath('composer.json');

        if (! file_exists($composerFile)) {
            throw new RuntimeException(
                "composer.json not found in {$this->packagePath()}"
            );
        }

        $composer = json_decode(file_get_contents($composerFile), true);

        if (
            ! isset($composer['autoload']['psr-4']) ||
            ! is_array($composer['autoload']['psr-4'])
        ) {
            throw new RuntimeException(
                'PSR-4 autoload not defined in composer.json'
            );
        }

        foreach ($composer['autoload']['psr-4'] as $namespace => $paths) {
            if (is_array($paths)) {
                foreach ($paths as $pathChoice) {
                    if (
                        realpath($this->packagePath('src')) ===
                        realpath($this->packagePath($pathChoice))
                    ) {
                        return $this->packageRootNamespace = trim(
                            $namespace,
                            '\\'
                        );
                    }
                }
            }

            if (is_string($paths)) {
                if (
                    realpath($this->packagePath('src')) ===
                    realpath($this->packagePath($paths))
                ) {
                    return $this->packageRootNamespace = trim($namespace, '\\');
                }
            }
        }

        throw new RuntimeException('Unable to detect application namespace.');
    }

    public function packagePath(string $path = ''): string
    {
        return $this->packagePath.($path ? DIRECTORY_SEPARATOR.$path : '');
    }

    public function basePath(string $path = ''): string
    {
        return $this->basePath.($path ? DIRECTORY_SEPARATOR.$path : '');
    }

    public function appPath(string $path = ''): string
    {
        return $this->appPath.($path ? DIRECTORY_SEPARATOR.$path : '');
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }

    public function packageRootNamespace(): string
    {
        return $this->packageRootNamespace;
    }
}
