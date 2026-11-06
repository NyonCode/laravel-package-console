<?php

use NyonCode\LaravelPackageConsole\Support\ComposerJson;

it('reads and writes with dot notation, keeping empty objects and key order', function () {
    $dir = tempPackage(['require' => ['php' => '^8.2'], 'name' => 'acme/blog', 'extra' => (object) []]);
    $composer = ComposerJson::in($dir);

    $composer->set('extra.laravel.providers', ['Acme\\Blog\\BlogServiceProvider'])
        ->setIfMissing('name', 'other/name')
        ->mergeMissing('require', ['php' => '^8.3', 'illuminate/support' => '^12.0'])
        ->set('scripts', []);
    $composer->save();

    $json = (string) file_get_contents("$dir/composer.json");

    expect(array_keys(json_decode($json, true)))->toBe(['name', 'require', 'extra', 'scripts'])
        ->and($json)->toContain('"scripts": {}')
        ->and($json)->toContain('"Acme\\\\Blog\\\\BlogServiceProvider"')
        ->and($composer->get('require.php'))->toBe('^8.2')
        ->and($composer->get('name'))->toBe('acme/blog')
        ->and($composer->pushUnique('keywords', 'laravel'))->toBeTrue()
        ->and($composer->pushUnique('keywords', 'laravel'))->toBeFalse();
});
