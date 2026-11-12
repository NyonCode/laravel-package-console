<?php

declare(strict_types=1);

namespace NyonCode\LaravelPackageConsole\Support;

use Closure;
use PhpParser\Error;
use RuntimeException;
use Symfony\Component\Finder\Finder;

/**
 * Compares what a package has on disk with what its service provider registers:
 *
 * - resources nothing registers (config, routes, migrations, seeders, views, translations,
 *   commands, view components), found by previewing the registration a generator would make;
 * - registrations of files and classes that do not exist;
 * - composer.json: the listed providers, the PSR-4 mappings, the provider's library.
 *
 * fix() writes the previewed registrations, so what the doctor adds is what a generator adds.
 */
final class Doctor
{
    private const LARAVEL_COMMAND = 'Illuminate\Console\Command';

    private const SPATIE = 'spatie/laravel-package-tools';

    /**
     * Toolkit calls that name files of a directory in their first argument.
     */
    private const TOOLKIT_FILES = [
        'hasConfig' => 'config',
        'hasRoutes' => 'routes',
        'hasBroadcastChannels' => 'routes',
        'hasMigrations' => 'database/migrations',
        'hasSeeders' => 'database/seeders',
    ];

    /**
     * Toolkit calls that, without arguments, discover a directory.
     */
    private const TOOLKIT_DIRECTORIES = self::TOOLKIT_FILES + [
        'hasViews' => 'resources/views',
        'hasTranslations' => 'lang',
    ];

    /**
     * What marks view components as registered some other way than a component namespace.
     */
    private const COMPONENT_REGISTRATIONS = ['componentNamespace', 'loadViewComponentsAs', 'Blade::component', 'hasComponent'];

    /** @var list<Finding> */
    private array $findings = [];

    private ProviderRegistrar $registrar;

    /**
     * A provider the package has that composer.json should list.
     */
    private ?string $unlistedProvider = null;

    /**
     * The subjects being fixed; null while examining.
     *
     * @var list<string>|null
     */
    private ?array $only = null;

    private TranslationFiles $translations;

    public function __construct(private readonly Package $package) {}

    /**
     * @return list<Finding>
     */
    public function examine(): array
    {
        $this->findings = [];
        $this->unlistedProvider = null;
        $this->registrar = (new ProviderRegistrar($this->package))->preview();

        $this->translations = new TranslationFiles($this->package);

        $this->checkProviders();
        $this->checkPsr4();
        $this->checkTranslations();

        $path = $this->package->mainProviderPath();

        if ($path !== null) {
            try {
                $provider = PhpFileEditor::fromFile($path);
                $provider->mode();
            } catch (Error|RuntimeException $e) {
                $this->add(Finding::ERROR, 'provider', 'cannot be read: '.$e->getMessage(), $this->package->relativePath($path));

                return $this->findings;
            }

            $this->checkProviderLibrary($provider);
            $this->checkResources($provider);
            $this->checkRegisteredFiles($provider);
            $this->checkRegisteredClasses($provider);
            $this->checkNamespaces($provider);
        }

        return $this->findings;
    }

    /**
     * Apply what the last examine() found fixable.
     *
     * @param  list<string>|null  $subjects  Fix only the findings about these; null fixes all.
     * @return list<Finding> The findings that were fixed.
     */
    public function fix(?array $subjects = null): array
    {
        $fixed = array_values(array_filter(
            $this->findings,
            fn (Finding $finding) => $finding->fixable() && $this->wantedIn($subjects, (string) $finding->subject)
        ));

        if ($subjects !== null) {
            // Preview again, registering only what was chosen.
            $this->only = $subjects;
            $this->examine();
            $this->only = null;
        }

        if ($this->unlistedProvider !== null && $this->wantedIn($subjects, $this->unlistedProvider)) {
            $composer = $this->package->composer();
            $composer->set('extra.laravel.providers', [
                $this->unlistedProvider,
                ...array_values(array_diff($this->package->providers(), [$this->unlistedProvider])),
            ]);
            $composer->save();
            $this->package->refresh();
        }

        $code = $this->registrar->previewedCode();
        $path = $this->package->mainProviderPath();

        if ($code !== null && $path !== null) {
            file_put_contents($path, $code);
        }

        foreach ($fixed as $finding) {
            if ($finding->check === 'missing-translation') {
                $this->translations->complete((string) $finding->subject);
            }
        }

        return $fixed;
    }

