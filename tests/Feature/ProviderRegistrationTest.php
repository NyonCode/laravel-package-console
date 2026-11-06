<?php

/**
 * Generate a set of resources that all need registering.
 */
function generateResources(string $dir): void
{
    foreach ([
        ['make:route', ['name' => 'web']],
        ['make:route', ['name' => 'api']],
        ['make:route', ['name' => 'channels']],
        ['make:migration', ['name' => 'create_posts_table']],
        ['make:seeder', ['name' => 'BlogSeeder']],
        ['make:command', ['name' => 'SyncCommand']],
        ['make:command', ['name' => 'PruneCommand']],
        ['make:middleware', ['name' => 'EnsureAuthor', '--alias' => 'blog.author']],
        ['make:listener', ['name' => 'Notify', '--event' => 'PostPublished']],
        ['make:component', ['name' => 'Alert']],
        ['make:lang', []],
    ] as [$command, $input]) {
        [$code, $output] = anvil($dir, $command, [...$input, '--force' => true]);
        expect($code)->toBe(0, $output);
    }
}

it('registers everything in the toolkit Packager chain', function () {
    $dir = initPackage();

    generateResources($dir);

    $provider = file_get_contents("$dir/src/BlogServiceProvider.php");

    expect($provider)->toContain(<<<'PHP'
        $packager
            ->name('Blog')
            ->hasShortName('blog')
            ->hasConfig()
            ->hasRoutes(['web.php', 'api.php'])
            ->hasBroadcastChannels(['channels.php'])
            ->hasMigrations()
            ->hasSeeders()
            ->hasCommands([SyncCommand::class, PruneCommand::class])
            ->hasMiddlewareAliases(['blog.author' => EnsureAuthor::class])
            ->hasEvent(PostPublished::class, Notify::class)
            ->hasViews()
            ->hasComponentNamespace('blog', 'Acme\Blog\View\Components')
            ->hasTranslations();
PHP)->toContain('use Acme\Blog\Commands\SyncCommand;');

    expectValidPhp("$dir/src/BlogServiceProvider.php");
});

it('registers everything in a plain ServiceProvider', function () {
    $dir = initPackage(toolkit: false);

    generateResources($dir);

    $provider = file_get_contents("$dir/src/BlogServiceProvider.php");

    expect($provider)
        ->toContain("\$this->loadRoutesFrom(__DIR__.'/../routes/web.php');")
        ->toContain("\$this->loadRoutesFrom(__DIR__.'/../routes/api.php');")
        ->toContain("require __DIR__.'/../routes/channels.php';")
        ->toContain("__DIR__.'/../database/migrations' => database_path('migrations'),\n        ], 'blog-migrations');")
        ->toContain("            \$this->commands([\n                SyncCommand::class,\n                PruneCommand::class,\n            ]);")
        ->toContain("\$this->app['router']->aliasMiddleware('blog.author', EnsureAuthor::class);")
        ->toContain('Event::listen(PostPublished::class, Notify::class);')
        ->toContain("Blade::componentNamespace('Acme\Blog\View\Components', 'blog');")
        ->toContain("\$this->loadViewsFrom(__DIR__.'/../resources/views', 'blog');")
        ->toContain("\$this->loadTranslationsFrom(__DIR__.'/../lang', 'blog');")
        ->not->toContain('//');

    expectValidPhp("$dir/src/BlogServiceProvider.php");
});

it('is idempotent', function (bool $toolkit) {
    $dir = initPackage(toolkit: $toolkit);

    generateResources($dir);
    $first = file_get_contents("$dir/src/BlogServiceProvider.php");

    generateResources($dir);

    expect(file_get_contents("$dir/src/BlogServiceProvider.php"))->toBe($first);
})->with(['toolkit' => true, 'plain' => false]);

it('computes resource paths relative to a nested provider', function () {
    $dir = initPackage(toolkit: false);
    $composer = composerJson($dir);
    $composer['extra']['laravel']['providers'] = ['Acme\\Blog\\Providers\\BlogServiceProvider'];
    file_put_contents("$dir/composer.json", json_encode($composer));
    mkdir("$dir/src/Providers");
    rename("$dir/src/BlogServiceProvider.php", "$dir/src/Providers/BlogServiceProvider.php");

    anvil($dir, 'make:route', ['name' => 'web']);

    expect(file_get_contents("$dir/src/Providers/BlogServiceProvider.php"))
        ->toContain("\$this->loadRoutesFrom(__DIR__.'/../../routes/web.php');");
});

