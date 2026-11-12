<?php

declare(strict_types=1);

namespace NyonCode\LaravelPackageConsole\Support;

use Closure;

/**
 * Registers generated resources in the package's main service provider, in the provider's own style:
 *
 * - nyoncode/laravel-package-toolkit: a `->hasX()` call on the Packager chain in configure();
 * - spatie/laravel-package-tools: a `->hasX()` call on the Package chain in configurePackage(),
 *   and statements in the packageBooted()/packageRegistered() hooks for what it has no call for;
 * - a plain Laravel ServiceProvider: the standard calls (mergeConfigFrom, loadRoutesFrom,
 *   publishes, commands, ...) in register()/boot().
 *
 * Every operation is idempotent. register()/boot() are never added to a provider that extends
 * another base class, because they would override the parent's.
 */
final class ProviderRegistrar
{
    private const LARAVEL_PROVIDER = 'Illuminate\Support\ServiceProvider';

    /**
     * The spatie/laravel-package-tools hooks that stand in for register() and boot().
     */
    private const SPATIE_HOOKS = ['register' => 'packageRegistered', 'boot' => 'packageBooted'];

    private string $mode = 'plain';

    private ?string $parentClass = null;

    private bool $previewing = false;

    /**
     * The provider's code with everything registered so far, while previewing.
     */
    private ?string $preview = null;

    public function __construct(private readonly Package $package) {}

    /**
     * Register in memory only. Registrations build on one another, as they would on disk, and
     * previewedCode() is the provider they add up to. A previewing registrar is also careful:
     * where the provider registers something of the same kind in a way that cannot be read
     * (`$this->loadRoutesFrom($routes)`), it answers UNKNOWN instead of adding a second copy.
     */
    public function preview(): static
    {
        $this->previewing = true;

        return $this;
    }

    /**
     * The provider's code after the previewed registrations; null when none changed it.
     */
    public function previewedCode(): ?string
    {
        return $this->preview;
    }

    public function config(string $file): Registration
    {
        $key = basename($file, '.php');
        $statements = [
            ['register', "\$this->mergeConfigFrom({$this->path("config/$file")}, '$key');", [['mergeConfigFrom'], "config/$file"]],
            ['boot', "\$this->publishes([\n    {$this->path("config/$file")} => config_path('$file'),\n], '{$this->tag('config')}');", [['publishes'], "config/$file"]],
        ];

        return $this->apply(
            toolkit: fn (PhpFileEditor $e) => $this->toolkitResource($e, 'hasConfig', "'$file'", 'hasConfig()'),
            laravel: fn (PhpFileEditor $e) => $this->laravelStatements($e, $statements),
            spatie: fn (PhpFileEditor $e) => $this->spatieConfig($e, $key),
        );
    }

    public function routes(string $file): Registration
    {
        $name = basename($file, '.php');

        return $this->apply(
            toolkit: fn (PhpFileEditor $e) => $this->toolkitResource($e, 'hasRoutes', "'$file'", "hasRoutes(['$file'])"),
            laravel: fn (PhpFileEditor $e) => $this->laravelStatements($e, [
                ['boot', "\$this->loadRoutesFrom({$this->path("routes/$file")});", [['loadRoutesFrom', 'require'], "routes/$file"]],
            ]),
            spatie: fn (PhpFileEditor $e) => $this->spatieCall($e, ['hasRoute', 'hasRoutes'], "'$name'", "hasRoute('$name')"),
        );
    }

    public function broadcastChannels(string $file): Registration
    {
        $statements = [['boot', "require {$this->path("routes/$file")};", [['require'], "routes/$file"]]];

        return $this->apply(
            toolkit: fn (PhpFileEditor $e) => $this->toolkitResource($e, 'hasBroadcastChannels', "'$file'", "hasBroadcastChannels(['$file'])"),
            laravel: fn (PhpFileEditor $e) => $this->laravelStatements($e, $statements),
            spatie: fn (PhpFileEditor $e) => $this->laravelStatements($e, $statements),
        );
    }