    private function wanted(string $subject): bool
    {
        return $this->wantedIn($this->only, $subject);
    }

    /**
     * @param  list<string>|null  $subjects
     */
    private function wantedIn(?array $subjects, string $subject): bool
    {
        return $subjects === null || in_array($subject, $subjects, true);
    }

    /**
     * Languages that lack keys another language has, and keys the code uses that no language
     * defines.
     */
    private function checkTranslations(): void
    {
        foreach ($this->translations->incomplete() as $file) {
            $count = count($file['keys']);
            $keys = implode(', ', array_slice($file['keys'], 0, 5)).($count > 5 ? ' and '.($count - 5).' more' : '');
            $from = implode(', ', $file['from']);

            $this->add(
                Finding::WARNING,
                'missing-translation',
                $file['exists']
                    ? 'has no translation for '.($count === 1 ? 'a key' : "$count keys")." that $from has: $keys."
                    : "does not exist, but $from has that file ($count ".($count === 1 ? 'key' : 'keys').').',
                $file['file'],
                'added '.($count === 1 ? 'the missing key' : "$count missing keys")." with the text from $from, to be translated",
            );
        }

        try {
            $short = $this->package->shortName();
        } catch (RuntimeException) {
            return;
        }

        $directories = array_values(array_unique(array_filter(
            [...array_map(fn (string $directory) => trim($directory, './'), array_values($this->package->psr4())), 'resources/views'],
            fn (string $directory) => ! in_array($directory, ['', 'vendor'], true)
        )));

        foreach ($this->translations->undefined($short, $directories) as $key => $file) {
            $this->add(Finding::WARNING, 'undefined-translation', "is used in $file, but no translation file defines it.", $key);
        }
    }

    private function checkProviders(): void
    {
        foreach ($this->package->providers() as $provider) {
            $path = $this->package->pathForClass($provider);

            if ($path === null) {
                $this->add(Finding::ERROR, 'provider', 'is listed in composer.json "extra.laravel.providers", but no PSR-4 namespace of the package covers it.', $provider);
            } elseif (! is_file($path)) {
                $this->add(Finding::ERROR, 'provider', 'is listed in composer.json "extra.laravel.providers", but '.$this->package->relativePath($path).' does not exist.', $provider);
            }
        }

        if ($this->package->mainProvider() !== null) {
            return;
        }

        $found = $this->package->discoverProviders()[0] ?? null;

        if ($found === null) {
            $this->add(Finding::ERROR, 'provider', 'The package has no service provider. Run "make:provider --main" to create one.');

            return;
        }

        $this->unlistedProvider = $found;
        $this->add(Finding::ERROR, 'provider', 'is not listed in composer.json "extra.laravel.providers", so Laravel does not load it.', $found, 'listed in composer.json "extra.laravel.providers"');
    }

    /**
     * Every class in a PSR-4 directory must be where its name says.
     */
    private function checkPsr4(): void
    {
        foreach ($this->package->psr4() as $namespace => $directory) {
            if (in_array(trim($directory, './'), ['', 'vendor'], true)) {
                continue;
            }

            foreach ($this->files($directory, '*.php', recursive: true) as $relative) {
                $code = (string) file_get_contents($this->package->path($relative));

                if (! preg_match('/^(?:(?:abstract|final|readonly)\s+)*(?:class|interface|trait|enum)\s+(\w+)/m', $code, $class)) {
                    continue;
                }

                $declared = (preg_match('/^namespace\s+([^;{\s]+)/m', $code, $ns) ? $ns[1].'\\' : '').$class[1];
                $expected = trim($namespace.'\\'.str_replace('/', '\\', substr($relative, strlen($directory) + 1, -4)), '\\');

                if ($declared !== $expected) {
                    $this->add(Finding::ERROR, 'psr-4', "declares $declared, but composer.json maps the file to $expected, so the class cannot be autoloaded.", $relative);
                }
            }
        }
    }

