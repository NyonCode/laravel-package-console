<?php

declare(strict_types=1);

namespace NyonCode\LaravelPackageConsole\Support;

use Closure;

/**
 * Registers generated resources in the package's main service provider.
 *
 * With nyoncode/laravel-package-toolkit the matching `->hasX()` call is added to the
 * Packager chain in configure(); otherwise the standard Laravel ServiceProvider calls
 * (mergeConfigFrom, loadRoutesFrom, publishes, commands, ...) go into register()/boot().
 * Every operation is idempotent.
 */
final class ProviderRegistrar
{
    public function __construct(private readonly Package $package) {}

    public function config(string $file): Registration
    {
        $key = basename($file, '.php');

        return $this->apply(
            toolkit: fn (PhpFileEditor $e) => $this->toolkitResource($e, 'hasConfig', "'$file'", 'hasConfig()'),
            laravel: fn (PhpFileEditor $e) => $this->laravelStatements($e, [
                ['register', "\$this->mergeConfigFrom({$this->path("config/$file")}, '$key');"],
                ['boot', "\$this->publishes([\n    {$this->path("config/$file")} => config_path('$file'),\n], '{$this->tag('config')}');"],
            ]),
        );
    }

    public function routes(string $file): Registration
    {
        return $this->apply(
            toolkit: fn (PhpFileEditor $e) => $this->toolkitResource($e, 'hasRoutes', "'$file'", "hasRoutes(['$file'])"),
            laravel: fn (PhpFileEditor $e) => $this->laravelStatements($e, [
                ['boot', "\$this->loadRoutesFrom({$this->path("routes/$file")});"],
            ]),
        );
    }

    public function broadcastChannels(string $file): Registration
    {
        return $this->apply(
            toolkit: fn (PhpFileEditor $e) => $this->toolkitResource($e, 'hasBroadcastChannels', "'$file'", "hasBroadcastChannels(['$file'])"),
            laravel: fn (PhpFileEditor $e) => $this->laravelStatements($e, [
                ['boot', "require {$this->path("routes/$file")};"],
            ]),
        );
    }

    /**
     * @param  string|null  $file  The new migration, added when hasMigrations() lists its files.
     */
    public function migrations(?string $file = null): Registration
    {
        return $this->apply(
            toolkit: fn (PhpFileEditor $e) => $this->toolkitResource($e, 'hasMigrations', $file !== null ? "'$file'" : null, 'hasMigrations()'),
            laravel: fn (PhpFileEditor $e) => $this->laravelStatements($e, [
                ['boot', "\$this->publishesMigrations([\n    {$this->path('database/migrations')} => database_path('migrations'),\n], '{$this->tag('migrations')}');"],
            ]),
        );
    }

    /**
     * @param  string|null  $file  The new seeder, added when hasSeeders() lists its files.
     */
    public function seeders(?string $file = null): Registration
    {
        return $this->apply(
            toolkit: fn (PhpFileEditor $e) => $this->toolkitResource($e, 'hasSeeders', $file !== null ? "'$file'" : null, 'hasSeeders()'),
            laravel: fn (PhpFileEditor $e) => $this->laravelStatements($e, [
                ['boot', "\$this->publishes([\n    {$this->path('database/seeders')} => database_path('seeders'),\n], '{$this->tag('seeders')}');"],
            ]),
        );
    }

    public function factories(): Registration
    {
        return $this->apply(
            toolkit: fn (PhpFileEditor $e) => $this->toolkitResource($e, 'hasFactories', null, 'hasFactories()'),
            laravel: fn (PhpFileEditor $e) => $this->laravelStatements($e, [
                ['boot', "\$this->publishes([\n    {$this->path('database/factories')} => database_path('factories'),\n], '{$this->tag('factories')}');"],
            ]),
        );
    }

    public function views(): Registration
    {
        $short = $this->package->shortName();

        return $this->apply(
            toolkit: fn (PhpFileEditor $e) => $this->toolkitResource($e, 'hasViews', null, 'hasViews()'),
            laravel: fn (PhpFileEditor $e) => $this->laravelStatements($e, [
                ['boot', "\$this->loadViewsFrom({$this->path('resources/views')}, '$short');"],
                ['boot', "\$this->publishes([\n    {$this->path('resources/views')} => resource_path('views/vendor/$short'),\n], '{$this->tag('views')}');"],
            ]),
        );
    }