    /**
     * @param  string|null  $file  The new migration, added when the provider lists its files.
     */
    public function migrations(?string $file = null): Registration
    {
        $name = $file !== null ? (string) preg_replace('/\.php(\.stub)?$/', '', $file) : null;

        return $this->apply(
            toolkit: fn (PhpFileEditor $e) => $this->toolkitResource($e, 'hasMigrations', $file !== null ? "'$file'" : null, 'hasMigrations()'),
            laravel: fn (PhpFileEditor $e) => $this->laravelStatements($e, [
                ['boot', "\$this->publishesMigrations([\n    {$this->path('database/migrations')} => database_path('migrations'),\n], '{$this->tag('migrations')}');", [['publishesMigrations', 'publishes', 'loadMigrationsFrom'], 'database/migrations']],
            ]),
            spatie: function (PhpFileEditor $e) use ($name) {
                if ($name === null || in_array('discoversMigrations', array_column($e->packagerCalls(), 'name'), true)) {
                    return Registration::already();
                }

                return $this->spatieCall($e, ['hasMigration', 'hasMigrations'], "'$name'", "hasMigration('$name')");
            },
        );
    }

    /**
     * @param  string|null  $file  The new seeder, added when the provider lists its files.
     */
    public function seeders(?string $file = null): Registration
    {
        $statements = [['boot', "\$this->publishes([\n    {$this->path('database/seeders')} => database_path('seeders'),\n], '{$this->tag('seeders')}');", [['publishes'], 'database/seeders']]];

        return $this->apply(
            toolkit: fn (PhpFileEditor $e) => $this->toolkitResource($e, 'hasSeeders', $file !== null ? "'$file'" : null, 'hasSeeders()'),
            laravel: fn (PhpFileEditor $e) => $this->laravelStatements($e, $statements),
            spatie: fn (PhpFileEditor $e) => $this->laravelStatements($e, $statements),
        );
    }

    public function views(): Registration
    {
        $short = $this->package->shortName();

        return $this->apply(
            toolkit: fn (PhpFileEditor $e) => $this->toolkitResource($e, 'hasViews', null, 'hasViews()'),
            laravel: function (PhpFileEditor $e) use ($short) {
                $namespace = $this->loadedNamespace($e, 'loadViewsFrom') ?? $short;

                return $this->laravelStatements($e, [
                    ['boot', "\$this->loadViewsFrom({$this->path('resources/views')}, '$namespace');", [['loadViewsFrom'], 'resources/views']],
                    ['boot', "\$this->publishes([\n    {$this->path('resources/views')} => resource_path('views/vendor/$namespace'),\n], '{$this->tag('views')}');", [['publishes'], 'resources/views']],
                ]);
            },
            spatie: fn (PhpFileEditor $e) => $this->spatieCall($e, ['hasViews'], '', 'hasViews()'),
        );
    }

    public function translations(): Registration
    {
        $short = $this->package->shortName();
        $lang = $this->package->translationsPath();

        return $this->apply(
            toolkit: fn (PhpFileEditor $e) => $this->toolkitResource($e, 'hasTranslations', null, 'hasTranslations()'),
            laravel: function (PhpFileEditor $e) use ($short, $lang) {
                $namespace = $this->loadedNamespace($e, 'loadTranslationsFrom') ?? $short;

                return $this->laravelStatements($e, [
                    ['boot', "\$this->loadTranslationsFrom({$this->path($lang)}, '$namespace');", [['loadTranslationsFrom'], $lang]],
                    ['boot', "\$this->loadJsonTranslationsFrom({$this->path($lang)});", [['loadJsonTranslationsFrom'], $lang]],
                    ['boot', "\$this->publishes([\n    {$this->path($lang)} => lang_path('vendor/$namespace'),\n], '{$this->tag('translations')}');", [['publishes'], $lang]],
                ]);
            },
            spatie: fn (PhpFileEditor $e) => $this->spatieCall($e, ['hasTranslations'], '', 'hasTranslations()'),
        );
    }

