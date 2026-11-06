<?php

declare(strict_types=1);

namespace NyonCode\LaravelPackageConsole\Support;

use RuntimeException;

use function Symfony\Component\String\u;

/**
 * The Laravel package being developed (the directory the console runs in).
 */
final class Package
{
    public const TOOLKIT = 'nyoncode/laravel-package-toolkit';

    private ?ComposerJson $composer = null;

    public function __construct(private readonly string $path) {}

    public function path(string $path = ''): string
    {
        return rtrim($this->path, DIRECTORY_SEPARATOR).($path !== '' ? DIRECTORY_SEPARATOR.ltrim($path, DIRECTORY_SEPARATOR) : '');
    }

    /**
     * Path relative to the package root, for display.
     */
    public function relativePath(string $path): string
    {
        $root = $this->path().DIRECTORY_SEPARATOR;

        return str_starts_with($path, $root) ? substr($path, strlen($root)) : $path;
    }

    public function composer(): ComposerJson
    {
        return $this->composer ??= ComposerJson::in($this->path());
    }

    /**
     * Forget the cached composer.json (after it was rewritten).
     */
    public function refresh(): void
    {
        $this->composer = null;
    }

    public function exists(): bool
    {
        return $this->composer()->exists();
    }

    /**
     * Composer package name, e.g. "acme/laravel-blog".
     */
    public function name(): string
    {
        $name = $this->composer()->get('name');

        return is_string($name) && $name !== '' ? $name : 'vendor/'.basename($this->path());
    }

    /**
     * Short kebab-case name used for config keys, view/translation namespaces and publish tags,
     * e.g. "acme/laravel-blog" -> "blog".
     */
    public function shortName(): string
    {
        return self::shortNameFor($this->name());
    }

    public static function shortNameFor(string $composerName): string
    {
        $name = str_contains($composerName, '/') ? substr($composerName, strpos($composerName, '/') + 1) : $composerName;
        $name = (string) preg_replace('/^laravel-|-laravel$/', '', $name);

        return u($name)->kebab()->toString();
    }

    /**
     * Human-readable name, e.g. "blog-posts" -> "Blog Posts".
     */
    public function displayName(): string
    {
        return ucwords(str_replace('-', ' ', $this->shortName()));
    }

    public function studlyName(): string
    {
        return str_replace(' ', '', $this->displayName());
    }

    public function usesToolkit(): bool
    {
        return $this->composer()->hasPackage(self::TOOLKIT);
    }

    /**
     * @return array<string, string> PSR-4 namespace prefix (without trailing "\") => directory (without trailing "/").
     */
    public function psr4(bool $dev = false): array
    {
        $mappings = [];

        foreach ((array) $this->composer()->get(($dev ? 'autoload-dev' : 'autoload').'.psr-4', []) as $namespace => $paths) {
            foreach ((array) $paths as $directory) {
                $mappings[trim((string) $namespace, '\\')] = rtrim((string) $directory, '/');
            }
        }

        return $mappings;
    }

    /**
     * The namespace mapped to "src/".
     */
    public function rootNamespace(): string
    {
        foreach ($this->psr4() as $namespace => $directory) {
            if (in_array(trim($directory, './'), ['src', ''], true) && $namespace !== '') {
                return $namespace;
            }
        }

        if (! $this->exists()) {
            throw new RuntimeException('composer.json not found in ['.$this->path().']. Run "package:init" first.');
        }

        throw new RuntimeException('No PSR-4 namespace mapped to "src/" in composer.json. Run "package:init" to add it.');
    }

    /**
     * Resolve the file path of a class from the PSR-4 mappings (longest prefix wins).
     */
    public function pathForClass(string $class): ?string
    {
        $class = trim($class, '\\');
        $best = null;

        foreach ($this->psr4() + $this->psr4(true) as $namespace => $directory) {
            if (($class === $namespace || str_starts_with($class, $namespace.'\\'))
                && ($best === null || strlen($namespace) > strlen($best[0]))) {
                $best = [$namespace, $directory];
            }
        }

        if ($best === null) {
            return null;
        }

        $relative = ltrim(substr($class, strlen($best[0])), '\\');

        return $this->path(trim($best[1].'/'.str_replace('\\', '/', $relative), '/').'.php');
    }

    /**
     * @return list<string>
     */
    public function providers(): array
    {
        return array_values(array_filter((array) $this->composer()->get('extra.laravel.providers', []), 'is_string'));
    }

    /**
     * The service provider generators register resources in: the first provider
     * listed in extra.laravel.providers whose file exists.
     */
    public function mainProvider(): ?string
    {
        foreach ($this->providers() as $provider) {
            $path = $this->pathForClass($provider);

            if ($path !== null && is_file($path)) {
                return $provider;
            }
        }

        return null;
    }

    public function mainProviderPath(): ?string
    {
        $provider = $this->mainProvider();

        return $provider !== null ? $this->pathForClass($provider) : null;
    }

    /**
     * Default class name of the main provider, e.g. "BlogServiceProvider".
     */
    public function defaultProviderName(): string
    {
        return $this->studlyName().'ServiceProvider';
    }
}
