<?php

use NyonCode\LaravelPackageConsole\Kernel;
use Symfony\Component\Console\Tester\ApplicationTester;

/*
 * Helpers: every test works in a fresh temporary package directory.
 */

/**
 * Create a temporary package directory, optionally with a composer.json.
 *
 * @param  array<string, mixed>|null  $composer
 */
function tempPackage(?array $composer = null): string
{
    $dir = sys_get_temp_dir().'/anvil-tests/'.bin2hex(random_bytes(6));
    mkdir($dir, 0755, true);

    if ($composer !== null) {
        file_put_contents($dir.'/composer.json', json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    return $dir;
}

/**
 * A package created with package:init (toolkit or plain).
 */
function initPackage(bool $toolkit = true, array $options = []): string
{
    $dir = tempPackage();

    [$code, $output] = anvil($dir, 'package:init', [
        '--name' => 'acme/laravel-blog',
        '--author' => 'Jane Doe',
        '--email' => 'jane@example.com',
        $toolkit ? '--toolkit' : '--no-toolkit' => true,
        ...$options,
    ]);

    expect($code)->toBe(0, $output);

    return $dir;
}

/**
 * Run a console command in the given package directory.
 *
 * @param  array<string, mixed>  $input
 * @return array{0: int, 1: string} [exit code, output]
 */
function anvil(string $dir, string $command, array $input = []): array
{
    $kernel = new Kernel($dir);
    $kernel->application->setAutoExit(false);

    $tester = new ApplicationTester($kernel->application);
    $code = $tester->run(['command' => $command, ...$input], ['interactive' => false, 'decorated' => false]);

    return [$code, $tester->getDisplay()];
}

/**
 * Assert that a file is valid PHP.
 */
function expectValidPhp(string $path): void
{
    exec('php -l '.escapeshellarg($path).' 2>&1', $output, $code);

    expect($code)->toBe(0, implode("\n", $output));
}

function composerJson(string $dir): array
{
    return json_decode((string) file_get_contents($dir.'/composer.json'), true);
}

/**
 * Every generator, with the options a real package uses. Shared by the full-package
 * tests and the runtime integration tests.
 *
 * @return list<array{0: string, 1: array<string, mixed>}>
 */
function fullPackageCommands(): array
{
    return [
        ['make:route', ['name' => 'web']],
        ['make:route', ['name' => 'api']],
        ['make:route', ['name' => 'channels']],
        ['make:route', ['name' => 'admin']],
        ['make:config', ['name' => 'blog-cache']],
        ['make:model', ['name' => 'Post', '--all' => true]],
        ['make:migration', ['name' => 'add_slug_to_posts_table']],
        ['make:migration', ['name' => 'archive_old_posts']],
        ['make:command', ['name' => 'SyncPostsCommand']],
        ['make:command', ['name' => 'Prune', '--command' => 'blog:prune']],
        ['make:middleware', ['name' => 'EnsureAuthor', '--alias' => 'blog.author']],
        ['make:event', ['name' => 'PostPublished']],
        ['make:listener', ['name' => 'NotifySubscribers', '--event' => 'PostPublished', '--queued' => true]],
        ['make:listener', ['name' => 'LogLogin', '--event' => 'Illuminate\Auth\Events\Login']],
        ['make:component', ['name' => 'Alert']],
        ['make:component', ['name' => 'Forms/TextInput', '--inline' => true]],
        ['make:component', ['name' => 'badge', '--view' => true]],
        ['make:view', ['name' => 'posts.index']],
        ['make:lang', ['name' => 'messages']],
        ['make:lang', ['--json' => true, '--locale' => 'cs']],
        ['make:mail', ['name' => 'PostDigest', '--markdown' => null]],
        ['make:mail', ['name' => 'Welcome', '--view' => 'mail.hello']],
        ['make:job', ['name' => 'IndexPost']],
        ['make:job', ['name' => 'ClearCache', '--sync' => true]],
        ['make:notification', ['name' => 'PostCommented']],
        ['make:observer', ['name' => 'PostObserver', '--model' => 'Post']],
        ['make:rule', ['name' => 'Slug']],
        ['make:cast', ['name' => 'Money']],
        ['make:cast', ['name' => 'Hashed', '--inbound' => true]],
        ['make:scope', ['name' => 'PublishedScope']],
        ['make:channel', ['name' => 'PostChannel']],
        ['make:enum', ['name' => 'PostStatus', '--string' => true]],
        ['make:enum', ['name' => 'Priority', '--int' => true]],
        ['make:enum', ['name' => 'Visibility']],
        ['make:exception', ['name' => 'PostNotFound', '--render' => true]],
        ['make:exception', ['name' => 'InvalidSlug']],
        ['make:facade', ['name' => 'Blog']],
        ['make:class', ['name' => 'Blog']],
        ['make:class', ['name' => 'Actions/PublishPost', '--invokable' => true]],
        ['make:interface', ['name' => 'Publishable']],
        ['make:trait', ['name' => 'HasSlug']],
        ['make:request', ['name' => 'StorePostRequest']],
        ['make:resource', ['name' => 'PostResource']],
        ['make:resource', ['name' => 'PostCollection']],
        ['make:controller', ['name' => 'PingController', '--invokable' => true]],
        ['make:controller', ['name' => 'Api/PostController', '--api' => true, '--model' => 'Post']],
        ['make:controller', ['name' => 'DashboardController']],
        ['make:policy', ['name' => 'CommentPolicy']],
        ['make:test', ['name' => 'PostApi']],
        ['make:test', ['name' => 'Slug', '--unit' => true, '--phpunit' => true]],
        ['make:provider', ['name' => 'EventServiceProvider']],
    ];
}

/**
 * Run every generator in the package and assert each one succeeded.
 *
 * @param  array<string, mixed>  $extra  Input added to every command (e.g. ['--force' => true]).
 * @return array<string, string> Output of each command, keyed by "command name".
 */
function buildFullPackage(string $dir, array $extra = []): array
{
    $outputs = [];

    foreach (fullPackageCommands() as [$command, $input]) {
        [$code, $output] = anvil($dir, $command, [...$input, ...$extra]);

        expect($code)->toBe(0, "$command failed:\n$output");

        $outputs[$command.' '.($input['name'] ?? json_encode($input))] = $output;
    }

    return $outputs;
}

/**
 * Relative paths of every file in a directory, sorted.
 *
 * @return list<string>
 */
function filesIn(string $dir): array
{
    $files = [];

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)) as $file) {
        $files[] = str_replace('\\', '/', substr($file->getPathname(), strlen($dir) + 1));
    }

    sort($files);

    return $files;
}

/**
 * Assert that every PHP file in the package passes Laravel Pint (the package's pint.json).
 */
function expectPintClean(string $dir): void
{
    $pint = dirname(__DIR__).'/vendor/bin/pint';

    exec(sprintf('%s --test --config=%s %s 2>&1', escapeshellarg($pint), escapeshellarg("$dir/pint.json"), escapeshellarg($dir)), $output, $code);

    expect($code)->toBe(0, implode("\n", $output));
}
