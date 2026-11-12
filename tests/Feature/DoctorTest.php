<?php

/*
 * package:doctor compares the files of a package with what its service provider registers.
 */

/**
 * The main provider with nothing registered, as each mode writes it.
 */
function bareProvider(string $mode): string
{
    $body = match ($mode) {
        'toolkit' => <<<'PHP'
use NyonCode\LaravelPackageToolkit\Packager;
use NyonCode\LaravelPackageToolkit\PackageServiceProvider;

class BlogServiceProvider extends PackageServiceProvider
{
    public function configure(Packager $packager): void
    {
        $packager
            ->name('Blog')
            ->hasShortName('blog');
    }
}
PHP,
        'spatie' => <<<'PHP'
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class BlogServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-blog');
    }
}
PHP,
        'plain' => <<<'PHP'
use Illuminate\Support\ServiceProvider;

class BlogServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        //
    }
}
PHP,
    };

    return "<?php\n\nnamespace Acme\\Blog;\n\n$body\n";
}

/**
 * A plain package with one provider, whose boot() holds $boot.
 */
function plainPackage(string $boot = '//'): string
{
    $dir = tempPackage([
        'name' => 'acme/laravel-blog',
        'autoload' => ['psr-4' => ['Acme\\Blog\\' => 'src/']],
        'extra' => ['laravel' => ['providers' => ['Acme\\Blog\\BlogServiceProvider']]],
    ]);

    put($dir, 'src/BlogServiceProvider.php', str_replace('//', $boot, bareProvider('plain')));

    return $dir;
}

describe('a package built by the generators', function () {
    it('has nothing to report', function (string $mode) {
        $dir = packageIn($mode);
        buildFullPackage($dir);

        [$code, $output] = doctor($dir, ['--strict' => true]);

        expect($code)->toBe(0, $output)
            ->and(trim($output))->toBe('DONE The service provider and composer.json match the package.');
    })->with('modes');

    it('registers everything again when the provider lost its registrations', function (string $mode) {
        $dir = packageIn($mode);
        buildFullPackage($dir);
        put($dir, 'src/BlogServiceProvider.php', bareProvider($mode));

        [$code, $output] = doctor($dir);

        expect($code)->toBe(1)
            ->and($output)
            ->toContain('ERROR config/blog-cache.php is not registered in the service provider.')
            ->toContain('ERROR routes/api.php is not registered')
            ->toContain('ERROR routes/channels.php is not registered')
            ->toContain('ERROR resources/views is not registered')
            ->toContain('ERROR src/Commands/SyncPostsCommand.php is not registered')
            ->toContain('WARN  src/View/Components has view components')
            ->toContain('Run package:doctor --fix to fix');

        [$code, $output] = doctor($dir, ['--fix' => true]);

        expect($code)->toBe(0, $output)->and($output)->toContain('FIXED config/blog-cache.php: registered ');

        $fixed = provider($dir);
        [$code, $output] = doctor($dir, ['--fix' => true, '--strict' => true]);

        expect($code)->toBe(0, $output)
            ->and(trim($output))->toBe('DONE The service provider and composer.json match the package.')
            ->and(provider($dir))->toBe($fixed);

        expectValidPhp("$dir/src/BlogServiceProvider.php");
        expectPintClean($dir);
    })->with('modes');
});