    public function command(string $class): Registration
    {
        $short = self::basename($class);

        return $this->apply(
            toolkit: function (PhpFileEditor $e) use ($class, $short) {
                foreach ($e->packagerCalls() as $call) {
                    if (in_array($call['name'], ['hasCommand', 'hasCommands'], true) && str_contains($call['args'], "$short::class")) {
                        return Registration::already();
                    }
                }

                $e->addUse($class);

                return $this->toolkitResource($e, 'hasCommands', "$short::class", "hasCommands([$short::class])");
            },
            laravel: function (PhpFileEditor $e) use ($class, $short) {
                if ($e->contains("$short::class")) {
                    return Registration::already();
                }

                $block = "if (\$this->app->runningInConsole()) {\n    \$this->commands([\n        $short::class,\n    ]);\n}";

                if (! $e->hasMethod('boot') && ! $this->canAddLifecycleMethods()) {
                    return $this->manualLifecycle('boot', $block);
                }

                $this->ensureMethod($e, 'boot');
                $e->addUse($class);

                if (! $e->addItemToThisCallArray('boot', 'commands', "$short::class")) {
                    $e->appendToMethod('boot', $block);
                }

                return Registration::registered("$short::class in commands()");
            },
            spatie: function (PhpFileEditor $e) use ($class, $short) {
                $calls = ['hasCommand', 'hasCommands', 'hasConsoleCommand', 'hasConsoleCommands'];

                foreach ($e->packagerCalls() as $call) {
                    if (in_array($call['name'], $calls, true) && str_contains($call['args'], "$short::class")) {
                        return Registration::already();
                    }
                }

                $e->addUse($class);

                return $this->spatieCall($e, [], '', "hasCommand($short::class)");
            },
        );
    }

    public function componentNamespace(string $namespace): Registration
    {
        $prefix = $this->package->shortName();
        $blade = function (PhpFileEditor $e) use ($namespace, $prefix) {
            $result = $this->laravelStatements($e, [
                ['boot', "Blade::componentNamespace('$namespace', '$prefix');"],
            ]);

            if ($result->status === Registration::REGISTERED) {
                $e->addUse('Illuminate\Support\Facades\Blade');
            }

            return $result;
        };

        return $this->apply(
            toolkit: function (PhpFileEditor $e) use ($namespace, $prefix) {
                foreach ($e->packagerCalls() as $call) {
                    if (in_array($call['name'], ['hasComponentNamespace', 'hasComponentNamespaces'], true) && str_contains($call['args'], $namespace)) {
                        return Registration::already();
                    }
                }

                $e->appendPackagerCall("hasComponentNamespace('$prefix', '$namespace')");

                return Registration::registered("->hasComponentNamespace('$prefix', ...)");
            },
            laravel: $blade,
            spatie: $blade,
        );
    }

    public function middlewareAlias(string $alias, string $class): Registration
    {
        $short = self::basename($class);
        $plain = function (PhpFileEditor $e) use ($alias, $class, $short) {
            $result = $this->laravelStatements($e, [
                ['boot', "\$this->app['router']->aliasMiddleware('$alias', $short::class);"],
            ]);

            if ($result->status === Registration::REGISTERED) {
                $e->addUse($class);
            }

            return $result;
        };

        return $this->apply(
            toolkit: function (PhpFileEditor $e) use ($alias, $class, $short) {
                foreach ($e->packagerCalls() as $call) {
                    if ($call['name'] === 'hasMiddlewareAliases' && str_contains($call['args'], "'$alias'")) {
                        return Registration::already();
                    }
                }

                $e->addUse($class);

                return $this->toolkitResource($e, 'hasMiddlewareAliases', "'$alias' => $short::class", "hasMiddlewareAliases(['$alias' => $short::class])");
            },
            laravel: $plain,
            spatie: $plain,
        );
    }

