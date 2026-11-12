<?php

/*
 * Installing Anvil into a package that already exists: its provider, its style of
 * registration, its test setup and its CI must all be respected.
 */

function writeProvider(string $dir, string $path, string $code): void
{
    if (! is_dir(dirname("$dir/$path"))) {
        mkdir(dirname("$dir/$path"), 0755, true);
    }

    file_put_contents("$dir/$path", $code);
}

describe('spatie/laravel-package-tools', function () {
    it('registers in configurePackage() and never overrides register() or boot()', function () {
        $dir = spatiePackage();

        buildFullPackage($dir);

        $provider = file_get_contents("$dir/src/BlogServiceProvider.php");

        expect($provider)
            ->not->toContain('function register(')
            ->not->toContain('function boot(')
            ->toContain('public function packageBooted(): void')
            ->and(composerJson($dir)['require'])->not->toHaveKey('nyoncode/laravel-package-toolkit');

        expectValidPhp("$dir/src/BlogServiceProvider.php");
    });

    it('takes the short name from ->name(), as spatie does', function () {
        $dir = spatiePackage(name: 'laravel-weblog');

        generate($dir, 'make:view', ['name' => 'posts.index']);
        $output = generate($dir, 'make:lang');

        expect("$dir/config/weblog.php")->toBeFile()
            ->and($output)->toContain("__('weblog::messages.key')")
            ->and(generate($dir, 'package:info'))->toContain('weblog (config key');
    });

    it('extends hasConfigFile() to a list, because each call replaces the previous one', function (string $existing, string $expected) {
        $dir = spatiePackage();
        rewriteProvider($dir, '->hasConfigFile()', $existing);

        generate($dir, 'make:config', ['name' => 'blog-cache']);

        expect(provider($dir))->toContain($expected);
    })->with([
        'no arguments' => ['->hasConfigFile()', "->hasConfigFile(['blog', 'blog-cache'])"],
        'a string' => ["->hasConfigFile('blog')", "->hasConfigFile(['blog', 'blog-cache'])"],
        'an array' => ["->hasConfigFile(['blog'])", "->hasConfigFile(['blog', 'blog-cache'])"],
    ]);

    it('leaves discovered migrations alone', function () {
        $dir = spatiePackage();
        rewriteProvider($dir, '->hasConfigFile()', "->hasConfigFile()\n            ->discoversMigrations()");
        $before = provider($dir);

        generate($dir, 'make:migration', ['name' => 'create_blog_posts_table']);

        expect(provider($dir))->toBe($before)
            ->and("$dir/database/migrations/create_blog_posts_table.php")->toBeFile();
    });

    it('recognises commands registered with hasCommands()', function () {
        $dir = spatiePackage();
        rewriteProvider($dir, '->hasConfigFile()', "->hasConfigFile()\n            ->hasCommands([Commands\\Sync::class])");
        $before = provider($dir);

        generate($dir, 'make:command', ['name' => 'Sync']);

        expect(provider($dir))->toBe($before);
    });

    it('writes translations to resources/lang, where spatie loads them', function () {
        $dir = spatiePackage();

        generate($dir, 'make:lang', ['--json' => true, '--locale' => 'cs']);
        generate($dir, 'make:lang');

        expect("$dir/resources/lang/cs.json")->toBeFile()
            ->and("$dir/resources/lang/en/messages.php")->toBeFile()
            ->and("$dir/lang")->not->toBeDirectory();
    });
});

