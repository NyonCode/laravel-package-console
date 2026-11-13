<?php

use NyonCode\LaravelPackageConsole\Support\Path;

it('normalizes a path', function (string $path, string $normalized) {
    expect(Path::normalize($path))->toBe($normalized);
})->with([
    ['packages/blog', 'packages/blog'],
    ['./packages/blog/', 'packages/blog'],
    ['packages//blog/./src/..', 'packages/blog'],
    ['', '.'],
    ['./', '.'],
    ['../toolkit', '../toolkit'],
    ['packages/../../toolkit', '../toolkit'],
    ['/var/mono/../mono/packages', '/var/mono/packages'],
    ['/..', '/'],
    ['C:\\mono\\packages\\blog', 'C:/mono/packages/blog'],
]);

it('tells an absolute path', function (string $path, bool $absolute) {
    expect(Path::isAbsolute($path))->toBe($absolute);
})->with([
    ['/var/mono', true],
    ['C:\\mono', true],
    ['packages/blog', false],
    ['./packages', false],
]);

it('joins a path to a directory', function () {
    expect(Path::join('/mono/', 'packages/blog'))->toBe('/mono'.DIRECTORY_SEPARATOR.'packages/blog')
        ->and(Path::join('/mono', '.'))->toBe('/mono')
        ->and(Path::join('/mono', '/elsewhere'))->toBe('/elsewhere');
});
