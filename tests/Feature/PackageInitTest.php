<?php

use NyonCode\LaravelPackageConsole\Kernel;
use Symfony\Component\Console\Tester\ApplicationTester;

it('creates composer.json for Laravel 12 and 13 with the toolkit', function () {
    $dir = initPackage();
    $composer = composerJson($dir);

    expect($composer['name'])->toBe('acme/laravel-blog')
        ->and($composer['require'])->toBe([
            'php' => '^8.2',
            'illuminate/support' => '^12.0|^13.0',
            'nyoncode/laravel-package-toolkit' => '^2.5',
        ])
        ->and($composer['require-dev']['orchestra/testbench'])->toBe('^10.0|^11.0')
        ->and($composer['autoload']['psr-4'])->toBe([
            'Acme\\Blog\\' => 'src/',
            'Acme\\Blog\\Database\\Factories\\' => 'database/factories/',
        ])
        ->and($composer['autoload-dev']['psr-4'])->toBe(['Acme\\Blog\\Tests\\' => 'tests/'])
        ->and($composer['extra']['laravel']['providers'])->toBe(['Acme\\Blog\\BlogServiceProvider'])
        ->and($composer['authors'][0])->toBe(['name' => 'Jane Doe', 'email' => 'jane@example.com', 'role' => 'Developer']);

    expect(file_get_contents("$dir/src/BlogServiceProvider.php"))
        ->toContain('extends PackageServiceProvider')
        ->toContain("->name('Blog')")
        ->toContain('->hasConfig()');

    foreach (['config/blog.php', 'tests/TestCase.php', 'tests/Pest.php', 'phpunit.xml', 'README.md', 'LICENSE.md', '.github/workflows/tests.yml', 'testbench.yaml'] as $file) {
        expect("$dir/$file")->toBeFile();
    }

    expectValidPhp("$dir/src/BlogServiceProvider.php");
    expectValidPhp("$dir/tests/TestCase.php");
    expectValidPhp("$dir/anvil");
    expect(is_executable("$dir/anvil"))->toBeTrue();
});

it('scaffolds a plain ServiceProvider without the toolkit', function () {
    $dir = initPackage(toolkit: false, options: ['--laravel' => '13']);
    $composer = composerJson($dir);

    expect($composer['require'])->toBe(['php' => '^8.3', 'illuminate/support' => '^13.0'])
        ->and($composer['require-dev']['orchestra/testbench'])->toBe('^11.0')
        ->and($composer['require-dev']['pestphp/pest'])->toBe('^4.0');

    expect(file_get_contents("$dir/src/BlogServiceProvider.php"))
        ->toContain('extends ServiceProvider')
        ->toContain("\$this->mergeConfigFrom(__DIR__.'/../config/blog.php', 'blog');");

    expect(file_get_contents("$dir/.github/workflows/tests.yml"))
        ->toContain("php: ['8.3', '8.4', '8.5']")
        ->not->toContain('exclude');
});

it('completes an existing composer.json without dropping what is there', function () {
    $dir = tempPackage([
        'name' => 'acme/laravel-blog',
        'require-dev' => ['nyoncode/laravel-package-console' => '^1.0', 'orchestra/testbench' => '^10.5'],
        'minimum-stability' => 'dev',
    ]);

    [$code] = anvil($dir, 'package:init', ['--toolkit' => true]);
    $composer = composerJson($dir);

    expect($code)->toBe(0)
        ->and($composer['require-dev']['nyoncode/laravel-package-console'])->toBe('^1.0')
        ->and($composer['require-dev']['orchestra/testbench'])->toBe('^10.5')
        ->and($composer['minimum-stability'])->toBe('dev')
        ->and($composer['autoload']['psr-4'])->toHaveKey('Acme\\Blog\\');
});

it('keeps existing files unless --force is given', function () {
    $dir = initPackage();
    file_put_contents("$dir/README.md", 'mine');

    [, $output] = anvil($dir, 'package:init', ['--toolkit' => true]);

    expect(file_get_contents("$dir/README.md"))->toBe('mine')
        ->and($output)->toContain('SKIP   README.md');
});