describe('a provider that extends another base class', function () {
    beforeEach(function () {
        $this->dir = tempPackage([
            'name' => 'acme/laravel-blog',
            'autoload' => ['psr-4' => ['Acme\\Blog\\' => 'src/']],
            'extra' => ['laravel' => ['providers' => ['Acme\\Blog\\BlogServiceProvider']]],
        ]);
        writeProvider($this->dir, 'src/BlogServiceProvider.php', <<<'PHP'
<?php

namespace Acme\Blog;

use Vendor\Foundation\BaseServiceProvider;

class BlogServiceProvider extends BaseServiceProvider
{
    public function register(): void
    {
        parent::register();
    }
}

PHP);
    });

    it('does not add boot(), and says what to add by hand', function () {
        $before = provider($this->dir);

        [$code, $output] = anvil($this->dir, 'make:route', ['name' => 'web']);

        expect($code)->toBe(0)
            ->and(provider($this->dir))->toBe($before)
            ->and($output)
            ->toContain('WARN src/BlogServiceProvider.php extends Vendor\Foundation\BaseServiceProvider and has no boot().')
            ->toContain("\$this->loadRoutesFrom(__DIR__.'/../routes/web.php');")
            ->and("$this->dir/routes/web.php")->toBeFile();
    });

    it('adds to the methods the provider already has', function () {
        generate($this->dir, 'make:config', ['name' => 'blog']);

        expect(provider($this->dir))->toContain("parent::register();\n        \$this->mergeConfigFrom(__DIR__.'/../config/blog.php', 'blog');");
    });
});

describe('package:init', function () {
    it('finds a provider that composer.json does not list, instead of creating a second one', function () {
        $dir = tempPackage(['name' => 'acme/laravel-blog', 'autoload' => ['psr-4' => ['Acme\\Blog\\' => 'src/']]]);
        writeProvider($dir, 'src/Providers/BlogProvider.php', "<?php\n\nnamespace Acme\\Blog\\Providers;\n\nuse Illuminate\\Support\\ServiceProvider;\n\nclass BlogProvider extends ServiceProvider\n{\n}\n");

        $output = generate($dir, 'package:init', ['--author' => 'Jane']);

        expect($output)->toContain('Found src/Providers/BlogProvider.php and registered Acme\Blog\Providers\BlogProvider')
            ->and(composerJson($dir)['extra']['laravel']['providers'])->toBe(['Acme\\Blog\\Providers\\BlogProvider'])
            ->and(composerJson($dir)['require'])->not->toHaveKey('nyoncode/laravel-package-toolkit')
            ->and("$dir/src/BlogServiceProvider.php")->not->toBeFile()
            ->and(file_get_contents("$dir/src/Providers/BlogProvider.php"))->toContain("mergeConfigFrom(__DIR__.'/../../config/blog.php', 'blog')");
    });

    it('prefers the conventional provider name when there are several', function () {
        $dir = tempPackage(['name' => 'acme/laravel-blog', 'autoload' => ['psr-4' => ['Acme\\Blog\\' => 'src/']]]);
        writeProvider($dir, 'src/Providers/RouteServiceProvider.php', "<?php\n\nnamespace Acme\\Blog\\Providers;\n\nuse Illuminate\\Support\\ServiceProvider;\n\nclass RouteServiceProvider extends ServiceProvider\n{\n}\n");
        writeProvider($dir, 'src/BlogServiceProvider.php', "<?php\n\nnamespace Acme\\Blog;\n\nuse Illuminate\\Support\\ServiceProvider;\n\nclass BlogServiceProvider extends ServiceProvider\n{\n}\n");

        generate($dir, 'package:init', ['--author' => 'Jane']);

        expect(composerJson($dir)['extra']['laravel']['providers'])->toBe(['Acme\\Blog\\BlogServiceProvider']);
    });

    it('finds a spatie provider and keeps spatie', function () {
        $dir = spatiePackage(listed: false);

        expect(composerJson($dir)['extra']['laravel']['providers'])->toBe(['Acme\\Blog\\BlogServiceProvider'])
            ->and(composerJson($dir)['require'])->toHaveKey('spatie/laravel-package-tools')
            ->not->toHaveKey('nyoncode/laravel-package-toolkit')
            ->and(provider($dir))->toContain("->name('laravel-blog')\n            ->hasConfigFile();");
    });

    it('only adds the toolkit to an existing package with --toolkit, and warns that the provider keeps its style', function () {
        $dir = spatiePackage(init: false);

        [$code, $output] = anvil($dir, 'package:init', ['--toolkit' => true, '--author' => 'Jane']);

        expect($code)->toBe(0)
            ->and(composerJson($dir)['require'])->toHaveKey('nyoncode/laravel-package-toolkit')
            ->and($output)->toContain('WARN src/BlogServiceProvider.php is not a laravel-package-toolkit provider');
    });

    it('still uses the toolkit by default for a new package', function () {
        $dir = tempPackage(['name' => 'acme/laravel-blog', 'require-dev' => ['nyoncode/laravel-package-console' => '^1.0']]);

        generate($dir, 'package:init', ['--author' => 'Jane']);

        expect(composerJson($dir)['require'])->toHaveKey('nyoncode/laravel-package-toolkit')
            ->and(provider($dir))->toContain('extends PackageServiceProvider');
    });

    it('adds Anvil itself to require-dev, so the launcher works after composer update', function () {
        expect(composerJson(initPackage())['require-dev']['nyoncode/laravel-package-console'])->toBe('^1.0');
    });

    it('does not clash with the test setup and CI the package already has', function () {
        $dir = spatiePackage(init: false);
        file_put_contents("$dir/phpunit.xml.dist", '<phpunit/>');
        file_put_contents("$dir/LICENSE", 'MIT');
        file_put_contents("$dir/phpstan.neon", 'parameters:');
        writeProvider($dir, 'tests/TestCase.php', "<?php\n\nnamespace Acme\\Blog\\Tests;\n\nabstract class TestCase {}\n");
        writeProvider($dir, '.github/workflows/run-tests.yml', "jobs:\n  test:\n    steps:\n      - run: vendor/bin/pest --ci\n");

        $output = generate($dir, 'package:init', ['--author' => 'Jane']);

        foreach (['phpunit.xml', 'LICENSE.md', 'phpstan.neon.dist', 'tests/Pest.php', 'tests/ArchTest.php', 'tests/Feature/ServiceProviderTest.php', '.github/workflows/tests.yml'] as $file) {
            expect("$dir/$file")->not->toBeFile($file);
        }

        expect($output)
            ->toContain('SKIP   phpunit.xml (phpunit.xml.dist exists)')
            ->toContain('SKIP   .github/workflows/tests.yml (.github/workflows/run-tests.yml exists)')
            ->toContain('SKIP   tests/Pest.php (tests/TestCase.php exists)');
    });

    it('writes its own CI workflow when the existing ones do not run tests', function () {
        $dir = spatiePackage(init: false);
        writeProvider($dir, '.github/workflows/fix-styling.yml', "jobs:\n  pint:\n    steps:\n      - run: vendor/bin/pint\n");

        generate($dir, 'package:init', ['--author' => 'Jane']);

        expect("$dir/.github/workflows/tests.yml")->toBeFile();
    });
});