    public function listener(string $event, string $listener): Registration
    {
        $eventShort = self::basename($event);
        $listenerShort = self::basename($listener);
        $plain = function (PhpFileEditor $e) use ($event, $listener, $eventShort, $listenerShort) {
            $result = $this->laravelStatements($e, [
                ['boot', "Event::listen($eventShort::class, $listenerShort::class);"],
            ]);

            if ($result->status === Registration::REGISTERED) {
                $e->addUse('Illuminate\Support\Facades\Event')->addUse($event)->addUse($listener);
            }

            return $result;
        };

        return $this->apply(
            toolkit: function (PhpFileEditor $e) use ($event, $listener, $eventShort, $listenerShort) {
                foreach ($e->packagerCalls() as $call) {
                    if (in_array($call['name'], ['hasEvent', 'hasEvents'], true)
                        && str_contains($call['args'], "$eventShort::class")
                        && str_contains($call['args'], "$listenerShort::class")) {
                        return Registration::already();
                    }
                }

                $e->addUse($event)->addUse($listener);
                $e->appendPackagerCall("hasEvent($eventShort::class, $listenerShort::class)");

                return Registration::registered("->hasEvent($eventShort::class, $listenerShort::class)");
            },
            laravel: $plain,
            spatie: $plain,
        );
    }

    /**
     * @param  Closure(PhpFileEditor): Registration  $toolkit
     * @param  Closure(PhpFileEditor): Registration  $laravel
     * @param  Closure(PhpFileEditor): Registration  $spatie
     */
    private function apply(Closure $toolkit, Closure $laravel, Closure $spatie): Registration
    {
        $path = $this->package->mainProviderPath();

        if ($path === null) {
            return Registration::noProvider();
        }

        $editor = $this->preview !== null ? new PhpFileEditor($this->preview) : PhpFileEditor::fromFile($path);
        $this->mode = $editor->mode();
        $this->parentClass = $editor->parentClass();

        $result = match ($this->mode) {
            'toolkit' => $toolkit($editor),
            'spatie' => $spatie($editor->usingConfigurationMethod('configurePackage')),
            default => $laravel($editor),
        };

        if ($result->status === Registration::REGISTERED && $this->previewing) {
            $this->preview = $editor->code();

            return $result;
        }

        if ($result->status === Registration::REGISTERED) {
            file_put_contents($path, $editor->code());

            return Registration::registered(
                'Registered '.$result->message.' in '.$this->package->relativePath($path).'.',
                $result->warning
            );
        }

        return $result;
    }

    /**
     * Make sure a toolkit Packager call covers the resource.
     *
     * - not called yet            -> append $newCall
     * - called without arguments  -> auto-discovery already covers it
     * - called with an array      -> add $item to the array
     * - called with something else -> ask the author to add it by hand
     */
    private function toolkitResource(PhpFileEditor $editor, string $method, ?string $item, string $newCall): Registration
    {
        $existing = array_values(array_filter($editor->packagerCalls(), fn (array $call) => $call['name'] === $method));

        if ($existing === []) {
            $editor->appendPackagerCall($newCall);

            return Registration::registered("->$newCall");
        }

        foreach ($existing as $call) {
            if ($call['args'] === '' || $item === null || str_contains($call['args'], $item)) {
                return Registration::already();
            }
        }

        if ($editor->addItemToPackagerCallArray($method, $item)) {
            return Registration::registered("$item in ->$method()");
        }

        return Registration::manual("Add $item to ->$method({$existing[0]['args']}) in the service provider.");
    }

    /**
     * spatie/laravel-package-tools calls are additive (hasRoute(), hasMigration(), hasCommand()),
     * so a resource is registered by appending a call unless one of $coveredBy already names it
     * ($needle === '': any such call covers it).
     *
     * @param  list<string>  $coveredBy
     */
    private function spatieCall(PhpFileEditor $editor, array $coveredBy, string $needle, string $newCall): Registration
    {
        foreach ($editor->packagerCalls() as $call) {
            if (in_array($call['name'], $coveredBy, true) && ($needle === '' || str_contains($call['args'], $needle))) {
                return Registration::already();
            }
        }

        $editor->appendPackagerCall($newCall);

        return Registration::registered("->$newCall");
    }

