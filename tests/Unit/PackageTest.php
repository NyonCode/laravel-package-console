<?php

use NyonCode\LaravelPackageConsole\Support\Package;

it('derives the short name from the composer name', function (string $name, string $short) {
    expect(Package::shortNameFor($name))->toBe($short);
})->with([
    ['acme/laravel-blog', 'blog'],
    ['acme/blog-laravel', 'blog'],
    ['acme/blog-posts', 'blog-posts'],
    ['nyoncode/laravel-package-toolkit', 'package-toolkit'],
]);

it('resolves class paths from PSR-4 using the longest prefix', function () {
    $dir = tempPackage(['autoload' => ['psr-4' => [
        'Acme\\Blog\\' => 'src/',
        'Acme\\Blog\\Database\\Factories\\' => 'database/factories/',
    ]]]);
    $package = new Package($dir);

    expect($package->rootNamespace())->toBe('Acme\\Blog')
        ->and($package->pathForClass('Acme\\Blog\\Models\\Post'))->toBe("$dir/src/Models/Post.php")
        ->and($package->pathForClass('Acme\\Blog\\Database\\Factories\\PostFactory'))->toBe("$dir/database/factories/PostFactory.php")
        ->and($package->pathForClass('Other\\Thing'))->toBeNull();
});

it('detects the toolkit and the main provider', function () {
    $dir = tempPackage([
        'require' => ['nyoncode/laravel-package-toolkit' => '^2.5'],
        'autoload' => ['psr-4' => ['Acme\\Blog\\' => 'src/']],
        'extra' => ['laravel' => ['providers' => ['Acme\\Blog\\Missing', 'Acme\\Blog\\BlogServiceProvider']]],
    ]);
    mkdir("$dir/src");
    touch("$dir/src/BlogServiceProvider.php");

    $package = new Package($dir);

    expect($package->usesToolkit())->toBeTrue()
        ->and($package->mainProvider())->toBe('Acme\\Blog\\BlogServiceProvider');
});

it('explains a missing composer.json', function () {
    (new Package(tempPackage()))->rootNamespace();
})->throws(RuntimeException::class, 'Run "package:init" first');
