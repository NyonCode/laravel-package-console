<?php

use NyonCode\LaravelPackageConsole\Support\PhpFileEditor;

const PROVIDER = <<<'PHP'
<?php

namespace Acme\Blog;

use Illuminate\Support\ServiceProvider;

class BlogServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'blog');
    }
}
PHP;

const TOOLKIT_PROVIDER = <<<'PHP'
<?php

namespace Acme\Blog;

use NyonCode\LaravelPackageToolkit\PackageServiceProvider;
use NyonCode\LaravelPackageToolkit\Packager;

class BlogServiceProvider extends PackageServiceProvider
{
    public function configure(Packager $packager): void
    {
        $packager
            ->name('Blog')
            ->hasRoutes(['web.php']);
    }
}
PHP;

it('adds imports in alphabetical order', function () {
    $code = (new PhpFileEditor(PROVIDER))
        ->addUse('Acme\Blog\Commands\Sync')
        ->addUse('Zeta\Thing')
        ->addUse('Illuminate\Support\Facades\Event')
        ->code();

    expect($code)->toContain(
        "use Acme\Blog\Commands\Sync;\nuse Illuminate\Support\Facades\Event;\nuse Illuminate\Support\ServiceProvider;\nuse Zeta\Thing;\n"
    );
});

it('does not import a class twice or from the same namespace', function () {
    $code = (new PhpFileEditor(PROVIDER))
        ->addUse('Illuminate\Support\ServiceProvider')
        ->addUse('Acme\Blog\Other')
        ->code();

    expect($code)->toBe(PROVIDER);
});

it('replaces a // placeholder when appending to an empty method', function () {
    $code = (new PhpFileEditor(PROVIDER))->appendToMethod('register', '$this->app->singleton(Foo::class);')->code();

    expect($code)->toContain("    public function register(): void\n    {\n        \$this->app->singleton(Foo::class);\n    }")
        ->not->toContain('//');
});

it('appends multi-line statements with the method indentation', function () {
    $code = (new PhpFileEditor(PROVIDER))->appendToMethod('boot', "if (true) {\n    foo();\n}")->code();

    expect($code)->toContain("'blog');\n        if (true) {\n            foo();\n        }\n    }");
});

it('adds a missing method at the end of the class', function () {
    $editor = (new PhpFileEditor(TOOLKIT_PROVIDER))->addMethod("public function boot(): void\n{\n    //\n}");

    expect($editor->hasMethod('boot'))->toBeTrue()
        ->and($editor->code())->toContain("    }\n\n    public function boot(): void\n    {\n        //\n    }\n}");
});

it('reads and extends the Packager chain', function () {
    $editor = (new PhpFileEditor(TOOLKIT_PROVIDER))->appendPackagerCall('hasConfig()');

    expect($editor->packagerCalls())->toBe([
        ['name' => 'name', 'args' => "'Blog'", 'values' => [['Blog']]],
        ['name' => 'hasRoutes', 'args' => "['web.php']", 'values' => [['web.php']]],
        ['name' => 'hasConfig', 'args' => '', 'values' => []],
    ])->and($editor->code())->toContain("            ->hasRoutes(['web.php'])\n            ->hasConfig();");
});

it('starts a Packager chain when configure() has none', function () {
    $code = str_replace("\$packager\n            ->name('Blog')\n            ->hasRoutes(['web.php']);", '//', TOOLKIT_PROVIDER);

    $editor = (new PhpFileEditor($code))->appendPackagerCall("name('Blog')")->appendPackagerCall('hasViews()');

    expect($editor->code())->toContain("        \$packager\n            ->name('Blog')\n            ->hasViews();");
});

it('adds items to an array argument, single-line and multi-line', function () {
    $editor = new PhpFileEditor(TOOLKIT_PROVIDER);

    expect($editor->addItemToPackagerCallArray('hasRoutes', "'api.php'"))->toBeTrue()
        ->and($editor->code())->toContain("->hasRoutes(['web.php', 'api.php'])")
        ->and($editor->addItemToPackagerCallArray('hasCommands', 'X::class'))->toBeFalse();

    $multiline = (new PhpFileEditor(PROVIDER))->appendToMethod('boot', "\$this->commands([\n    A::class,\n]);");
    $multiline->addItemToThisCallArray('boot', 'commands', 'B::class');

    expect($multiline->code())->toContain("\$this->commands([\n            A::class,\n            B::class,\n        ]);");
});