    /**
     * hasConfigFile() replaces its list on every call, so a second config file is added to
     * the existing call: hasConfigFile() -> hasConfigFile(['blog', 'blog-cache']).
     */
    private function spatieConfig(PhpFileEditor $editor, string $key): Registration
    {
        $short = $this->package->shortName();
        $existing = array_values(array_filter($editor->packagerCalls(), fn (array $call) => $call['name'] === 'hasConfigFile'));

        if ($existing === []) {
            $call = $key === $short ? 'hasConfigFile()' : "hasConfigFile('$key')";
            $editor->appendPackagerCall($call);

            return Registration::registered("->$call");
        }

        $args = $existing[0]['args'];

        if (($args === '' && $key === $short) || str_contains($args, "'$key'") || str_contains($args, "\"$key\"")) {
            return Registration::already();
        }

        if ($args === '' || preg_match('/^([\'"])[^\'"]*\1$/', $args)) {
            $files = $args === '' ? "'$short'" : $args;
            $editor->replacePackagerCallArguments('hasConfigFile', "[$files, '$key']");

            return Registration::registered("'$key' in ->hasConfigFile()");
        }

        if ($editor->addItemToPackagerCallArray('hasConfigFile', "'$key'")) {
            return Registration::registered("'$key' in ->hasConfigFile()");
        }

        return Registration::manual("Add '$key' to ->hasConfigFile($args) in the service provider.");
    }

    /**
     * Add statements to register()/boot() (spatie: packageRegistered()/packageBooted()) unless
     * the provider already has them.
     *
     * A statement that registers a path names the calls that cover it and the path, relative to
     * the package root: the provider is then read for what it registers, so a hand-written
     * `$this->loadMigrationsFrom(dirname(__DIR__).'/database/migrations')` counts. Any other
     * statement is looked up as text.
     *
     * @param  list<array{0: string, 1: string, 2?: array{0: list<string>, 1: string}}>  $statements  [method, code, [covering calls, path]] tuples
     */
    private function laravelStatements(PhpFileEditor $editor, array $statements): Registration
    {
        $added = [];
        $manual = null;

        if ($this->previewing) {
            foreach ($statements as $statement) {
                if (isset($statement[2]) && ! $this->covered($editor, ...$statement[2])
                    && ($unreadable = $this->unreadable($editor, ...$statement[2])) !== null) {
                    return Registration::unknown("the service provider calls $unreadable, which may or may not cover {$statement[2][1]}.");
                }
            }
        }

        foreach ($statements as $statement) {
            [$method, $code] = $statement;
            $method = $this->mode === 'spatie' ? self::SPATIE_HOOKS[$method] : $method;
            $lines = explode("\n", $code);
            // Multi-line blocks (publishes) are identified by their distinctive second line.
            $needle = trim($lines[count($lines) > 1 ? 1 : 0]);

            if (isset($statement[2]) ? $this->covered($editor, ...$statement[2]) : $editor->contains($needle)) {
                continue;
            }

            if (! $editor->hasMethod($method) && ! $this->canAddLifecycleMethods()) {
                $manual ??= $this->manualLifecycle($method, $code);

                continue;
            }

            $this->ensureMethod($editor, $method);
            $editor->appendToMethod($method, $code);
            $added[$method][] = self::callName($code);
        }

        if ($added === []) {
            return $manual ?? Registration::already();
        }

        $parts = [];

        foreach ($added as $method => $calls) {
            $parts[] = implode(', ', array_unique($calls))." in $method()";
        }

        return Registration::registered(implode(' and ', $parts), $manual?->message);
    }

