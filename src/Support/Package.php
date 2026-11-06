<?php

declare(strict_types=1);

namespace NyonCode\LaravelPackageConsole\Support;

use RuntimeException;
use Symfony\Component\Finder\Finder;

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
     * Short kebab-case name used for config keys, view/translation namespaces and publish tags.
     *
     * It is the name the provider gives the package, so it matches what the provider registers:
     * - laravel-package-toolkit: ->hasShortName('blog'), else Str::kebab() of ->name('Blog');
     * - spatie/laravel-package-tools: ->name('laravel-blog') without the "laravel-" prefix;
     * - otherwise it is derived from the Composer name: "acme/laravel-blog" -> "blog".
     */
    public function shortName(): string
    {
        $editor = $this->mainProviderEditor();

        if ($editor !== null) {
            if ($editor->mode() === 'toolkit') {
                $short = $editor->packagerStringArgument('hasShortName');
                $name = $editor->packagerStringArgument('name');

                if ($short !== null && $short !== '') {
                    return $short;
                }

                if ($name !== null && $name !== '') {
                    return self::laravelKebab($name);
                }
            }

            if ($editor->mode() === 'spatie') {
                $name = $editor->usingConfigurationMethod('configurePackage')->packagerStringArgument('name');

                if ($name !== null && $name !== '') {
                    return str_contains($name, 'laravel-') ? substr($name, strpos($name, 'laravel-') + 8) : $name;
                }
            }
        }

        return self::shortNameFor($this->name());
    }

    /**
     * How the main provider registers resources: "toolkit", "spatie" or "plain"; null without one.
     */
    public function providerMode(): ?string
    {
        return $this->mainProviderEditor()?->mode();
    }

    /**
     * Directory of the package's translations: spatie/laravel-package-tools loads them from
     * resources/lang, everything else from lang.
     */
    public function translationsPath(): string
    {
        return $this->providerMode() === 'spatie' ? 'resources/lang' : 'lang';
    }

    /**
     * Service providers in the package's source that composer.json does not list yet:
     * classes whose parent class name ends in "ServiceProvider". The conventional
     * "{Package}ServiceProvider" comes first.
     *
     * @return list<string>
     */
    public function discoverProviders(): array
    {
        $found = [];

        foreach ($this->psr4() as $namespace => $directory) {
            $root = $this->path($directory);

            if (! is_dir($root) || str_contains($namespace, '\\Database\\')) {
                continue;
            }

            $files = (new Finder)->files()->in($root)->depth('< 3')->name('*.php');

            foreach ($files as $file) {
                $code = (string) file_get_contents($file->getPathname());

                if (! preg_match('/^namespace\s+([^;]+);/m', $code, $ns)
                    || ! preg_match('/^(?:final\s+|readonly\s+)*class\s+(\w+)\s+extends\s+\\\\?([\w\\\\]*ServiceProvider)\b/m', $code, $class)
                    || preg_match('/^abstract\s+class/m', $code)) {
                    continue;
                }

                $found[] = trim($ns[1]).'\\'.$class[1];
            }
        }

        $found = array_values(array_unique(array_diff($found, $this->providers())));
        $preferred = $this->rootNamespaceOrNull() !== null ? $this->rootNamespaceOrNull().'\\'.$this->defaultProviderName() : null;

        usort($found, fn (string $a, string $b) => [$a !== $preferred, substr_count($a, '\\'), $a] <=> [$b !== $preferred, substr_count($b, '\\'), $b]);

        return $found;
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

    private function mainProviderEditor(): ?PhpFileEditor
    {
        $path = $this->mainProviderPath();

        if ($path === null) {
            return null;
        }

        try {
            return PhpFileEditor::fromFile($path);
        } catch (\Throwable) {
            return null;
        }
    }

    private function rootNamespaceOrNull(): ?string
    {
        try {
            return $this->rootNamespace();
        } catch (RuntimeException) {
            return null;
        }
    }

    /**
     * Laravel's Str::kebab(), which the toolkit uses to derive the short name.
     */
    private static function laravelKebab(string $value): string
    {
        if (! ctype_lower($value)) {
            $value = (string) preg_replace('/\s+/u', '', ucwords($value));
            $value = mb_strtolower((string) preg_replace('/(.)(?=[A-Z])/u', '$1-', $value), 'UTF-8');
        }

        return $value;
    }

    /**
     * Default class name of the main provider, e.g. "BlogServiceProvider".
     */
    public function defaultProviderName(): string
    {
        return $this->studlyName().'ServiceProvider';
    }
}