    /**
     * A provider written for a package-tools library needs that library.
     */
    private function checkProviderLibrary(PhpFileEditor $provider): void
    {
        $library = match ($provider->mode()) {
            'toolkit' => [Package::TOOLKIT, 'configure()'],
            'spatie' => [self::SPATIE, 'configurePackage()'],
            default => null,
        };

        if ($library !== null && ! $this->package->composer()->hasPackage($library[0])) {
            $this->add(Finding::WARNING, 'dependency', "has {$library[1]}, which Anvil reads as a {$library[0]} provider, but composer.json does not require {$library[0]}.", $this->providerPath());
        }
    }

    /**
     * Files on disk that the provider does not register.
     */
    private function checkResources(PhpFileEditor $provider): void
    {
        $mode = $provider->mode();

        foreach ($this->files('config', '*.php') as $file) {
            $this->expectRegistered(fn () => $this->registrar->config(basename($file)), $file);
        }

        $routes = $this->files('routes', '*.php');

        foreach ($routes as $file) {
            if ($this->includedByAnother($file, $routes)) {
                continue;
            }

            $this->expectRegistered(fn () => basename($file) === 'channels.php'
                ? $this->registrar->broadcastChannels(basename($file))
                : $this->registrar->routes(basename($file)), $file);
        }

        foreach ($this->files('database/migrations', ['*.php', '*.php.stub']) as $file) {
            $this->expectRegistered(fn () => $this->registrar->migrations(basename($file)), $file);
        }

        foreach ($this->files('database/seeders', ['*.php', '*.php.stub']) as $file) {
            $this->expectRegistered(fn () => $this->registrar->seeders(basename($file)), $file);
        }

        if ($this->files('resources/views', '*', recursive: true) !== []) {
            $this->expectRegistered(fn () => $this->registrar->views(), 'resources/views');
        }

        $lang = $this->package->translationsPath();

        if ($this->files($lang, ['*.php', '*.json'], recursive: true) !== []) {
            $this->expectRegistered(fn () => $this->registrar->translations(), $lang);
        } elseif ($mode === 'spatie' && $this->files('lang', ['*.php', '*.json'], recursive: true) !== []) {
            $this->add(Finding::WARNING, 'translations', 'holds the translations, but spatie/laravel-package-tools loads them from resources/lang.', 'lang');
        }

        foreach ($this->commands() as $class => $file) {
            $this->expectRegistered(fn () => $this->registrar->command($class), $file);
        }

        $this->checkComponents($provider);
    }

    private function checkComponents(PhpFileEditor $provider): void
    {
        try {
            $namespace = $this->package->rootNamespace().'\\View\\Components';
        } catch (RuntimeException) {
            return;
        }

        $directory = dirname((string) $this->package->pathForClass($namespace.'\\Component'));

        if (! is_dir($directory) || (new Finder)->files()->in($directory)->name('*.php')->count() === 0) {
            return;
        }

        foreach (self::COMPONENT_REGISTRATIONS as $registration) {
            if ($provider->contains($registration)) {
                return;
            }
        }

        $this->expectRegistered(
            fn () => $this->registrar->componentNamespace($namespace),
            $this->package->relativePath($directory),
            Finding::WARNING,
            'has view components, but the service provider registers no component namespace',
        );
    }

    /**
     * Preview a registration and turn its outcome into a finding. When only some findings are
     * being fixed, the others are not previewed, so they stay out of the provider.
     */
    private function expectRegistered(Closure $register, string $subject, string $severity = Finding::ERROR, string $problem = 'is not registered in the service provider'): void
    {
        if (! $this->wanted($subject)) {
            return;
        }

        /** @var Registration $registration */
        $registration = $register();

        if ($registration->status === Registration::REGISTERED) {
            $this->add($severity, 'unregistered', "$problem. Missing: {$registration->message}.", $subject, "registered {$registration->message}");
        }

        $manual = $registration->status === Registration::MANUAL ? $registration->message : $registration->warning;

        if ($manual !== null) {
            $this->add(Finding::WARNING, 'manual', "$problem, and Anvil cannot add it. $manual", $subject);
        }

        if ($registration->status === Registration::UNKNOWN) {
            $this->add(Finding::INFO, 'unknown', "may not be registered: {$registration->message}", $subject);
        }
    }