describe('files the provider does not register', function () {
    it('reports each kind of resource, and --fix registers it', function (string $file, string $missing, string $contents = "<?php\n\nreturn [];\n") {
        $dir = plainPackage();
        put($dir, $file, $contents);

        [$code, $output] = doctor($dir);

        expect($code)->toBe(1)
            ->and($output)->toContain($missing)
            ->and(substr_count($output, 'ERROR'))->toBe(1);

        expect(doctor($dir, ['--fix' => true])[0])->toBe(0)
            ->and(doctor($dir, ['--strict' => true])[0])->toBe(0);

        expectValidPhp("$dir/src/BlogServiceProvider.php");
    })->with([
        'config' => ['config/blog.php', 'ERROR config/blog.php is not registered in the service provider. Missing: mergeConfigFrom() in register() and publishes() in boot().'],
        'routes' => ['routes/web.php', 'ERROR routes/web.php is not registered in the service provider. Missing: loadRoutesFrom() in boot().'],
        'channels' => ['routes/channels.php', 'ERROR routes/channels.php is not registered in the service provider. Missing: require routes/channels.php in boot().'],
        'migration' => ['database/migrations/2026_01_01_000000_create_posts_table.php', 'create_posts_table.php is not registered in the service provider. Missing: publishesMigrations() in boot().'],
        'seeder' => ['database/seeders/PostSeeder.php', 'ERROR database/seeders/PostSeeder.php is not registered'],
        'view' => ['resources/views/posts/index.blade.php', 'ERROR resources/views is not registered in the service provider. Missing: loadViewsFrom(), publishes() in boot().', '<div></div>'],
        'translations' => ['lang/en/messages.php', 'ERROR lang is not registered'],
        'command' => ['src/Commands/Prune.php', 'ERROR src/Commands/Prune.php is not registered in the service provider. Missing: Prune::class in commands().', "<?php\n\nnamespace Acme\\Blog\\Commands;\n\nuse Illuminate\\Console\\Command;\n\nclass Prune extends Command\n{\n}\n"],
    ]);

    it('reports a directory of migrations once', function () {
        $dir = plainPackage();
        put($dir, 'database/migrations/2026_01_01_000000_create_posts_table.php');
        put($dir, 'database/migrations/2026_01_02_000000_create_tags_table.php');

        expect(substr_count(doctor($dir)[1], 'ERROR'))->toBe(1);
    });

    it('finds commands through a base class of the package, and skips abstract ones', function () {
        $dir = plainPackage();
        put($dir, 'src/Commands/BaseCommand.php', "<?php\n\nnamespace Acme\\Blog\\Commands;\n\nuse Illuminate\\Console\\Command;\n\nabstract class BaseCommand extends Command\n{\n}\n");
        put($dir, 'src/Commands/Prune.php', "<?php\n\nnamespace Acme\\Blog\\Commands;\n\nclass Prune extends BaseCommand\n{\n}\n");

        $output = doctor($dir)[1];

        expect($output)->toContain('ERROR src/Commands/Prune.php is not registered')
            ->and($output)->not->toContain('BaseCommand.php');
    });

    it('leaves a route file alone that another route file requires', function () {
        $dir = plainPackage("\$this->loadRoutesFrom(__DIR__.'/../routes/web.php');");
        put($dir, 'routes/web.php', "<?php\n\nrequire __DIR__.'/admin.php';\n");
        put($dir, 'routes/admin.php');

        expect(doctor($dir, ['--strict' => true])[0])->toBe(0);
    });

    it('accepts what the provider registers in its own words', function () {
        $dir = plainPackage(<<<'PHP'
$this->mergeConfigFrom(__DIR__ . '/../config/blog.php', 'blog');
        $this->publishes([__DIR__ . '/../config' => config_path()], 'config');
        $this->loadRoutesFrom(dirname(__DIR__).'/routes/web.php');
        $this->loadMigrationsFrom(dirname(__DIR__) . '/database/migrations');
PHP);
        put($dir, 'config/blog.php');
        put($dir, 'routes/web.php');
        put($dir, 'database/migrations/2026_01_01_000000_create_posts_table.php');

        [$code, $output] = doctor($dir, ['--strict' => true]);

        expect($code)->toBe(0, $output);
    });

    it('does not guess when the provider registers a path it cannot read', function () {
        $dir = plainPackage('$this->loadRoutesFrom($this->routes);');
        put($dir, 'routes/web.php');
        $before = provider($dir);

        [$code, $output] = doctor($dir, ['--fix' => true, '--strict' => true]);

        expect($code)->toBe(0, $output)
            ->and($output)->toContain('INFO  routes/web.php may not be registered: the service provider calls loadRoutesFrom($this->routes), which may or may not cover routes/web.php.')
            ->and(provider($dir))->toBe($before);
    });

    it('says what to add by hand when it cannot add it', function () {
        $dir = initPackage();
        rewriteProvider($dir, '->hasConfig()', '->hasConfig()->hasRoutes($this->routes())');
        put($dir, 'routes/web.php');

        [$code, $output] = doctor($dir);

        expect($code)->toBe(0, $output)
            ->and($output)->toContain("WARN  routes/web.php is not registered in the service provider, and Anvil cannot add it. Add 'web.php' to ->hasRoutes(\$this->routes()) in the service provider.")
            ->and(doctor($dir, ['--strict' => true])[0])->toBe(1);
    });
});

