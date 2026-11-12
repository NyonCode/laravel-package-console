<?php

/*
 * Packages with several languages: make:lang creates a file per locale, and package:doctor
 * compares the languages with one another and with the keys the code uses.
 */

/**
 * A package with registered translations and the given lang files.
 *
 * @param  array<string, string>  $files  Path below lang/ => contents.
 */
function translatedPackage(array $files, string $mode = 'toolkit'): string
{
    $dir = packageIn($mode);
    generate($dir, 'make:lang');
    $lang = $mode === 'spatie' ? 'resources/lang' : 'lang';
    unlink("$dir/$lang/en/messages.php");

    foreach ($files as $path => $contents) {
        if (! is_dir(dirname("$dir/$lang/$path"))) {
            mkdir(dirname("$dir/$lang/$path"), 0755, true);
        }

        file_put_contents("$dir/$lang/$path", $contents);
    }

    return $dir;
}

const EN_MESSAGES = <<<'PHP'
<?php

return [

    'title' => 'Posts',
    'empty' => 'No posts yet.', // shown on the index

    'actions' => [
        'publish' => 'Publish',
        'archive' => 'Archive',
    ],

    'statuses' => ['draft', 'published'],
    'count' => '{0} None|[1,*] :count posts',

];

PHP;

describe('make:lang', function () {
    it('creates a file for every locale of --locale and registers the translations once', function (string $mode) {
        $dir = packageIn($mode);
        $lang = $mode === 'spatie' ? 'resources/lang' : 'lang';

        $output = generate($dir, 'make:lang', ['name' => 'messages', '--locale' => 'cs, en,de']);

        expect("$dir/$lang/cs/messages.php")->toBeFile()
            ->and("$dir/$lang/en/messages.php")->toBeFile()
            ->and("$dir/$lang/de/messages.php")->toBeFile()
            ->and(substr_count($output, 'created successfully'))->toBe(3)
            ->and(substr_count($output, 'DONE'))->toBe(1)
            ->and(substr_count($output, 'Use it with'))->toBe(1);
    })->with('modes');

    it('creates a JSON file for every locale', function () {
        $dir = initPackage();

        generate($dir, 'make:lang', ['--json' => true, '--locale' => 'cs,de']);

        expect("$dir/lang/cs.json")->toBeFile()->and("$dir/lang/de.json")->toBeFile();
    });

    it('creates the locales that are missing and fails for the ones that exist', function () {
        $dir = initPackage();
        generate($dir, 'make:lang', ['--locale' => 'en']);

        [$code, $output] = anvil($dir, 'make:lang', ['--locale' => 'en,cs']);

        expect($code)->toBe(1)
            ->and($output)->toContain('Translation [lang/en/messages.php] already exists!')
            ->and("$dir/lang/cs/messages.php")->toBeFile();
    });

    it('rejects a list with a locale that is not a plain name, before writing anything', function () {
        $dir = initPackage();

        [$code, $output] = anvil($dir, 'make:lang', ['--locale' => 'cs,../x']);

        expect($code)->toBe(1)
            ->and($output)->toContain('The locale may only contain')
            ->and("$dir/lang")->not->toBeDirectory();
    });
});

