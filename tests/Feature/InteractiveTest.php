<?php

use Laravel\Prompts\Key;
use NyonCode\LaravelPackageConsole\Support\Lang;

/*
 * What a command asks when it is run in a terminal without everything it needs.
 * anvilPrompting() answers Laravel Prompts with key presses, anvilAnswering() answers the
 * plain questions used where prompts cannot be drawn.
 */

afterEach(fn () => Lang::setLocale(null));

describe('a missing name', function () {
    it('is asked for with an example', function () {
        $dir = initPackage();

        [$code, $output, $prompts] = anvilPrompting($dir, 'make:class', [], keys('Slugger', Key::ENTER));

        expect($code)->toBe(0, $output)
            ->and($prompts)->toContain('What should the class be named?')->toContain('E.g. PostService')
            ->and("$dir/src/Slugger.php")->toBeFile();
    });

    it('is validated while it is typed', function () {
        $dir = initPackage();

        [$code, $output, $prompts] = anvilPrompting($dir, 'make:class', [], keys(
            '1x', Key::ENTER, Key::BACKSPACE, Key::BACKSPACE, 'Slugger', Key::ENTER,
        ));

        expect($code)->toBe(0, $output)
            ->and($prompts)->toContain('The name must be a valid class name')
            ->and("$dir/src/Slugger.php")->toBeFile()
            ->and("$dir/src/1x.php")->not->toBeFile();
    });

    it('cannot be left empty', function () {
        $dir = initPackage();

        [$code, $output, $prompts] = anvilPrompting($dir, 'make:event', [], keys(Key::ENTER, 'PostPublished', Key::ENTER));

        expect($code)->toBe(0, $output)
            ->and($prompts)->toContain('Required.')
            ->and("$dir/src/Events/PostPublished.php")->toBeFile();
    });

    it('is not asked for when the run is not interactive', function () {
        [$code, $output] = anvil(initPackage(), 'make:class');

        expect($code)->toBe(1)->and($output)->toContain('Not enough arguments');
    });

    it('is asked for as a plain question where prompts cannot be drawn', function () {
        $dir = initPackage();

        [$code, $output] = anvilAnswering($dir, 'make:class', [], ['1x', 'Slugger']);

        expect($code)->toBe(0, $output)
            ->and($output)->toContain('What should the class be named?')->toContain('The name must be a valid class name')
            ->and("$dir/src/Slugger.php")->toBeFile();
    });
});

