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
        ['name' => 'name', 'args' => "'Blog'"],
        ['name' => 'hasRoutes', 'args' => "['web.php']"],
        ['name' => 'hasConfig', 'args' => ''],
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