    /**
     * Registrations that point at a file or directory the package does not have.
     */
    private function checkRegisteredFiles(PhpFileEditor $provider): void
    {
        $seen = [];

        foreach ($provider->registrations($this->providerDirectory()) as $registration) {
            $path = $registration['path'];

            if ($path === null || $path === '' || isset($seen[$registration['call'].$path]) || file_exists($this->package->path($path))) {
                continue;
            }

            $seen[$registration['call'].$path] = true;
            $call = $registration['call'] === 'require' ? 'require' : $registration['call'].'()';
            $this->add(Finding::ERROR, 'missing-file', "does not exist, but the service provider passes it to $call.", $path);
        }

        match ($provider->mode()) {
            'toolkit' => $this->checkToolkitFiles($provider),
            'spatie' => $this->checkSpatieFiles($provider->usingConfigurationMethod('configurePackage')),
            default => null,
        };
    }

    private function checkToolkitFiles(PhpFileEditor $provider): void
    {
        foreach ($provider->packagerCalls() as $call) {
            $directory = self::TOOLKIT_DIRECTORIES[$call['name']] ?? null;

            if ($directory === null) {
                continue;
            }

            if ($call['values'] === []) {
                if (! is_dir($this->package->path($directory))) {
                    $this->add(Finding::WARNING, 'missing-file', "does not exist, but the service provider calls ->{$call['name']}().", $directory);
                }

                continue;
            }

            // A second argument moves the directory; then the files are the author's business.
            if (! isset(self::TOOLKIT_FILES[$call['name']]) || count($call['values']) !== 1) {
                continue;
            }

            foreach ($call['values'][0] ?? [] as $file) {
                $this->expectFile("$directory/$file", ['', '.php', '.stub', '.php.stub'], "->{$call['name']}()");
            }
        }
    }

    private function checkSpatieFiles(PhpFileEditor $provider): void
    {
        foreach ($provider->packagerCalls() as $call) {
            $names = array_merge(...array_map(fn (?array $values) => $values ?? [], $call['values']), ...[[]]);
            $method = "->{$call['name']}()";

            match ($call['name']) {
                'hasConfigFile' => array_map(
                    fn (string $name) => $this->expectFile("config/$name", ['.php', '.php.stub'], $method),
                    $call['values'] === [] ? [$this->package->shortName()] : $names
                ),
                'hasRoute', 'hasRoutes' => array_map(fn (string $name) => $this->expectFile("routes/$name", ['.php'], $method), $names),
                'hasMigration', 'hasMigrations' => array_map(
                    fn (string $name) => $this->expectFile("database/migrations/$name", ['.php', '.php.stub'], $method),
                    $names
                ),
                'hasViews' => is_dir($this->package->path('resources/views'))
                    || $this->add(Finding::WARNING, 'missing-file', "does not exist, but the service provider calls $method.", 'resources/views'),
                'hasTranslations' => is_dir($this->package->path('resources/lang'))
                    || $this->add(Finding::WARNING, 'missing-file', "does not exist, but the service provider calls $method.", 'resources/lang'),
                default => null,
            };
        }
    }

    /**
     * @param  list<string>  $extensions  The file may exist under any of these suffixes.
     */
    private function expectFile(string $path, array $extensions, string $call): void
    {
        foreach ($extensions as $extension) {
            if (is_file($this->package->path($path.$extension))) {
                return;
            }
        }

        $shown = $extensions[0] === '' ? $path : $path.$extensions[0];
        $this->add(Finding::ERROR, 'missing-file', "does not exist, but the service provider names it in $call.", $shown);
    }

    /**
     * `Foo::class` in the provider, for a class of the package that has no file.
     */
    private function checkRegisteredClasses(PhpFileEditor $provider): void
    {
        foreach ($provider->classReferences() as $class) {
            $path = $this->package->pathForClass($class);

            if ($path !== null && ! is_file($path)) {
                $this->add(Finding::ERROR, 'missing-class', 'is used in the service provider, but '.$this->package->relativePath($path).' does not exist.', $class);
            }
        }
    }