describe('follow-up questions', function () {
    it('offers the companions of a model', function () {
        $dir = initPackage();

        [$code, $output, $prompts] = anvilPrompting($dir, 'make:model', [], keys(
            'Post', Key::ENTER,
            Key::SPACE, Key::DOWN, Key::SPACE, Key::ENTER, // migration, factory
        ));

        expect($code)->toBe(0, $output)
            ->and($prompts)->toContain('Would you like any of the following?')->toContain('API resource controller')
            ->and("$dir/src/Models/Post.php")->toBeFile()
            ->and("$dir/database/migrations/create_posts_table.php")->toBeFile()
            ->and("$dir/database/factories/PostFactory.php")->toBeFile()
            ->and("$dir/database/seeders/PostSeeder.php")->not->toBeFile()
            ->and(contents($dir, 'src/Models/Post.php'))->toContain('HasFactory');
    });

    it('creates only the model when nothing is chosen', function () {
        $dir = initPackage();

        [$code, $output] = anvilPrompting($dir, 'make:model', [], keys('Post', Key::ENTER, Key::ENTER));

        expect($code)->toBe(0, $output)
            ->and("$dir/src/Models/Post.php")->toBeFile()
            ->and("$dir/database")->not->toBeDirectory();
    });

    it('does not ask when the name was given', function () {
        $dir = initPackage();

        [$code, $output, $prompts] = anvilPrompting($dir, 'make:model', ['name' => 'Post'], []);

        expect($code)->toBe(0, $output)
            ->and($prompts)->toBe('')
            ->and("$dir/src/Models/Post.php")->toBeFile();
    });

    it('does not ask about what the options already say', function () {
        $dir = initPackage();

        [$code, $output, $prompts] = anvilPrompting($dir, 'make:model', ['--migration' => true], keys('Post', Key::ENTER));

        expect($code)->toBe(0, $output)
            ->and($prompts)->not->toContain('Would you like')
            ->and("$dir/database/migrations/create_posts_table.php")->toBeFile();
    });

    it('offers the companions of a model as a plain choice', function () {
        $dir = initPackage();

        [$code, $output] = anvilAnswering($dir, 'make:model', [], ['Post', 'migration,policy']);

        expect($code)->toBe(0, $output)
            ->and("$dir/database/migrations/create_posts_table.php")->toBeFile()
            ->and("$dir/src/Policies/PostPolicy.php")->toBeFile();

        [$code, $output] = anvilAnswering($dir, 'make:model', [], ['Tag', '']);

        expect($code)->toBe(0, $output)
            ->and("$dir/src/Models/Tag.php")->toBeFile()
            ->and("$dir/src/Policies/TagPolicy.php")->not->toBeFile();
    });

    it('asks for the type of a controller and its model', function () {
        $dir = initPackage();
        generate($dir, 'make:model', ['name' => 'Post']);

        [$code, $output, $prompts] = anvilPrompting($dir, 'make:controller', [], keys(
            'PostController', Key::ENTER,
            Key::DOWN, Key::DOWN, Key::ENTER, // API resource
            'Po', Key::DOWN, Key::ENTER,      // the suggested model
        ));

        $controller = contents($dir, 'src/Http/Controllers/PostController.php');

        expect($code)->toBe(0, $output)
            ->and($prompts)->toContain('Which type of controller would you like?')->toContain('What model should this controller be for?')
            ->and($controller)->toContain('use Acme\Blog\Models\Post;')->toContain('public function store(')
            ->not->toContain('public function create(');
    });

    it('asks nothing more about an empty or invokable controller', function () {
        $dir = initPackage();

        [$code, $output, $prompts] = anvilPrompting($dir, 'make:controller', [], keys('PingController', Key::ENTER, Key::UP, Key::ENTER));

        expect($code)->toBe(0, $output)
            ->and($prompts)->not->toContain('What model')
            ->and(contents($dir, 'src/Http/Controllers/PingController.php'))->toContain('public function __invoke(');
    });

    it('suggests the events of the package to a listener and registers it', function (string $mode) {
        $dir = packageIn($mode);
        generate($dir, 'make:event', ['name' => 'PostPublished']);

        [$code, $output, $prompts] = anvilPrompting($dir, 'make:listener', [], keys(
            'NotifySubscribers', Key::ENTER,
            Key::DOWN, Key::ENTER,
        ));

        expect($code)->toBe(0, $output)
            ->and($prompts)->toContain('What event should be listened for?')->toContain('PostPublished')
            ->and(contents($dir, 'src/Listeners/NotifySubscribers.php'))->toContain('PostPublished $event')
            ->and(provider($dir))->toContain('NotifySubscribers::class');
    })->with('modes');

    it('checks the model and the event as they are submitted', function (string $command, string $name, string $message) {
        $dir = initPackage();

        [$code, $output, $prompts] = anvilPrompting($dir, $command, [], [
            ...keys($name, Key::ENTER),
            ...($command === 'make:controller' ? [Key::DOWN, Key::ENTER] : []),
            ...keys('Post Model', Key::ENTER),
            ...array_fill(0, 10, Key::BACKSPACE), ...keys('Post', Key::ENTER),
        ]);

        expect($code)->toBe(0, $output)->and($prompts)->toContain($message);
    })->with([
        ['make:controller', 'PostController', 'The model must be a class name'],
        ['make:observer', 'PostObserver', 'The model must be a class name'],
        ['make:policy', 'PostPolicy', 'The model must be a class name'],
        ['make:listener', 'NotifySubscribers', 'The event must be a class name'],
    ]);

    it('lets a listener go without an event', function () {
        $dir = initPackage();

        [$code, $output] = anvilPrompting($dir, 'make:listener', [], keys('LogSomething', Key::ENTER, Key::ENTER));

        expect($code)->toBe(0, $output)
            ->and(contents($dir, 'src/Listeners/LogSomething.php'))->toContain('object $event');
    });

    it('asks which model an observer and a policy apply to', function (string $command, string $name, string $path) {
        $dir = initPackage();
        generate($dir, 'make:model', ['name' => 'Post']);

        [$code, $output] = anvilPrompting($dir, $command, [], keys($name, Key::ENTER, 'Post', Key::ENTER));

        expect($code)->toBe(0, $output)
            ->and(contents($dir, $path))->toContain('use Acme\Blog\Models\Post;');
    })->with([
        ['make:observer', 'PostObserver', 'src/Observers/PostObserver.php'],
        ['make:policy', 'PostPolicy', 'src/Policies/PostPolicy.php'],
    ]);

    it('asks for the suite and the framework of a test', function () {
        $dir = initPackage();

        [$code, $output, $prompts] = anvilPrompting($dir, 'make:test', [], keys(
            'Slug', Key::ENTER,
            Key::DOWN, Key::ENTER, // unit
            Key::DOWN, Key::ENTER, // PHPUnit
        ));

        expect($code)->toBe(0, $output)
            ->and($prompts)->toContain('Which type of test would you like?')->toContain('Which test framework should it use?')
            ->and(contents($dir, 'tests/Unit/SlugTest.php'))->toContain('extends TestCase');
    });
});

