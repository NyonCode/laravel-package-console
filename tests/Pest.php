<?php

use Laravel\Prompts\Prompt;
use NyonCode\LaravelPackageConsole\Kernel;
use NyonCode\LaravelPackageConsole\Support\Prompts;
use Symfony\Component\Console\Tester\ApplicationTester;

// The questions are asked in English whatever the language of the machine running the tests.
putenv('ANVIL_LANG=en');

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
 * Run a command in a terminal, answering its prompts with key presses.
 *
 * @param  array<string, mixed>  $input
 * @param  list<string>  $keys  See keys().
 * @return array{0: int, 1: string, 2: string} [exit code, output, what the prompts rendered]
 */
function anvilPrompting(string $dir, string $command, array $input, array $keys): array
{
    $kernel = new Kernel($dir);
    $kernel->application->setAutoExit(false);
    $tester = new ApplicationTester($kernel->application);

    Prompts::fake($keys);

    // Without this a prompt that is asked more than the test answers would wait forever.
    Prompt::terminal()->shouldReceive('read')->andThrow(new RuntimeException('The command asked for more key presses than the test gave it.'));

    try {
        $code = $tester->run(['command' => $command, ...$input], ['interactive' => true, 'decorated' => false]);

        // Every key press must have been read: a test with keys to spare tests less than it says.
        Prompt::terminal()->mockery_verify();
    } finally {
        Prompts::stopFaking();
    }

    return [$code, $tester->getDisplay(), Prompt::strippedContent()];
}

/**
 * Run a command where questions can be answered but prompts cannot be drawn (piped input,
 * Windows): the answers are lines of text.
 *
 * @param  array<string, mixed>  $input
 * @param  list<string>  $answers
 * @return array{0: int, 1: string} [exit code, output]
 */
function anvilAnswering(string $dir, string $command, array $input, array $answers): array
{
    $kernel = new Kernel($dir);
    $kernel->application->setAutoExit(false);
    $tester = new ApplicationTester($kernel->application);
    $tester->setInputs($answers);

    $code = $tester->run(['command' => $command, ...$input], ['interactive' => true, 'decorated' => false]);

    return [$code, $tester->getDisplay()];
}

/**
 * Key presses for anvilPrompting(): text is typed letter by letter, a Key constant is one press.
 *
 * @return list<string>
 */
function keys(string ...$parts): array
{
    $keys = [];

    foreach ($parts as $part) {
        array_push($keys, ...(preg_match('/^[\x20-\x7e]{2,}$/', $part) ? str_split($part) : [$part]));
    }

    return $keys;
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

/**
 * An existing package built on spatie/laravel-package-tools (the layout of Spatie's package
 * skeleton), completed with package:init.
 *
 * @param  bool  $listed  Whether composer.json lists the provider in extra.laravel.providers.
 */
function spatiePackage(bool $listed = true, string $name = 'laravel-blog', bool $init = true, array $options = []): string
{
    $dir = tempPackage(array_filter([
        'name' => 'acme/laravel-blog',
        'require' => ['php' => '^8.2', 'spatie/laravel-package-tools' => '^1.16'],
        'autoload' => ['psr-4' => ['Acme\\Blog\\' => 'src/']],
        'extra' => $listed ? ['laravel' => ['providers' => ['Acme\\Blog\\BlogServiceProvider']]] : null,
    ]));

    mkdir("$dir/src");
    file_put_contents("$dir/src/BlogServiceProvider.php", <<<PHP
<?php

namespace Acme\Blog;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class BlogServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package \$package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        \$package
            ->name('$name');
    }
}

PHP);

    if ($init) {
        [$code, $output] = anvil($dir, 'package:init', ['--author' => 'Jane Doe', '--email' => 'jane@example.com', ...$options]);

        expect($code)->toBe(0, $output);
    }

    return $dir;
}

dataset('modes', ['toolkit', 'plain', 'spatie']);

/**
 * A new package with the toolkit, a new plain one, or an existing spatie/laravel-package-tools one.
 */
function packageIn(string $mode): string
{
    return match ($mode) {
        'toolkit' => initPackage(),
        'plain' => initPackage(toolkit: false),
        'spatie' => spatiePackage(),
    };
}

/**
 * Run a command that must succeed and return its output.
 */
function generate(string $dir, string $command, array $input = []): string
{
    [$code, $output] = anvil($dir, $command, $input);

    expect($code)->toBe(0, $output);

    return $output;
}

function contents(string $dir, string $path): string
{
    expect("$dir/$path")->toBeFile();

    return (string) file_get_contents("$dir/$path");
}

function provider(string $dir): string
{
    return contents($dir, 'src/BlogServiceProvider.php');
}

/**
 * Replace the body of configure() / boot() in the main provider.
 */
function rewriteProvider(string $dir, string $search, string $replace): void
{
    $path = "$dir/src/BlogServiceProvider.php";
    $code = file_get_contents($path);

    expect($code)->toContain($search);

    file_put_contents($path, str_replace($search, $replace, $code));
}

/**
 * Write a file of the package, creating its directory.
 */
function put(string $dir, string $path, string $contents = "<?php\n\nreturn [];\n"): void
{
    if (! is_dir(dirname("$dir/$path"))) {
        mkdir(dirname("$dir/$path"), 0755, true);
    }

    file_put_contents("$dir/$path", $contents);
}

/**
 * @return array{0: int, 1: string}
 */
function doctor(string $dir, array $options = []): array
{
    return anvil($dir, 'package:doctor', $options);
}