it('rejects an invalid package name and unsupported Laravel versions', function () {
    expect(anvil(tempPackage(), 'package:init', ['--name' => 'Not Valid'])[0])->toBe(1)
        ->and(anvil(tempPackage(), 'package:init', ['--name' => 'acme/blog', '--laravel' => '11'])[1])
        ->toContain('Supported Laravel versions: 12, 13');
});

it('sets the constraints for each supported Laravel version', function (string $laravel, array $require, array $requireDev, string $phpMatrix) {
    $dir = initPackage(options: ['--laravel' => $laravel]);
    $composer = composerJson($dir);

    expect(array_intersect_key($composer['require'], $require))->toBe($require)
        ->and(array_intersect_key($composer['require-dev'], $requireDev))->toBe($requireDev)
        ->and(file_get_contents("$dir/.github/workflows/tests.yml"))->toContain("php: [$phpMatrix]");
})->with([
    'Laravel 12' => ['12', ['php' => '^8.2', 'illuminate/support' => '^12.0'], [
        'orchestra/testbench' => '^10.0', 'pestphp/pest' => '^3.8|^4.0', 'pestphp/pest-plugin-laravel' => '^3.2|^4.0',
    ], "'8.2', '8.3', '8.4', '8.5'"],
    'Laravel 13' => ['13', ['php' => '^8.3', 'illuminate/support' => '^13.0'], [
        'orchestra/testbench' => '^11.0', 'pestphp/pest' => '^4.0', 'pestphp/pest-plugin-laravel' => '^4.0',
    ], "'8.3', '8.4', '8.5'"],
    'Laravel 12 and 13' => ['13,12', ['php' => '^8.2', 'illuminate/support' => '^12.0|^13.0'], [
        'orchestra/testbench' => '^10.0|^11.0', 'pestphp/pest' => '^3.8|^4.0',
    ], "'8.2', '8.3', '8.4', '8.5'"],
]);

it('excludes PHP versions a Laravel version does not support from the CI matrix', function () {
    $dir = initPackage();

    expect(file_get_contents("$dir/.github/workflows/tests.yml"))
        ->toContain("laravel: ['12.*', '13.*']")
        ->toContain("          - laravel: '12.*'\n            testbench: '10.*'\n          - laravel: '13.*'\n            testbench: '11.*'")
        ->toContain("        exclude:\n          - laravel: '13.*'\n            php: '8.2'");
});

it('writes the anvil launcher, test setup and tooling', function () {
    $dir = initPackage();

    expect(file_get_contents("$dir/anvil"))->toContain('exit((new Kernel(__DIR__))->run());')
        ->and(is_executable("$dir/anvil"))->toBeTrue()
        ->and(file_get_contents("$dir/.gitattributes"))->toContain('/anvil              export-ignore')
        ->and(file_get_contents("$dir/tests/TestCase.php"))
        ->toContain('use Acme\Blog\BlogServiceProvider;')
        ->toContain('BlogServiceProvider::class,')
        ->and(file_get_contents("$dir/tests/Pest.php"))->toContain('pest()->extend(TestCase::class)->in(__DIR__);')
        ->and(file_get_contents("$dir/testbench.yaml"))->toBe("providers:\n  - Acme\Blog\BlogServiceProvider\n")
        ->and(file_get_contents("$dir/phpunit.xml"))->toMatch('~<env name="APP_KEY" value="base64:[A-Za-z0-9+/]{43}="/>~')
        ->and(file_get_contents("$dir/phpstan.neon.dist"))->toContain('level: 5')
        ->and(file_get_contents("$dir/pint.json"))->toContain('"preset": "laravel"')
        ->and(file_get_contents("$dir/README.md"))
        ->toContain('composer require acme/laravel-blog')
        ->toContain('php artisan vendor:publish --tag="blog::config"')
        ->and(file_get_contents("$dir/LICENSE.md"))->toContain('Jane Doe');

    expect(file_get_contents(initPackage(toolkit: false).'/README.md'))
        ->toContain('php artisan vendor:publish --tag="blog-config"');
});