describe('package:init', function () {
    it('asks for everything, shows a summary and waits for a yes', function () {
        $dir = tempPackage();

        [$code, $output, $prompts] = anvilPrompting($dir, 'package:init', ['--author' => 'Jane Doe', '--email' => 'jane@example.com'], [
            ...array_fill(0, 30, Key::BACKSPACE), ...keys('acme/laravel-shop', Key::ENTER),
            Key::ENTER,                         // description
            Key::ENTER,                         // namespace
            Key::DOWN, Key::ENTER,              // plain provider
            Key::SPACE, Key::ENTER,             // without Laravel 12
            Key::ENTER,                         // confirm
        ]);

        $composer = composerJson($dir);

        expect($code)->toBe(0, $output)
            ->and($prompts)->toContain('Composer package name')
            ->toContain('Which service provider should the package have?')
            ->toContain('Which Laravel versions should the package support?')
            ->toContain('Create the package with these settings?')
            ->not->toContain('Author name')
            ->and($output)->toContain('Acme\Shop')->toContain('Jane Doe <jane@example.com>')->toContain('plain Laravel ServiceProvider')
            ->and($composer['name'])->toBe('acme/laravel-shop')
            ->and($composer['require'])->not->toHaveKey('nyoncode/laravel-package-toolkit')
            ->and($composer['require']['illuminate/support'])->toBe('^13.0')
            ->and(contents($dir, 'src/ShopServiceProvider.php'))->toContain('extends ServiceProvider');
    });

    it('writes nothing when the summary is declined', function () {
        $dir = tempPackage();

        [$code, $output] = anvilPrompting($dir, 'package:init', ['--name' => 'acme/laravel-blog'], keys(
            Key::ENTER, Key::ENTER, Key::ENTER, Key::ENTER, Key::ENTER, Key::ENTER,
            'n', Key::ENTER,
        ));

        expect($code)->toBe(1)
            ->and($output)->toContain('Nothing was written.')
            ->and(filesIn($dir))->toBe([]);
    });

    it('goes back to the previous question with Ctrl+U', function () {
        $dir = tempPackage();

        [$code, $output] = anvilPrompting($dir, 'package:init', ['--name' => 'acme/laravel-blog'], [
            ...keys('Oops', Key::ENTER),         // description
            Key::CTRL_U,                         // back from the namespace
            ...array_fill(0, 30, Key::BACKSPACE), ...keys('A blog.', Key::ENTER),
            Key::ENTER, Key::ENTER, Key::ENTER, Key::ENTER, Key::ENTER, Key::ENTER,
        ]);

        expect($code)->toBe(0, $output)
            ->and(composerJson($dir)['description'])->toBe('A blog.');
    });

    it('does not ask about the provider of a package that has one', function () {
        $dir = spatiePackage(init: false);

        // Name, description, author, email, Laravel versions, confirmation.
        [$code, $output, $prompts] = anvilPrompting($dir, 'package:init', [], keys(
            Key::ENTER, Key::ENTER, Key::ENTER, Key::ENTER, Key::ENTER, Key::ENTER,
        ));

        expect($code)->toBe(0, $output)
            ->and($prompts)->not->toContain('Which service provider')->not->toContain('Root namespace')
            ->toContain('Complete the package with these settings?')
            ->and($output)->toMatch('/Service provider\s+spatie\/laravel-package-tools/')
            ->toMatch('/Namespace\s+Acme\\\\Blog/');
    });

    describe('in a package that has composer.json', function () {
        /**
         * An existing package: a toolkit one for Laravel 13, by an author with a homepage.
         */
        function existingPackage(): string
        {
            $dir = initPackage(options: ['--laravel' => '13', '--description' => 'Old description.']);
            $composer = composerJson($dir);
            $composer['authors'] = [['name' => 'Old Author', 'email' => 'old@example.com', 'homepage' => 'https://example.com']];
            $composer['require']['php'] = '^8.4';
            file_put_contents("$dir/composer.json", json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");

            return $dir;
        }

        /**
         * Key presses that replace the text of a prefilled prompt.
         *
         * @return list<string>
         */
        function retype(string $text): array
        {
            return [...array_fill(0, 40, Key::BACKSPACE), ...keys($text, Key::ENTER)];
        }

        it('prefills every question from composer.json', function () {
            $dir = existingPackage();

            [$code, $output, $prompts] = anvilPrompting($dir, 'package:init', [], keys(
                Key::ENTER, Key::ENTER, Key::ENTER, Key::ENTER, Key::ENTER, Key::ENTER,
            ));

            expect($code)->toBe(0, $output)
                ->and($prompts)->toContain('acme/laravel-blog')->toContain('Old description.')
                ->toContain('Old Author')->toContain('old@example.com')
                ->toContain('◻ Laravel 12')->toContain('◼ Laravel 13')
                ->not->toContain('Root namespace')->not->toContain('Which service provider')
                ->and($output)->toMatch('/Laravel\s+13\n/')->not->toContain('→');
        });

        it('writes what the answers change and shows it in the summary', function () {
            $dir = existingPackage();
            $before = composerJson($dir);

            [$code, $output] = anvilPrompting($dir, 'package:init', [], [
                Key::ENTER,                        // name
                ...retype('New description.'),
                ...retype('New Author'),
                ...retype('new@example.com'),
                Key::SPACE, Key::ENTER,            // add Laravel 12
                Key::ENTER,                        // confirm
            ]);

            $composer = composerJson($dir);

            expect($code)->toBe(0, $output)
                ->and($output)->toContain('Old description. → New description.')
                ->toContain('Old Author <old@example.com> → New Author <new@example.com>')
                ->toContain('13 → 12, 13')
                ->and($composer['description'])->toBe('New description.')
                ->and($composer['authors'])->toBe([['name' => 'New Author', 'email' => 'new@example.com', 'homepage' => 'https://example.com']])
                ->and($composer['require']['illuminate/support'])->toBe('^12.0|^13.0')
                ->and($composer['require']['php'])->toBe('^8.4')
                ->and($composer['require-dev']['orchestra/testbench'])->toBe('^10.0|^11.0')
                ->and($composer['require-dev']['pestphp/pest'])->toBe('^3.8|^4.0')
                ->and(array_keys($composer))->toBe(array_keys($before));
        });

        it('raises PHP when a Laravel version is dropped, and warns about the CI matrix', function () {
            $dir = initPackage(options: ['--laravel' => '12,13']);

            [$code, $output] = anvilPrompting($dir, 'package:init', [], keys(
                Key::ENTER, Key::ENTER, Key::ENTER, Key::ENTER,
                Key::SPACE, Key::ENTER,            // drop Laravel 12
                Key::ENTER,
            ));

            $composer = composerJson($dir);

            expect($code)->toBe(0, $output)
                ->and($composer['require']['illuminate/support'])->toBe('^13.0')
                ->and($composer['require']['php'])->toBe('^8.3')
                ->and($composer['require-dev']['orchestra/testbench'])->toBe('^11.0')
                ->and($composer['require-dev']['pestphp/pest-plugin-laravel'])->toBe('^4.0')
                ->and($output)->toContain('WARN The CI matrix in .github/workflows/tests.yml does not match the new Laravel versions.')
                ->and(contents($dir, '.github/workflows/tests.yml'))->toContain("'12.*'");
        });

        it('changes nothing when every answer is kept', function () {
            $dir = existingPackage();
            $files = array_map(fn (string $file) => file_get_contents("$dir/$file"), array_combine(filesIn($dir), filesIn($dir)));

            [$code, $output] = anvilPrompting($dir, 'package:init', [], keys(
                Key::ENTER, Key::ENTER, Key::ENTER, Key::ENTER, Key::ENTER, Key::ENTER,
            ));

            expect($code)->toBe(0, $output)
                ->and($output)->not->toContain('WARN')
                ->and(array_map(fn (string $file) => file_get_contents("$dir/$file"), array_combine(filesIn($dir), filesIn($dir))))->toBe($files);
        });

        it('only fills in what is missing when not interactive', function () {
            $dir = existingPackage();
            $before = file_get_contents("$dir/composer.json");

            [$code, $output] = anvil($dir, 'package:init');

            expect($code)->toBe(0, $output)->and(file_get_contents("$dir/composer.json"))->toBe($before);
        });

        it('changes what an option says when not interactive', function () {
            $dir = existingPackage();

            [$code, $output] = anvil($dir, 'package:init', ['--description' => 'Given.', '--author' => 'Given Author', '--laravel' => '12,13']);
            $composer = composerJson($dir);

            expect($code)->toBe(0, $output)
                ->and($composer['description'])->toBe('Given.')
                ->and($composer['authors'][0])->toBe(['name' => 'Given Author', 'email' => 'old@example.com', 'homepage' => 'https://example.com'])
                ->and($composer['require']['illuminate/support'])->toBe('^12.0|^13.0');
        });

        it('keeps the namespace and says so when --namespace asks for another', function () {
            $dir = existingPackage();

            [$code, $output] = anvil($dir, 'package:init', ['--namespace' => 'Acme\\Weblog']);

            expect($code)->toBe(0, $output)
                ->and($output)->toContain('WARN Kept the namespace Acme\\Blog from composer.json')
                ->and(composerJson($dir)['autoload']['psr-4'])->toHaveKey('Acme\\Blog\\')->not->toHaveKey('Acme\\Weblog\\');
        });

        it('fills in an author composer.json lacks, but not the email of one it has', function () {
            $dir = tempPackage(['name' => 'acme/laravel-blog', 'authors' => [['name' => 'Jane Doe']], 'autoload' => ['psr-4' => ['Acme\\Blog\\' => 'src/']]]);

            [$code, $output] = anvil($dir, 'package:init', ['--toolkit' => true]);

            expect($code)->toBe(0, $output)->and(composerJson($dir)['authors'])->toBe([['name' => 'Jane Doe']]);
        });
    });

    it('asks nothing when the run is not interactive', function () {
        $dir = tempPackage();

        [$code, $output] = anvil($dir, 'package:init', ['--name' => 'acme/laravel-blog']);

        expect($code)->toBe(0, $output)
            ->and($output)->not->toContain('with these settings')
            ->and(composerJson($dir)['require'])->toHaveKey('nyoncode/laravel-package-toolkit');
    });

    it('guesses a namespace PHP accepts for a name that starts with a digit', function () {
        $dir = tempPackage();

        [$code, $output] = anvil($dir, 'package:init', ['--name' => '9lives/3d-tools']);

        expect($code)->toBe(0, $output)
            ->and(composerJson($dir)['autoload']['psr-4'])->toHaveKey('Package9lives\\Package3dTools\\');
    });

    it('rejects a namespace that is not one', function () {
        [$code, $output] = anvil(tempPackage(), 'package:init', ['--name' => 'acme/laravel-blog', '--namespace' => 'Acme Blog']);

        expect($code)->toBe(1)->and($output)->toContain('The namespace must be a valid PHP namespace');
    });
});

describe('package:doctor --fix', function () {
    it('asks which findings to fix and leaves the others', function () {
        $dir = initPackage(toolkit: false);
        generate($dir, 'make:route', ['name' => 'web']);
        generate($dir, 'make:view', ['name' => 'posts.index']);

        file_put_contents("$dir/src/BlogServiceProvider.php", preg_replace(
            '/^\s+\$this->(loadRoutesFrom|loadViewsFrom|publishes\(\[\s*__DIR__\.\'\/\.\.\/resources\/views)[^;]*;\n/m',
            '',
            provider($dir),
        ));

        [, $before] = anvil($dir, 'package:doctor');

        expect($before)->toContain('resources/views is not registered')->toContain('routes/web.php is not registered');

        // Everything is preselected: untick the first finding (the routes).
        [$code, $output, $prompts] = anvilPrompting($dir, 'package:doctor', ['--fix' => true], keys(Key::SPACE, Key::ENTER));

        expect($prompts)->toContain('Which of these should be fixed?')
            ->and($output)->toContain('FIXED resources/views')
            ->toContain('ERROR routes/web.php is not registered')
            ->and($code)->toBe(1)
            ->and(provider($dir))->toContain('loadViewsFrom')->not->toContain('loadRoutesFrom');

        expectValidPhp("$dir/src/BlogServiceProvider.php");
    });

    it('fixes everything without asking when the run is not interactive', function () {
        $dir = initPackage(toolkit: false);
        generate($dir, 'make:route', ['name' => 'web']);
        file_put_contents("$dir/src/BlogServiceProvider.php", preg_replace('/^\s+\$this->loadRoutesFrom[^;]*;\n/m', '', provider($dir)));

        [$code, $output] = anvil($dir, 'package:doctor', ['--fix' => true]);

        expect($code)->toBe(0, $output)->and(provider($dir))->toContain('loadRoutesFrom');
    });
});

describe('the language of the questions', function () {
    it('follows ANVIL_LANG and falls back to English', function (string $value, string $locale) {
        putenv("ANVIL_LANG=$value");

        try {
            Lang::setLocale(null);

            expect(Lang::locale())->toBe($locale);
        } finally {
            putenv('ANVIL_LANG=en');
        }
    })->with([
        ['cs', 'cs'],
        ['cs_CZ.UTF-8', 'cs'],
        ['CS', 'cs'],
        ['xx', 'en'],
        ['C', 'en'],
        ['../../etc/passwd', 'en'],
    ]);

    it('asks in Czech and keeps the output in English', function () {
        Lang::setLocale('cs');
        $dir = initPackage();

        [$code, $output, $prompts] = anvilPrompting($dir, 'make:model', [], keys('1', Key::ENTER, Key::BACKSPACE, 'Post', Key::ENTER, Key::ENTER));

        expect($code)->toBe(0, $output)
            ->and($prompts)->toContain('Jak se má model jmenovat?')
            ->toContain('Např. Post')
            ->toContain('Název musí být platný název třídy')
            ->toContain('Chcete vytvořit i něco z následujícího?')
            ->toContain('Mezerník vybírá, Enter potvrzuje.')
            ->and($output)->toContain('Model [src/Models/Post.php] created successfully.');
    });

    it('has a translation of every question in every language', function (string $locale) {
        $lines = require dirname(__DIR__, 2)."/lang/$locale.php";
        $source = '';

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 2).'/src', FilesystemIterator::SKIP_DOTS)) as $file) {
            $source .= file_get_contents($file->getPathname());
        }

        // Literal lines, and the validation messages a prompt may show.
        preg_match_all('/(?:->trans|anvil_trans)\(\s*\'((?:[^\'\\\\]|\\\\.)+)\'/', $source, $literal);
        preg_match_all('/message: \'(The name (?:argument|must|may)(?:[^\'\\\\]|\\\\.)*)\'/', $source, $messages);
        preg_match_all('/\? null\s*: \'((?:[^\'\\\\]|\\\\.)+)\'/', $source, $errors);
        preg_match_all('/classOptionRule\(\'((?:[^\'\\\\]|\\\\.)+)\'/', $source, $options);
        preg_match_all('/string \$type = \'([^\']+)\'/', $source, $types);

        $expected = array_unique([
            ...array_map(fn (string $line) => str_replace(['\\\\', "\\'"], ['\\', "'"], $line), [...$literal[1], ...$messages[1], ...$errors[1], ...$options[1]]),
            ...array_map('strtolower', array_diff($types[1], ['File', 'Config', 'Routes', 'Translation'])),
        ]);

        expect($expected)->not->toBeEmpty()
            ->and(array_values(array_diff($expected, array_keys($lines))))->toBe([])
            ->and(array_filter($lines, fn (string $line) => trim($line) === ''))->toBe([]);
    })->with(fn () => array_diff(Lang::available(), ['en']));
});