    /**
     * Whether one of $calls in the provider already registers $path: the path itself, a
     * directory it is in (publishes(config/) covers config/blog.php) or a file inside it
     * (publishing single migration files covers database/migrations).
     *
     * A path the code does not spell out (`$this->basePath('routes/web.php')`) counts when one
     * of its string literals ends with $path.
     *
     * @param  list<string>  $calls
     */
    private function covered(PhpFileEditor $editor, array $calls, string $path): bool
    {
        foreach ($editor->registrations($this->providerDirectory()) as $registration) {
            if (! in_array($registration['call'], $calls, true)) {
                continue;
            }

            $registered = $registration['path'];

            if ($registered !== null) {
                if ($registered !== '' && (str_starts_with("$path/", "$registered/") || str_starts_with("$registered/", "$path/"))) {
                    return true;
                }

                continue;
            }

            foreach ($registration['literals'] as $literal) {
                if (str_ends_with('/'.trim(str_replace('\\', '/', $literal), '/'), "/$path")) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * The namespace the provider already loads its views or translations under, so what is
     * published for them goes where Laravel looks: loadViewsFrom(..., 'laravel-blog') ->
     * resource_path('views/vendor/laravel-blog').
     */
    private function loadedNamespace(PhpFileEditor $editor, string $call): ?string
    {
        foreach ($editor->registrations($this->providerDirectory()) as $registration) {
            if ($registration['call'] === $call && $registration['name'] !== null && preg_match('/^[\w.-]+$/', $registration['name'])) {
                return $registration['name'];
            }
        }

        return null;
    }

    /**
     * A call among $calls whose path cannot be read from the code: "loadRoutesFrom($routes)".
     *
     * @param  list<string>  $calls
     */
    private function unreadable(PhpFileEditor $editor, array $calls, string $path): ?string
    {
        foreach ($editor->registrations($this->providerDirectory()) as $registration) {
            if ($registration['path'] === null && $registration['call'] !== 'require' && in_array($registration['call'], $calls, true)) {
                return "{$registration['call']}({$registration['source']})";
            }
        }

        return null;
    }

    /**
     * register()/boot() may only be added to a provider that extends Laravel's ServiceProvider
     * (whose methods are empty). The spatie hooks are empty by design, so they can always be added.
     */
    private function canAddLifecycleMethods(): bool
    {
        return $this->mode === 'spatie'
            || $this->parentClass === null
            || ltrim($this->parentClass, '\\') === self::LARAVEL_PROVIDER;
    }

    private function manualLifecycle(string $method, string $code): Registration
    {
        $provider = $this->package->relativePath((string) $this->package->mainProviderPath());
        $indented = implode("\n", array_map(fn (string $line) => '      '.$line, explode("\n", $code)));

        return Registration::manual(
            "$provider extends {$this->parentClass} and has no $method(). Adding one would override the parent's, ".
            "so add this to $method() by hand, after parent::$method():\n$indented"
        );
    }

    private function ensureMethod(PhpFileEditor $editor, string $method): void
    {
        if (! $editor->hasMethod($method)) {
            $editor->addMethod("public function $method(): void\n{\n    //\n}");
        }
    }

    /**
     * `__DIR__.'/../config/blog.php'` relative to the main provider's directory.
     */
    private function path(string $relative): string
    {
        $relativeDir = $this->providerDirectory();
        $depth = $relativeDir === '' ? 0 : substr_count($relativeDir, '/') + 1;

        return "__DIR__.'/".str_repeat('../', $depth).$relative."'";
    }

    /**
     * The main provider's directory, relative to the package root: "src", "src/Providers".
     */
    private function providerDirectory(): string
    {
        $providerDir = dirname((string) $this->package->mainProviderPath());

        return $providerDir === $this->package->path()
            ? ''
            : trim(str_replace('\\', '/', $this->package->relativePath($providerDir)), '/');
    }

    private function tag(string $group): string
    {
        return $this->package->shortName().'-'.$group;
    }

    /**
     * "$this->loadRoutesFrom(...)" -> "loadRoutesFrom()", "Event::listen(...)" -> "Event::listen()",
     * "require __DIR__.'/../routes/channels.php'" -> "require routes/channels.php".
     */
    private static function callName(string $code): string
    {
        if (str_starts_with($code, 'require ')) {
            return preg_match("~'/(?:\\.\\./)*([^']+)'~", $code, $match) ? 'require '.$match[1] : 'require';
        }

        return preg_match('/((?:[A-Z]\w*::)?\w+)\(/', $code, $match) ? $match[1].'()' : 'code';
    }

    private static function basename(string $class): string
    {
        $class = trim($class, '\\');
        $position = strrpos($class, '\\');

        return $position === false ? $class : substr($class, $position + 1);
    }
}