describe('an existing toolkit provider', function () {
    it('takes the short name from hasShortName() or name(), as the toolkit does', function (string $chain, string $short) {
        $dir = initPackage();
        rewriteProvider($dir, "->name('Blog')\n            ->hasShortName('blog')", $chain);

        expect(generate($dir, 'package:info'))->toContain("$short (config key");
    })->with([
        'hasShortName()' => ["->name('Blog')\n            ->hasShortName('weblog')", 'weblog'],
        'name() in words' => ["->name('Blog Posts')", 'blog-posts'],
        'name() in StudlyCase' => ["->name('BlogPosts')", 'blog-posts'],
    ]);
});

describe('a hand-written plain provider', function () {
    /**
     * A package whose provider registers its resources in boot(), written by hand.
     */
    $package = function (string $boot): string {
        $dir = tempPackage([
            'name' => 'acme/laravel-blog',
            'autoload' => ['psr-4' => ['Acme\\Blog\\' => 'src/']],
            'extra' => ['laravel' => ['providers' => ['Acme\\Blog\\BlogServiceProvider']]],
        ]);
        writeProvider($dir, 'src/BlogServiceProvider.php', "<?php\n\nnamespace Acme\\Blog;\n\nuse Illuminate\\Support\\ServiceProvider;\n\nclass BlogServiceProvider extends ServiceProvider\n{\n    public function boot(): void\n    {\n        $boot\n    }\n}\n");

        return $dir;
    };

    it('does not register again what the provider already registers its own way', function (string $boot, string $command, array $input) use ($package) {
        $dir = $package($boot);
        $before = provider($dir);

        generate($dir, $command, $input);

        expect(provider($dir))->toBe($before);
    })->with([
        'routes, spaces around the dot' => ["\$this->loadRoutesFrom(__DIR__ . '/../routes/api.php');", 'make:route', ['name' => 'api']],
        'routes, required in a group' => ["Route::group([], function () {\n            require __DIR__.'/../routes/api.php';\n        });", 'make:route', ['name' => 'api']],
        'routes, through a helper' => ["\$this->loadRoutesFrom(\$this->basePath('routes/api.php'));", 'make:route', ['name' => 'api']],
        'migrations, loaded' => ["\$this->loadMigrationsFrom(dirname(__DIR__).'/database/migrations');", 'make:migration', ['name' => 'create_posts_table']],
        'migrations, published file by file' => ["\$this->publishes([\n            __DIR__.'/../database/migrations/create_tags_table.php.stub' => database_path('migrations/create_tags_table.php'),\n        ], 'migrations');", 'make:migration', ['name' => 'create_posts_table']],
        'config, published as a directory' => ["\$this->mergeConfigFrom(__DIR__ . '/../config/blog.php', 'blog');\n        \$this->publishes([__DIR__ . '/../config' => config_path()], 'config');", 'make:config', ['name' => 'blog']],
    ]);

    it('adds only what is missing', function () use ($package) {
        $dir = $package("\$this->loadViewsFrom(__DIR__ . '/../resources/views', 'blog');");

        generate($dir, 'make:view', ['name' => 'posts.index']);

        expect(substr_count(provider($dir), 'loadViewsFrom'))->toBe(1)
            ->and(provider($dir))->toContain("__DIR__.'/../resources/views' => resource_path('views/vendor/blog'),");
    });

    it('publishes views under the namespace the provider loads them as', function () use ($package) {
        $dir = $package("\$this->loadViewsFrom(__DIR__.'/../resources/views', 'laravel-blog');");

        generate($dir, 'make:view', ['name' => 'posts.index']);

        expect(provider($dir))->toContain("__DIR__.'/../resources/views' => resource_path('views/vendor/laravel-blog'),");
    });

    it('registers a second file next to one the provider registers its own way', function () use ($package) {
        $dir = $package("\$this->loadRoutesFrom(__DIR__ . '/../routes/web.php');");

        generate($dir, 'make:route', ['name' => 'api']);

        expect(provider($dir))->toContain("\$this->loadRoutesFrom(__DIR__.'/../routes/api.php');");
    });
});

it('registers what it can and warns about the rest', function () {
    $dir = tempPackage([
        'name' => 'acme/laravel-blog',
        'autoload' => ['psr-4' => ['Acme\\Blog\\' => 'src/']],
        'extra' => ['laravel' => ['providers' => ['Acme\\Blog\\BlogServiceProvider']]],
    ]);
    writeProvider($dir, 'src/BlogServiceProvider.php', "<?php\n\nnamespace Acme\\Blog;\n\nuse Vendor\\Foundation\\BaseServiceProvider;\n\nclass BlogServiceProvider extends BaseServiceProvider\n{\n    public function register(): void\n    {\n        parent::register();\n    }\n}\n");

    $output = generate($dir, 'make:config', ['name' => 'blog']);

    expect($output)
        ->toContain('DONE Registered mergeConfigFrom() in register()')
        ->toContain('WARN src/BlogServiceProvider.php extends Vendor\Foundation\BaseServiceProvider and has no boot().')
        ->toContain("config_path('blog.php')");
});