    /**
     * Generated code refers to views and translations by the short name, so the namespace
     * the provider loads them under has to be the same.
     */
    private function checkNamespaces(PhpFileEditor $provider): void
    {
        $short = $this->package->shortName();
        $kinds = ['loadViewsFrom' => 'views', 'loadTranslationsFrom' => 'translations'];

        foreach ($provider->registrations($this->providerDirectory()) as $registration) {
            $kind = $kinds[$registration['call']] ?? null;

            if ($kind !== null && $registration['name'] !== null && $registration['name'] !== $short) {
                $this->add(
                    Finding::WARNING,
                    'short-name',
                    "loads the $kind as \"{$registration['name']}::\", but Anvil's short name for the package is \"$short\": generated code refers to \"$short::\".",
                    $this->providerPath()
                );
            }
        }
    }

    /**
     * Concrete Artisan commands in the package's source: class => relative file.
     *
     * @return array<string, string>
     */
    private function commands(): array
    {
        $parents = [];
        $files = [];
        $abstract = [];

        foreach ($this->package->psr4() as $directory) {
            if (in_array(trim($directory, './'), ['', 'vendor'], true)) {
                continue;
            }

            foreach ($this->files($directory, '*.php', recursive: true) as $relative) {
                $code = (string) file_get_contents($this->package->path($relative));

                if (! preg_match('/\bclass\s+\w+\s+extends\b/', $code)) {
                    continue;
                }

                try {
                    $editor = new PhpFileEditor($code);
                    $class = $editor->className();
                    $parent = $editor->parentClass();
                } catch (Error|RuntimeException) {
                    continue;
                }

                if ($class !== null && $parent !== null) {
                    $parents[$class] = ltrim($parent, '\\');
                    $files[$class] = $relative;
                    $abstract[$class] = $editor->isAbstract();
                }
            }
        }

        $commands = [];

        foreach ($parents as $class => $parent) {
            for ($depth = 0; $depth < 10 && $parent !== self::LARAVEL_COMMAND && isset($parents[$parent]); $depth++) {
                $parent = $parents[$parent];
            }

            if ($parent === self::LARAVEL_COMMAND && ! $abstract[$class]) {
                $commands[$class] = $files[$class];
            }
        }

        return $commands;
    }

    /**
     * A route file that another route file requires is loaded through that one.
     *
     * @param  list<string>  $routes
     */
    private function includedByAnother(string $file, array $routes): bool
    {
        foreach ($routes as $other) {
            if ($other !== $file && preg_match(
                '/\b(?:require|include)(?:_once)?\b[^;]*'.preg_quote(basename($file), '/').'/',
                (string) file_get_contents($this->package->path($other))
            )) {
                return true;
            }
        }

        return false;
    }

    /**
     * Files of a package directory, relative to the package root, sorted.
     *
     * @param  string|list<string>  $names
     * @return list<string>
     */
    private function files(string $directory, string|array $names, bool $recursive = false): array
    {
        $root = $this->package->path($directory);

        if (! is_dir($root)) {
            return [];
        }

        $finder = (new Finder)->files()->in($root)->name($names)->ignoreDotFiles(true)->sortByName();

        if (! $recursive) {
            $finder->depth(0);
        }

        $files = [];

        foreach ($finder as $file) {
            $files[] = trim($directory, '/').'/'.str_replace('\\', '/', $file->getRelativePathname());
        }

        return $files;
    }

    private function providerPath(): string
    {
        return $this->package->relativePath((string) $this->package->mainProviderPath());
    }

    /**
     * The main provider's directory, relative to the package root.
     */
    private function providerDirectory(): string
    {
        $directory = dirname($this->providerPath());

        return $directory === '.' ? '' : str_replace('\\', '/', $directory);
    }

    private function add(string $severity, string $check, string $message, ?string $subject = null, ?string $fix = null): false
    {
        $this->findings[] = new Finding($severity, $check, $message, $subject, $fix);

        return false;
    }
}
