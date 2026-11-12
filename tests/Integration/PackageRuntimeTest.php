<?php

/*
 * Opt-in integration tests (they install packages with Composer, so they need network
 * access and take a few minutes):
 *
 *   composer test:integration
 *
 * For each mode (toolkit / plain / an existing spatie/laravel-package-tools package) and
 * Laravel version (12 / 13) a complete package is
 * generated, installed with `composer update`, and its own test suite runs — including
 * tests/Fixtures/PackageRuntimeTest.stub, which checks in a real Laravel application that
 * everything the generators registered works. Larastan and Pint must pass as well.
 */

beforeEach(function () {
    if (! getenv('ANVIL_INTEGRATION')) {
        $this->markTestSkipped('Integration test: run "composer test:integration".');
    }
});

/**
 * Run a shell command in the package and return its output; fail on a non-zero exit code.
 */
function shell(string $command, string $dir): string
{
    exec('cd '.escapeshellarg($dir).' && '.$command.' 2>&1', $output, $code);

    expect($code)->toBe(0, "$command failed:\n".implode("\n", $output));

    return implode("\n", $output);
}

/**
 * Give the generated stubs something to do, the way a package author would.
 */
function fillInPackage(string $dir, string $mode): void
{
    $replace = function (string $file, string $search, string $replace) use ($dir): void {
        $contents = (string) file_get_contents("$dir/$file");

        expect($contents)->toContain($search);

        file_put_contents("$dir/$file", str_replace($search, $replace, $contents));
    };

    $replace('routes/web.php', "        //\n", "        Route::get('ping', fn () => 'pong')->name('ping');\n");
    $replace('routes/api.php', "        //\n", "        Route::get('ping', fn () => ['pong' => true])->name('ping');\n");
    $replace('routes/admin.php', "        //\n", "        Route::get('ping', fn () => 'admin')->name('ping');\n");
    $lang = $mode === 'spatie' ? 'resources/lang' : 'lang';
    $replace("$lang/en/messages.php", '    //', "    'welcome' => 'Hello from blog',");
    file_put_contents("$dir/$lang/cs.json", "{\n    \"Hello\": \"Ahoj\"\n}\n");
    $replace('src/Blog.php', "namespace Acme\\Blog;\n\nclass Blog\n{\n", "namespace Acme\\Blog;\n\nuse Acme\\Blog\\Concerns\\HasSlug;\n\nclass Blog\n{\n    use HasSlug;\n\n");

    $test = (string) file_get_contents(dirname(__DIR__).'/Fixtures/PackageRuntimeTest.stub');
    file_put_contents("$dir/tests/Feature/PackageRuntimeTest.php", str_replace('{{ separator }}', $mode === 'toolkit' ? '::' : '-', $test));
}

it('generates a package that works in Laravel', function (string $mode, string $laravel) {
    $dir = match ($mode) {
        'toolkit' => initPackage(options: ['--laravel' => $laravel]),
        'plain' => initPackage(toolkit: false, options: ['--laravel' => $laravel]),
        'spatie' => spatiePackage(options: ['--laravel' => $laravel]),
    };

    buildFullPackage($dir);
    fillInPackage($dir, $mode);

    [$code, $output] = anvil($dir, 'package:doctor', ['--strict' => true]);

    expect($code)->toBe(0, $output);

    shell('composer update --no-interaction --no-progress --prefer-dist --no-audit', $dir);

    $framework = shell('php -r '.escapeshellarg('require "vendor/autoload.php"; echo Composer\InstalledVersions::getPrettyVersion("laravel/framework");'), $dir);

    expect(ltrim($framework, 'v'))->toStartWith("$laravel.");

    $output = shell('vendor/bin/pest --colors=never', $dir);

    expect($output)->toContain('PackageRuntimeTest')->not->toContain('skipped');

    shell('vendor/bin/phpstan analyse --no-progress --memory-limit=1G', $dir);
    shell('vendor/bin/pint --test', $dir);
})
    ->with(['toolkit', 'plain', 'spatie'])
    ->with(['Laravel 12' => '12', 'Laravel 13' => '13'])
    ->group('integration');