describe('package:doctor and the languages of a package', function () {
    it('has nothing to say about languages with the same keys', function () {
        $dir = translatedPackage(['en/messages.php' => EN_MESSAGES, 'cs/messages.php' => EN_MESSAGES]);

        [$code, $output] = doctor($dir, ['--strict' => true]);

        expect($code)->toBe(0, $output);
    });

    it('warns about keys a language lacks', function () {
        $dir = translatedPackage([
            'en/messages.php' => EN_MESSAGES,
            'cs/messages.php' => "<?php\n\nreturn [\n    'title' => 'Příspěvky',\n    'actions' => [\n        'publish' => 'Publikovat',\n    ],\n    'only_cs' => 'Jen česky',\n];\n",
        ]);

        [$code, $output] = doctor($dir);

        expect($code)->toBe(0, $output)
            ->and($output)->toContain('WARN  lang/cs/messages.php has no translation for 4 keys that en has: empty, actions.archive, statuses, count.')
            ->toContain('WARN  lang/en/messages.php has no translation for a key that cs has: only_cs.')
            ->toContain('2 warnings. Run package:doctor --fix to fix 2 of them.');

        expect(doctor($dir, ['--strict' => true])[0])->toBe(1);
    });

    it('adds the missing keys in the formatting of the file, with the text to translate', function () {
        $dir = translatedPackage([
            'en/messages.php' => EN_MESSAGES,
            'cs/messages.php' => "<?php\n\nreturn [\n    'title' => 'Příspěvky',\n    'actions' => [\n        'publish' => 'Publikovat', // hlavní akce\n    ],\n    'only_cs' => 'Jen česky'\n];\n",
        ]);

        [$code, $output] = doctor($dir, ['--fix' => true, '--strict' => true]);

        expect($code)->toBe(0, $output)
            ->and($output)->toContain('FIXED lang/cs/messages.php: added 4 missing keys with the text from en, to be translated.')
            ->and(contents($dir, 'lang/cs/messages.php'))->toBe(<<<'PHP'
            <?php

            return [
                'title' => 'Příspěvky',
                'actions' => [
                    'publish' => 'Publikovat', // hlavní akce
                    'archive' => 'Archive',
                ],
                'only_cs' => 'Jen česky',
                'empty' => 'No posts yet.',
                'statuses' => ['draft', 'published'],
                'count' => '{0} None|[1,*] :count posts',
            ];

            PHP)
            ->and(contents($dir, 'lang/en/messages.php'))->toContain("    'count' => '{0} None|[1,*] :count posts',\n    'only_cs' => 'Jen česky',\n\n];");

        expect(require "$dir/lang/cs/messages.php")->toHaveKeys(['title', 'actions.archive', 'statuses', 'count', 'only_cs']);
        expectPintClean($dir);

        // A second run finds nothing to add.
        $before = contents($dir, 'lang/cs/messages.php');
        doctor($dir, ['--fix' => true]);

        expect(contents($dir, 'lang/cs/messages.php'))->toBe($before);
    });

    it('creates a file a language does not have at all', function () {
        $dir = translatedPackage([
            'en/messages.php' => EN_MESSAGES,
            'en/admin/menu.php' => "<?php\n\nreturn ['home' => 'Home', 'nested' => ['deep' => ['er' => 'Deeper']]];\n",
            'cs/messages.php' => EN_MESSAGES,
        ]);

        [, $output] = doctor($dir);

        expect($output)->toContain('WARN  lang/cs/admin/menu.php does not exist, but en has that file (2 keys).');

        doctor($dir, ['--fix' => true]);

        expect(require "$dir/lang/cs/admin/menu.php")->toBe(['home' => 'Home', 'nested' => ['deep' => ['er' => 'Deeper']]])
            ->and(doctor($dir, ['--strict' => true])[0])->toBe(0);
    });

    it('fills the empty file make:lang creates', function () {
        $dir = translatedPackage(['en/messages.php' => EN_MESSAGES]);
        generate($dir, 'make:lang', ['--locale' => 'cs']);

        doctor($dir, ['--fix' => true]);

        expect(require "$dir/lang/cs/messages.php")->toBe(require "$dir/lang/en/messages.php");
        expectPintClean($dir);
    });

    it('compares JSON translations', function () {
        $dir = translatedPackage([
            'en.json' => json_encode(['Save' => 'Save', 'Cancel' => 'Cancel']),
            'cs.json' => "{\n    \"Save\": \"Uložit\"\n}\n",
            'de.json' => "{\n}\n",
        ]);

        [, $output] = doctor($dir);

        expect($output)->toContain('WARN  lang/cs.json has no translation for a key that en has: Cancel.')
            ->toContain('WARN  lang/de.json has no translation for 2 keys that en has: Save, Cancel.');

        doctor($dir, ['--fix' => true]);

        expect(contents($dir, 'lang/cs.json'))->toBe("{\n    \"Save\": \"Uložit\",\n    \"Cancel\": \"Cancel\"\n}\n")
            ->and(json_decode(contents($dir, 'lang/de.json'), true))->toBe(['Save' => 'Save', 'Cancel' => 'Cancel'])
            ->and(doctor($dir, ['--strict' => true])[0])->toBe(0);
    });

    it('leaves alone what it cannot read or compare', function () {
        $dir = translatedPackage([
            'en/messages.php' => "<?php\n\nreturn ['title' => 'Posts', 'actions' => ['publish' => 'Publish']];\n",
            // A string where English has an array, and a file that builds its array.
            'cs/messages.php' => "<?php\n\nreturn ['title' => 'Příspěvky', 'actions' => 'Akce'];\n",
            'de/messages.php' => "<?php\n\n\$lines = ['title' => 'Beiträge'];\n\nreturn \$lines;\n",
            'vendor/other/en/messages.php' => "<?php\n\nreturn ['x' => 'y'];\n",
        ]);

        [$code, $output] = doctor($dir, ['--strict' => true]);

        expect($code)->toBe(0, $output);
    });

    it('reads resources/lang in a spatie package', function () {
        $dir = translatedPackage([
            'en/messages.php' => "<?php\n\nreturn ['title' => 'Posts'];\n",
            'cs/messages.php' => "<?php\n\nreturn [];\n",
        ], 'spatie');

        expect(doctor($dir)[1])->toContain('WARN  resources/lang/cs/messages.php has no translation for a key that en has: title.');
    });

    it('warns about keys the code uses that no language defines', function () {
        $dir = translatedPackage(['en/messages.php' => EN_MESSAGES]);

        put($dir, 'src/Http/Controllers/PostController.php', <<<'PHP'
        <?php

        namespace Acme\Blog\Http\Controllers;

        class PostController
        {
            public function index(): string
            {
                return __('blog::messages.title').trans('blog::messages.actions.publish').trans_choice('blog::messages.count', 2)
                    .__('blog::messages.actions').__('blog::messages.missing').__("blog::errors.not_found")
                    .__('blog::messages.'.$this->key()).__('other::messages.nope').__('Just a sentence.');
            }
        }

        PHP);
        put($dir, 'resources/views/posts/index.blade.php', "<h1>@lang('blog::messages.title')</h1>\n<p>{{ __('blog::messages.subtitle') }}</p>\n");
        generate($dir, 'make:view', ['name' => 'posts.show']);

        [$code, $output] = doctor($dir);

        expect($code)->toBe(0, $output)
            ->and($output)->toContain('WARN  blog::messages.missing is used in src/Http/Controllers/PostController.php, but no translation file defines it.')
            ->toContain('WARN  blog::errors.not_found is used in src/Http/Controllers/PostController.php')
            ->toContain('WARN  blog::messages.subtitle is used in resources/views/posts/index.blade.php')
            ->toContain('3 warnings.')
            ->not->toContain('--fix');
    });
});