    public function translations(): Registration
    {
        $short = $this->package->shortName();

        return $this->apply(
            toolkit: fn (PhpFileEditor $e) => $this->toolkitResource($e, 'hasTranslations', null, 'hasTranslations()'),
            laravel: fn (PhpFileEditor $e) => $this->laravelStatements($e, [
                ['boot', "\$this->loadTranslationsFrom({$this->path('lang')}, '$short');"],
                ['boot', "\$this->loadJsonTranslationsFrom({$this->path('lang')});"],
                ['boot', "\$this->publishes([\n    {$this->path('lang')} => lang_path('vendor/$short'),\n], '{$this->tag('translations')}');"],
            ]),
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

                $this->ensureMethod($e, 'boot');
                $e->addUse($class);

                if (! $e->addItemToThisCallArray('boot', 'commands', "$short::class")) {
                    $e->appendToMethod('boot', "if (\$this->app->runningInConsole()) {\n    \$this->commands([\n        $short::class,\n    ]);\n}");
                }

                return Registration::registered("$short::class in commands()");
            },
        );
    }

    public function componentNamespace(string $namespace): Registration
    {
        $prefix = $this->package->shortName();

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
            laravel: function (PhpFileEditor $e) use ($namespace, $prefix) {
                $e->addUse('Illuminate\Support\Facades\Blade');

                return $this->laravelStatements($e, [
                    ['boot', "Blade::componentNamespace('$namespace', '$prefix');"],
                ]);
            },
        );
    }

    public function middlewareAlias(string $alias, string $class): Registration
    {
        $short = self::basename($class);

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
            laravel: function (PhpFileEditor $e) use ($alias, $class, $short) {
                $e->addUse($class);

                return $this->laravelStatements($e, [
                    ['boot', "\$this->app['router']->aliasMiddleware('$alias', $short::class);"],
                ]);
            },
        );
    }

    public function listener(string $event, string $listener): Registration
    {
        $eventShort = self::basename($event);
        $listenerShort = self::basename($listener);

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
            laravel: function (PhpFileEditor $e) use ($event, $listener, $eventShort, $listenerShort) {
                $e->addUse('Illuminate\Support\Facades\Event')->addUse($event)->addUse($listener);

                return $this->laravelStatements($e, [
                    ['boot', "Event::listen($eventShort::class, $listenerShort::class);"],
                ]);
            },
        );
    }

    /**
     * @param  Closure(PhpFileEditor): Registration  $toolkit
     * @param  Closure(PhpFileEditor): Registration  $laravel
     */
    private function apply(Closure $toolkit, Closure $laravel): Registration
    {
        $path = $this->package->mainProviderPath();

        if ($path === null) {
            return Registration::noProvider();
        }

        $editor = PhpFileEditor::fromFile($path);
        $result = $editor->hasPackagerConfiguration() ? $toolkit($editor) : $laravel($editor);

        if ($result->status === Registration::REGISTERED) {
            file_put_contents($path, $editor->code());

            return Registration::registered(
                'Registered '.$result->message.' in '.$this->package->relativePath($path).'.'
            );
        }

        return $result;
    }

    /**
     * Make sure a Packager call covers the resource.
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
     * @param  list<array{0: string, 1: string, 2?: string}>  $statements  [method, code, dedupe needle] tuples
     */
    private function laravelStatements(PhpFileEditor $editor, array $statements): Registration
    {
        $added = [];

        foreach ($statements as $statement) {
            [$method, $code] = $statement;
            $lines = explode("\n", $code);
            // Multi-line blocks (publishes) are identified by their distinctive second line.
            $needle = $statement[2] ?? trim($lines[count($lines) > 1 ? 1 : 0]);

            if ($editor->contains($needle)) {
                continue;
            }

            $this->ensureMethod($editor, $method);
            $editor->appendToMethod($method, $code);
            $added[$method][] = self::callName($code);
        }

        if ($added === []) {
            return Registration::already();
        }

        $parts = [];

        foreach ($added as $method => $calls) {
            $parts[] = implode(', ', array_unique($calls))." in $method()";
        }

        return Registration::registered(implode(' and ', $parts));
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
        $providerDir = dirname((string) $this->package->mainProviderPath());
        $relativeDir = $providerDir === $this->package->path()
            ? ''
            : trim(str_replace('\\', '/', $this->package->relativePath($providerDir)), '/');
        $depth = $relativeDir === '' ? 0 : substr_count($relativeDir, '/') + 1;

        return "__DIR__.'/".str_repeat('../', $depth).$relative."'";
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