it('asks for a manual edit when an existing call cannot be extended', function () {
    $dir = initPackage();
    $path = "$dir/src/BlogServiceProvider.php";
    file_put_contents($path, str_replace('->hasConfig();', "->hasConfig()\n            ->hasRoutes('web.php');", file_get_contents($path)));

    [$code, $output] = anvil($dir, 'make:route', ['name' => 'api']);

    expect($code)->toBe(0)
        ->and($output)->toContain("WARN Add 'api.php' to ->hasRoutes('web.php')");
});

it('warns when the package has no service provider yet', function () {
    $dir = tempPackage(['name' => 'acme/blog', 'autoload' => ['psr-4' => ['Acme\\Blog\\' => 'src/']]]);

    [$code, $output] = anvil($dir, 'make:config');

    expect($code)->toBe(0)
        ->and($output)->toContain('No service provider found')
        ->and("$dir/config/blog.php")->toBeFile();
});

it('makes the first provider the main one and registers extra providers after it', function () {
    $dir = tempPackage(['name' => 'acme/blog', 'autoload' => ['psr-4' => ['Acme\\Blog\\' => 'src/']]]);

    anvil($dir, 'make:provider');
    anvil($dir, 'make:provider', ['name' => 'EventServiceProvider']);

    expect(composerJson($dir)['extra']['laravel']['providers'])->toBe([
        'Acme\\Blog\\BlogServiceProvider',
        'Acme\\Blog\\Providers\\EventServiceProvider',
    ]);
});

describe('toolkit mode', function () {
    it('treats a call without arguments as auto-discovery', function (string $call, string $command, array $input) {
        $dir = initPackage();
        rewriteProvider($dir, '->hasConfig();', "->hasConfig()\n            ->$call;");
        $before = file_get_contents("$dir/src/BlogServiceProvider.php");

        [$code, $output] = anvil($dir, $command, $input);

        expect($code)->toBe(0, $output)
            ->and(file_get_contents("$dir/src/BlogServiceProvider.php"))->toBe($before)
            ->and($output)->not->toContain('Registered');
    })->with([
        'hasCommands()' => ['hasCommands()', 'make:command', ['name' => 'Sync']],
        'hasRoutes()' => ['hasRoutes()', 'make:route', ['name' => 'web']],
        'hasBroadcastChannels()' => ['hasBroadcastChannels()', 'make:route', ['name' => 'channels']],
    ]);

    it('adds to a multi-line array and keeps its trailing comma', function () {
        $dir = initPackage();
        rewriteProvider($dir, '->hasConfig();', "->hasConfig()\n            ->hasRoutes([\n                'web.php',\n            ]);");

        anvil($dir, 'make:route', ['name' => 'api']);

        expect(file_get_contents("$dir/src/BlogServiceProvider.php"))
            ->toContain("->hasRoutes([\n                'web.php',\n                'api.php',\n            ]);");
    });

    it('recognises a command registered with hasCommand()', function () {
        $dir = initPackage();
        rewriteProvider($dir, '->hasConfig();', "->hasConfig()\n            ->hasCommand(Commands\\Sync::class);");
        $before = file_get_contents("$dir/src/BlogServiceProvider.php");

        anvil($dir, 'make:command', ['name' => 'Sync']);

        expect(file_get_contents("$dir/src/BlogServiceProvider.php"))->toBe($before);
    });

    it('asks for a manual edit when the argument is not an array', function () {
        $dir = initPackage();
        rewriteProvider($dir, '->hasConfig();', "->hasConfig()\n            ->hasCommands(\$this->commands());");

        [$code, $output] = anvil($dir, 'make:command', ['name' => 'Sync']);

        expect($code)->toBe(0)
            ->and($output)->toContain('WARN Add Sync::class to ->hasCommands($this->commands()) in the service provider.')
            ->and("$dir/src/Commands/Sync.php")->toBeFile();
    });

    it('starts the chain when configure() has none', function () {
        $dir = initPackage();
        rewriteProvider($dir, "\$packager\n            ->name('Blog')\n            ->hasShortName('blog')\n            ->hasConfig();", '//');

        anvil($dir, 'make:route', ['name' => 'web']);

        expect(file_get_contents("$dir/src/BlogServiceProvider.php"))
            ->toContain("\$packager\n            ->hasRoutes(['web.php']);");
        expectValidPhp("$dir/src/BlogServiceProvider.php");
    });

    it('decides the mode from the provider, not from composer.json', function () {
        $dir = initPackage(toolkit: false);
        $composer = composerJson($dir);
        $composer['require']['nyoncode/laravel-package-toolkit'] = '^2.5';
        file_put_contents("$dir/composer.json", json_encode($composer));

        anvil($dir, 'make:route', ['name' => 'web']);

        expect(file_get_contents("$dir/src/BlogServiceProvider.php"))
            ->toContain("\$this->loadRoutesFrom(__DIR__.'/../routes/web.php');");
    });
});