describe('registrations()', function () {
    $provider = fn (string $body) => new PhpFileEditor("<?php\n\nnamespace Acme\\Blog;\n\nclass BlogServiceProvider extends ServiceProvider\n{\n    public function boot(): void\n    {\n$body\n    }\n}\n");

    it('reads a path however it is written', function (string $statement, string $call, ?string $path) use ($provider) {
        $registrations = $provider($statement)->registrations('src');

        expect($registrations)->toHaveCount(1)
            ->and($registrations[0]['call'])->toBe($call)
            ->and($registrations[0]['path'])->toBe($path);
    })->with([
        'Anvil' => ["\$this->loadRoutesFrom(__DIR__.'/../routes/web.php');", 'loadRoutesFrom', 'routes/web.php'],
        'spaces around the dot' => ["\$this->loadRoutesFrom(__DIR__ . '/../routes/web.php');", 'loadRoutesFrom', 'routes/web.php'],
        'dirname(__DIR__)' => ["\$this->loadMigrationsFrom(dirname(__DIR__).'/database/migrations');", 'loadMigrationsFrom', 'database/migrations'],
        'dirname(__DIR__, 1)' => ["\$this->loadViewsFrom(dirname(__DIR__, 1).'/resources/views', 'blog');", 'loadViewsFrom', 'resources/views'],
        'dirname(__FILE__)' => ["\$this->mergeConfigFrom(dirname(__FILE__).'/../config/blog.php', 'blog');", 'mergeConfigFrom', 'config/blog.php'],
        'realpath()' => ["\$this->loadTranslationsFrom(realpath(__DIR__.'/../lang'), 'blog');", 'loadTranslationsFrom', 'lang'],
        'require' => ["require __DIR__.'/../routes/channels.php';", 'require', 'routes/channels.php'],
        'require_once in a closure' => ["Route::group([], function () {\n    require_once __DIR__.'/../routes/web.php';\n});", 'require', 'routes/web.php'],
        'a helper method' => ["\$this->loadRoutesFrom(\$this->basePath('routes/web.php'));", 'loadRoutesFrom', null],
        'a variable' => ['$this->loadRoutesFrom($routes);', 'loadRoutesFrom', null],
        'outside the package' => ["\$this->loadRoutesFrom(__DIR__.'/../../routes/web.php');", 'loadRoutesFrom', null],
    ]);

    it('resolves __DIR__ from the directory of the file', function () use ($provider) {
        $editor = $provider("\$this->loadRoutesFrom(__DIR__.'/../../routes/web.php');");

        expect($editor->registrations('src/Providers')[0]['path'])->toBe('routes/web.php')
            ->and($provider("\$this->loadRoutesFrom(__DIR__.'/routes/web.php');")->registrations()[0]['path'])->toBe('routes/web.php');
    });

    it('lists every source of publishes() and every directory of loadMigrationsFrom()', function () use ($provider) {
        $editor = $provider(<<<'PHP'
        $this->publishes([
            __DIR__.'/../config/blog.php' => config_path('blog.php'),
            __DIR__.'/../resources/views' => resource_path('views/vendor/blog'),
        ], 'blog');
        $this->loadMigrationsFrom([__DIR__.'/../database/migrations', __DIR__.'/../database/legacy']);
PHP);

        expect(array_map(fn (array $r) => $r['call'].' '.$r['path'], $editor->registrations('src')))->toBe([
            'publishes config/blog.php',
            'publishes resources/views',
            'loadMigrationsFrom database/migrations',
            'loadMigrationsFrom database/legacy',
        ]);
    });

    it('keeps the string literals of a path it cannot resolve', function () use ($provider) {
        $registration = $provider("\$this->loadRoutesFrom(\$this->basePath('routes/web.php'));")->registrations('src')[0];

        expect($registration['literals'])->toBe(['routes/web.php'])
            ->and($registration['source'])->toBe("\$this->basePath('routes/web.php')");
    });

    it('finds calls in every method of the class', function () {
        $editor = new PhpFileEditor("<?php\n\nclass P\n{\n    public function boot(): void\n    {\n        \$this->bootRoutes();\n    }\n\n    private function bootRoutes(): void\n    {\n        \$this->loadRoutesFrom(__DIR__.'/../routes/web.php');\n    }\n}\n");

        expect(array_column($editor->registrations('src'), 'path'))->toBe(['routes/web.php']);
    });
});