describe('registrations of what the package does not have', function () {
    it('reports a missing file in a plain provider', function () {
        $dir = plainPackage("\$this->loadRoutesFrom(__DIR__.'/../routes/api.php');\n        \$this->loadViewsFrom(__DIR__.'/../resources/views', 'blog');");

        [$code, $output] = doctor($dir);

        expect($code)->toBe(1)
            ->and($output)
            ->toContain('ERROR routes/api.php does not exist, but the service provider passes it to loadRoutesFrom().')
            ->toContain('ERROR resources/views does not exist, but the service provider passes it to loadViewsFrom().');
    });

    it('reports a missing file in a toolkit provider', function () {
        $dir = initPackage();
        rewriteProvider($dir, '->hasConfig()', "->hasConfig()->hasRoutes(['web.php', 'api.php'])->hasViews()");
        put($dir, 'routes/web.php');

        [$code, $output] = doctor($dir);

        expect($code)->toBe(1)
            ->and($output)
            ->toContain('ERROR routes/api.php does not exist, but the service provider names it in ->hasRoutes().')
            ->toContain('WARN  resources/views does not exist, but the service provider calls ->hasViews().')
            ->not->toContain('routes/web.php');
    });

    it('reports a missing file in a spatie provider', function () {
        $dir = spatiePackage();
        rewriteProvider($dir, "->name('laravel-blog')", "->name('laravel-blog')->hasRoute('api')->hasMigration('create_posts_table')->hasConfigFile(['blog', 'blog-cache'])");
        put($dir, 'database/migrations/create_posts_table.php.stub');

        [$code, $output] = doctor($dir);

        expect($code)->toBe(1)
            ->and($output)
            ->toContain('ERROR routes/api.php does not exist, but the service provider names it in ->hasRoute().')
            ->toContain('ERROR config/blog-cache.php does not exist, but the service provider names it in ->hasConfigFile().')
            ->not->toContain('create_posts_table');
    });

    it('reports a class of the package that has no file', function () {
        $dir = plainPackage('$this->commands([Commands\Prune::class, \Illuminate\Support\Str::class]);');

        [$code, $output] = doctor($dir);

        expect($code)->toBe(1)
            ->and($output)->toContain('ERROR Acme\Blog\Commands\Prune is used in the service provider, but src/Commands/Prune.php does not exist.')
            ->and(substr_count($output, 'ERROR'))->toBe(1);
    });
});