describe('plain mode', function () {
    it('creates boot() and register() when they are missing', function () {
        $dir = initPackage(toolkit: false, options: ['--no-config' => true]);
        file_put_contents("$dir/src/BlogServiceProvider.php", <<<'PHP'
<?php

namespace Acme\Blog;

use Illuminate\Support\ServiceProvider;

class BlogServiceProvider extends ServiceProvider
{
}

PHP);

        anvil($dir, 'make:config');
        anvil($dir, 'make:route');

        $provider = file_get_contents("$dir/src/BlogServiceProvider.php");

        expect($provider)
            ->toContain("public function register(): void\n    {\n        \$this->mergeConfigFrom(")
            ->toContain("public function boot(): void\n    {\n        \$this->publishes([")
            ->toContain("\$this->loadRoutesFrom(__DIR__.'/../routes/web.php');");
        expectValidPhp("$dir/src/BlogServiceProvider.php");
    });

    it('adds commands to an existing commands() call', function () {
        $dir = initPackage(toolkit: false);
        rewriteProvider($dir, "public function boot(): void\n    {\n", "public function boot(): void\n    {\n        \$this->commands([Commands\\Existing::class]);\n");

        anvil($dir, 'make:command', ['name' => 'Sync']);

        expect(file_get_contents("$dir/src/BlogServiceProvider.php"))
            ->toContain('$this->commands([Commands\Existing::class, Sync::class]);')
            ->not->toContain('runningInConsole');
    });

    it('keeps the code that is already in the provider', function () {
        $dir = initPackage(toolkit: false);
        rewriteProvider($dir, "public function boot(): void\n    {\n", "public function boot(): void\n    {\n        // my own code\n        \$this->app->singleton('blog', fn () => new Blog);\n");

        anvil($dir, 'make:route');

        expect(file_get_contents("$dir/src/BlogServiceProvider.php"))
            ->toContain("        // my own code\n        \$this->app->singleton('blog', fn () => new Blog);\n");
    });

    it('registers in the main provider even when it is not first in composer.json', function () {
        $dir = initPackage(toolkit: false);
        $composer = composerJson($dir);
        array_unshift($composer['extra']['laravel']['providers'], 'Acme\\Blog\\Missing\\Provider');
        file_put_contents("$dir/composer.json", json_encode($composer));

        anvil($dir, 'make:route');

        expect(file_get_contents("$dir/src/BlogServiceProvider.php"))->toContain('loadRoutesFrom');
    });
});

it('reads every $packager statement in configure(), not only the last one', function () {
    $dir = initPackage();
    rewriteProvider($dir, "\$packager\n            ->name('Blog')\n            ->hasShortName('blog')\n            ->hasConfig();", "\$packager->name('Blog')->hasShortName('blog');\n\n        \$packager->hasRoutes(['web.php']);\n\n        \$packager->hasConfig();");

    anvil($dir, 'make:route', ['name' => 'api']);
    [$code, $output] = anvil($dir, 'make:config', ['name' => 'extra']);

    expect($code)->toBe(0)
        ->and($output)->not->toContain('Registered')
        ->and(file_get_contents("$dir/src/BlogServiceProvider.php"))
        ->toContain("\$packager->hasRoutes(['web.php', 'api.php']);")
        ->not->toContain('->hasRoutes([\'api.php\'])');
});

it('adds new files to an explicit list of migrations or seeders', function (string $call, string $command, array $input, string $expected) {
    $dir = initPackage();
    rewriteProvider($dir, '->hasConfig();', "->hasConfig()\n            ->$call;");

    [$code, $output] = anvil($dir, $command, $input);

    expect($code)->toBe(0, $output)
        ->and(file_get_contents("$dir/src/BlogServiceProvider.php"))->toContain("->$expected;");
})->with([
    'migrations' => ["hasMigrations(['create_blog_posts_table.php'])", 'make:migration', ['name' => 'add_slug_to_blog_posts_table'], "hasMigrations(['create_blog_posts_table.php', 'add_slug_to_blog_posts_table.php'])"],
    'seeders' => ["hasSeeders(['BlogSeeder.php'])", 'make:seeder', ['name' => 'BlogPostSeeder'], "hasSeeders(['BlogSeeder.php', 'BlogPostSeeder.php'])"],
]);