it('skips the test setup with --no-tests and the config with --no-config', function () {
    $dir = initPackage(options: ['--no-tests' => true, '--no-config' => true]);

    foreach (['phpunit.xml', 'tests/TestCase.php', 'tests/Pest.php', '.github/workflows/tests.yml', 'config/blog.php'] as $file) {
        expect("$dir/$file")->not->toBeFile();
    }

    expect(file_get_contents("$dir/src/BlogServiceProvider.php"))->not->toContain('hasConfig')
        ->and("$dir/anvil")->toBeFile();
});

it('derives the namespace and short name from the package name', function (string $name, string $namespace, string $provider) {
    $dir = tempPackage();

    [$code, $output] = anvil($dir, 'package:init', ['--name' => $name, '--toolkit' => true, '--author' => 'A']);

    expect($code)->toBe(0, $output)
        ->and(composerJson($dir)['autoload']['psr-4'])->toHaveKey("$namespace\\")
        ->and("$dir/src/$provider.php")->toBeFile();
})->with([
    ['acme/laravel-blog', 'Acme\Blog', 'BlogServiceProvider'],
    ['acme/blog-laravel', 'Acme\Blog', 'BlogServiceProvider'],
    ['acme/laravel-blog-posts', 'Acme\BlogPosts', 'BlogPostsServiceProvider'],
    ['my-company/seo-tools', 'MyCompany\SeoTools', 'SeoToolsServiceProvider'],
]);

it('uses --namespace and --description', function () {
    $dir = initPackage(options: ['--namespace' => 'Acme\\Publishing', '--description' => 'Blogging for Laravel.']);
    $composer = composerJson($dir);

    expect($composer['autoload']['psr-4'])->toHaveKey('Acme\\Publishing\\')
        ->and($composer['description'])->toBe('Blogging for Laravel.')
        ->and(file_get_contents("$dir/src/BlogServiceProvider.php"))->toContain('namespace Acme\Publishing;');
});

it('keeps the namespace of an existing composer.json', function () {
    $dir = tempPackage(['name' => 'acme/laravel-blog', 'autoload' => ['psr-4' => ['Acme\\Weblog\\' => 'src/']]]);

    anvil($dir, 'package:init', ['--toolkit' => true]);

    expect(composerJson($dir)['autoload']['psr-4'])->toBe([
        'Acme\\Weblog\\' => 'src/',
        'Acme\\Weblog\\Database\\Factories\\' => 'database/factories/',
    ])->and("$dir/src/BlogServiceProvider.php")->toBeFile();
});

it('keeps an existing main provider when run again', function () {
    $dir = initPackage(toolkit: false);
    $provider = file_get_contents("$dir/src/BlogServiceProvider.php");

    [$code] = anvil($dir, 'package:init', ['--no-toolkit' => true]);

    expect($code)->toBe(0)
        ->and(file_get_contents("$dir/src/BlogServiceProvider.php"))->toBe($provider)
        ->and(composerJson($dir)['extra']['laravel']['providers'])->toBe(['Acme\\Blog\\BlogServiceProvider']);
});

it('has the init alias', function () {
    $dir = tempPackage();

    [$code] = anvil($dir, 'init', ['--name' => 'acme/blog', '--toolkit' => true, '--author' => 'A']);

    expect($code)->toBe(0)->and("$dir/composer.json")->toBeFile();
});

it('asks for everything when interactive', function () {
    $dir = tempPackage();

    $kernel = new Kernel($dir);
    $kernel->application->setAutoExit(false);
    $tester = new ApplicationTester($kernel->application);
    // Name, description, namespace, author, email, provider, Laravel versions, confirmation.
    $tester->setInputs(['acme/laravel-shop', 'A shop.', '', 'Jane Doe', 'jane@example.com', 'plain', '', '']);

    $code = $tester->run(['command' => 'package:init'], ['interactive' => true, 'decorated' => false]);
    $composer = composerJson($dir);

    expect($code)->toBe(0, $tester->getDisplay())
        ->and($composer['name'])->toBe('acme/laravel-shop')
        ->and($composer['description'])->toBe('A shop.')
        ->and($composer['autoload']['psr-4'])->toHaveKey('Acme\\Shop\\')
        ->and($composer['require'])->not->toHaveKey('nyoncode/laravel-package-toolkit')
        ->and(file_get_contents("$dir/src/ShopServiceProvider.php"))->toContain('extends ServiceProvider');
});