describe('composer.json', function () {
    it('lists a provider that composer.json leaves out', function () {
        $dir = tempPackage(['name' => 'acme/laravel-blog', 'autoload' => ['psr-4' => ['Acme\\Blog\\' => 'src/']]]);
        put($dir, 'src/BlogServiceProvider.php', bareProvider('plain'));
        put($dir, 'routes/web.php');

        [$code, $output] = doctor($dir);

        expect($code)->toBe(1)
            ->and($output)->toContain('ERROR Acme\Blog\BlogServiceProvider is not listed in composer.json "extra.laravel.providers", so Laravel does not load it.');

        [$code, $output] = doctor($dir, ['--fix' => true]);

        expect($code)->toBe(0, $output)
            ->and($output)->toContain('FIXED Acme\Blog\BlogServiceProvider: listed in composer.json "extra.laravel.providers".')->toContain('FIXED routes/web.php: registered loadRoutesFrom() in boot().')
            ->and(composerJson($dir)['extra']['laravel']['providers'])->toBe(['Acme\\Blog\\BlogServiceProvider'])
            ->and(provider($dir))->toContain("\$this->loadRoutesFrom(__DIR__.'/../routes/web.php');");
    });

    it('reports a listed provider that does not exist, and a package without one', function () {
        $dir = tempPackage([
            'name' => 'acme/laravel-blog',
            'autoload' => ['psr-4' => ['Acme\\Blog\\' => 'src/']],
            'extra' => ['laravel' => ['providers' => ['Acme\\Blog\\BlogServiceProvider']]],
        ]);

        [$code, $output] = doctor($dir);

        expect($code)->toBe(1)
            ->and($output)
            ->toContain('ERROR Acme\Blog\BlogServiceProvider is listed in composer.json "extra.laravel.providers", but src/BlogServiceProvider.php does not exist.')
            ->toContain('ERROR The package has no service provider. Run "make:provider --main" to create one.');
    });

    it('reports a class that is not where PSR-4 expects it', function () {
        $dir = plainPackage();
        put($dir, 'src/Models/Post.php', "<?php\n\nnamespace Acme\\Blog;\n\nclass Post\n{\n}\n");
        put($dir, 'src/helpers.php', "<?php\n\nfunction blog(): string\n{\n    return 'blog';\n}\n");

        [$code, $output] = doctor($dir);

        expect($code)->toBe(1)
            ->and($output)->toContain('ERROR src/Models/Post.php declares Acme\Blog\Post, but composer.json maps the file to Acme\Blog\Models\Post, so the class cannot be autoloaded.')
            ->and(substr_count($output, 'ERROR'))->toBe(1);
    });

    it('warns about a provider whose library composer.json does not require', function () {
        $dir = plainPackage();
        put($dir, 'src/BlogServiceProvider.php', bareProvider('toolkit'));

        [$code, $output] = doctor($dir);

        expect($code)->toBe(0, $output)
            ->and($output)->toContain('WARN  src/BlogServiceProvider.php has configure(), which Anvil reads as a nyoncode/laravel-package-toolkit provider, but composer.json does not require nyoncode/laravel-package-toolkit.');
    });
});

it('warns when views are loaded under another namespace than the short name', function () {
    $dir = plainPackage("\$this->loadViewsFrom(__DIR__.'/../resources/views', 'laravel-blog');\n        \$this->publishes([__DIR__.'/../resources/views' => resource_path('views/vendor/laravel-blog')]);");
    put($dir, 'resources/views/index.blade.php', '<div></div>');

    [$code, $output] = doctor($dir);

    expect($code)->toBe(0, $output)
        ->and($output)->toContain('WARN  src/BlogServiceProvider.php loads the views as "laravel-blog::", but Anvil\'s short name for the package is "blog": generated code refers to "blog::".')
        ->and($output)->toContain('1 warning.');
});

it('warns about translations a spatie provider does not load', function () {
    $dir = spatiePackage();
    put($dir, 'lang/en/messages.php');

    expect(doctor($dir)[1])->toContain('WARN  lang holds the translations, but spatie/laravel-package-tools loads them from resources/lang.');
});

it('prints the findings as JSON', function () {
    $dir = plainPackage();
    put($dir, 'routes/web.php');

    [$code, $output] = doctor($dir, ['--json' => true]);

    expect($code)->toBe(1)
        ->and(json_decode($output, true))->toBe([
            'errors' => 1,
            'warnings' => 0,
            'fixed' => [],
            'findings' => [[
                'severity' => 'error',
                'check' => 'unregistered',
                'subject' => 'routes/web.php',
                'message' => 'is not registered in the service provider. Missing: loadRoutesFrom() in boot().',
                'fix' => 'registered loadRoutesFrom() in boot()',
            ]],
        ]);
});

it('needs a composer.json', function () {
    [$code, $output] = doctor(tempPackage());

    expect($code)->toBe(1)->and($output)->toContain('No composer.json');
});
