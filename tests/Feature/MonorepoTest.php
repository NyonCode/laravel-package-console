<?php

use Laravel\Prompts\Key;

/**
 * Output with the error blocks unwrapped: one space between words.
 */
function flat(string $output): string
{
    return (string) preg_replace('/\s+/', ' ', $output);
}

/**
 * A command class the provider of the shop package does not register.
 */
function unregisteredCommand(string $root): void
{
    put($root, 'packages/shop/src/Commands/Prune.php', "<?php\n\nnamespace Acme\\Shop\\Commands;\n\nuse Illuminate\\Console\\Command;\n\nclass Prune extends Command\n{\n}\n");
}

/**
 * A monorepo whose composer.json has the given path repositories, with a package created by
 * package:init in each of the given directories ("packages/blog" => "acme/laravel-blog").
 *
 * @param  array<string, string>  $packages
 */
function monorepo(array $packages = ['packages/blog' => 'acme/laravel-blog', 'packages/shop' => 'acme/laravel-shop'], ?array $repositories = [['type' => 'path', 'url' => 'packages/*']]): string
{
    $root = tempPackage(array_filter(['name' => 'acme/monorepo', 'repositories' => $repositories]));

    foreach ($packages as $directory => $name) {
        generate($root, 'package:init', ['--package' => $directory, '--name' => $name, '--author' => 'Jane Doe', '--email' => 'jane@example.com', '--toolkit' => true]);
    }

    return $root;
}

describe('package:init in a monorepo', function () {
    it('creates the package in --package and makes the root install it', function () {
        $root = tempPackage(['name' => 'acme/monorepo', 'require' => (object) []]);

        $output = generate($root, 'package:init', ['--package' => 'packages/blog', '--name' => 'acme/laravel-blog', '--toolkit' => true]);

        expect("$root/packages/blog/composer.json")->toBeFile()
            ->and("$root/packages/blog/src/BlogServiceProvider.php")->toBeFile()
            ->and("$root/anvil")->toBeFile()
            ->and($output)->toContain('UPDATE ../../composer.json (path repository packages/blog, require-dev acme/laravel-blog, require-dev nyoncode/laravel-package-console)')
            ->and($output)->toContain('CREATE ../../anvil')
            ->and($output)->toContain('--package=packages/blog')
            ->and(composerJson($root))->toBe([
                'name' => 'acme/monorepo',
                'require' => [],
                'require-dev' => ['acme/laravel-blog' => '@dev', 'nyoncode/laravel-package-console' => '^1.0'],
                'repositories' => [['type' => 'path', 'url' => 'packages/blog']],
            ]);
    });

    it('adds no repository a glob already covers, and changes nothing the second time', function () {
        $root = monorepo(['packages/blog' => 'acme/laravel-blog']);
        $composer = file_get_contents("$root/composer.json");

        expect(composerJson($root)['repositories'])->toBe([['type' => 'path', 'url' => 'packages/*']]);

        $output = generate($root, 'package:init', ['--package' => 'blog']);

        expect($output)->not->toContain('UPDATE ../../composer.json')
            ->and($output)->not->toContain('CREATE ../../anvil')
            ->and(file_get_contents("$root/composer.json"))->toBe($composer);
    });

    it('registers a package created in an empty directory of the monorepo', function () {
        $root = monorepo([]);
        mkdir("$root/packages/blog", 0755, true);

        $output = generate("$root/packages/blog", 'package:init', ['--name' => 'acme/laravel-blog']);

        expect("$root/packages/blog/composer.json")->toBeFile()
            ->and($output)->toContain('UPDATE ../../composer.json (require-dev acme/laravel-blog, require-dev nyoncode/laravel-package-console)');
    });

    it('does not touch a path repository outside the package', function () {
        $dir = tempPackage(['name' => 'acme/laravel-blog', 'repositories' => [['type' => 'path', 'url' => '../toolkit']]]);

        $output = generate($dir, 'package:init');

        expect($output)->not->toContain('../composer.json')
            ->and(composerJson($dir)['repositories'])->toBe([['type' => 'path', 'url' => '../toolkit']])
            ->and("$dir/src/BlogServiceProvider.php")->toBeFile();
    });

    it('asks for the directory in the root of a monorepo', function () {
        $root = monorepo();

        [$code, $output, $prompts] = anvilPrompting($root, 'package:init', [
            '--name' => 'acme/laravel-forum', '--description' => 'Forum', '--namespace' => 'Acme\Forum',
            '--author' => 'Jane Doe', '--email' => 'jane@example.com', '--toolkit' => true, '--laravel' => '13',
        ], keys('packages/forum', Key::ENTER, Key::ENTER));

        expect($code)->toBe(0, $output)
            ->and($prompts)->toContain('In which directory of the monorepo should the package be?')
            ->and("$root/packages/forum/src/ForumServiceProvider.php")->toBeFile();
    });

    it('needs --package in the root of a monorepo when it cannot ask', function () {
        [$code, $output] = anvil(monorepo(), 'package:init');

        expect($code)->toBe(1)
            ->and(flat($output))->toContain('The monorepo has 2 packages, choose one with --package: packages/blog, packages/shop.');
    });
});

describe('choosing the package', function () {
    it('works on the package --package names', function (string $selector) {
        $root = monorepo();

        generate($root, 'make:model', ['name' => 'Post', '--package' => $selector]);

        expect("$root/packages/shop/src/Models/Post.php")->toBeFile()
            ->and("$root/packages/blog/src/Models/Post.php")->not->toBeFile();
    })->with([
        'directory' => 'packages/shop',
        'directory name' => 'shop',
        'Composer name' => 'acme/laravel-shop',
        'directory with ./' => './packages/shop/',
    ]);

    it('takes the package from ANVIL_PACKAGE', function () {
        $root = monorepo();
        putenv('ANVIL_PACKAGE=shop');

        try {
            generate($root, 'make:event', ['name' => 'Paid']);
        } finally {
            putenv('ANVIL_PACKAGE');
        }

        expect("$root/packages/shop/src/Events/Paid.php")->toBeFile();
    });

    it('fails for a package the monorepo does not have', function () {
        [$code, $output] = anvil(monorepo(), 'make:model', ['name' => 'Post', '--package' => 'forum']);

        expect($code)->toBe(1)
            ->and(flat($output))->toContain('No package "forum" found. The packages of the monorepo: packages/blog, packages/shop.');
    });

    it('fails for a name several packages match', function () {
        $root = monorepo(['packages/blog' => 'acme/laravel-blog', 'modules/blog' => 'acme/blog-module'], [
            ['type' => 'path', 'url' => 'packages/*'],
            ['type' => 'path', 'url' => 'modules/*'],
        ]);

        [$code, $output] = anvil($root, 'make:model', ['name' => 'Post', '--package' => 'blog']);

        expect($code)->toBe(1)
            ->and(flat($output))->toContain('"blog" matches several packages: modules/blog, packages/blog. Use the directory.');
    });

    it('uses the only package of the monorepo', function () {
        $root = monorepo(['packages/blog' => 'acme/laravel-blog']);

        generate($root, 'make:model', ['name' => 'Post']);

        expect("$root/packages/blog/src/Models/Post.php")->toBeFile();
    });

    it('fails in the root of a monorepo with several packages when it cannot ask', function () {
        $root = monorepo();

        [$code, $output] = anvil($root, 'make:model', ['name' => 'Post']);

        expect($code)->toBe(1)
            ->and(flat($output))->toContain('choose one with --package: packages/blog, packages/shop.')
            ->and(filesIn("$root/packages/blog/src"))->not->toContain('Models/Post.php');
    });

    it('asks which package in the root of a monorepo with several packages', function () {
        $root = monorepo();

        [$code, $output, $prompts] = anvilPrompting($root, 'make:model', [], keys(Key::DOWN, Key::ENTER, 'Post', Key::ENTER, Key::ENTER));

        expect($code)->toBe(0, $output)
            ->and($prompts)->toContain('Which package of the monorepo?')
            ->and($prompts)->toContain('packages/shop (acme/laravel-shop)')
            ->and("$root/packages/shop/src/Models/Post.php")->toBeFile();
    });

    it('works on the package in a subdirectory of it', function () {
        $root = monorepo();

        generate("$root/packages/shop/src", 'make:event', ['name' => 'Paid']);

        expect("$root/packages/shop/src/Events/Paid.php")->toBeFile();
    });

    it('works on another package of the monorepo from inside a package', function () {
        $root = monorepo();

        generate("$root/packages/shop", 'make:event', ['name' => 'Paid', '--package' => 'blog']);

        expect("$root/packages/blog/src/Events/Paid.php")->toBeFile();
    });

    it('takes a customised stub from the root of the monorepo', function () {
        $root = monorepo();
        put($root, 'stubs/anvil/class.stub', "<?php\n\nnamespace {{ namespace }};\n\nfinal class {{ class }}\n{\n    // root stub\n}\n");

        generate($root, 'make:class', ['name' => 'Slugger', '--package' => 'blog']);

        expect(contents($root, 'packages/blog/src/Slugger.php'))->toContain('// root stub');
    });

    it('keeps working on the package the commands it calls run in', function () {
        $root = monorepo();

        generate($root, 'make:model', ['name' => 'Post', '--package' => 'shop', '--all' => true]);

        expect("$root/packages/shop/database/factories/PostFactory.php")->toBeFile()
            ->and("$root/packages/shop/src/Http/Controllers/PostController.php")->toBeFile()
            ->and("$root/packages/blog/database/factories/PostFactory.php")->not->toBeFile();
    });
});

describe('every package of the monorepo', function () {
    it('lists them with package:info --all', function () {
        $output = generate(monorepo(), 'package:info', ['--all' => true]);

        expect($output)->toContain('| packages/blog | acme/laravel-blog | blog       | Acme\Blog      | toolkit | Acme\Blog\BlogServiceProvider |')
            ->and($output)->toContain('| packages/shop | acme/laravel-shop | shop       | Acme\Shop      | toolkit | Acme\Shop\ShopServiceProvider |');
    });

    it('shows the monorepo in package:info', function () {
        $output = generate(monorepo(), 'package:info', ['--package' => 'shop']);

        expect($output)->toContain('--package=packages/shop');
    });

    it('does not look for the monorepo above the root of a git repository', function () {
        $root = monorepo();

        expect(generate("$root/packages/shop", 'package:info'))->toContain('--package=packages/shop');

        mkdir("$root/packages/shop/.git");

        expect(generate("$root/packages/shop", 'package:info'))->not->toContain('Monorepo');
    });

    it('checks them with package:doctor --all', function () {
        $root = monorepo();
        unregisteredCommand($root);

        [$code, $output] = anvil($root, 'package:doctor', ['--all' => true]);

        expect($code)->toBe(1)
            ->and($output)->toContain("packages/blog (acme/laravel-blog)\n  DONE")
            ->and($output)->toContain('packages/shop (acme/laravel-shop)')
            ->and($output)->toContain('ERROR src/Commands/Prune.php is not registered')
            ->and($output)->toContain('package:doctor --all --fix');

        [$code, $output] = anvil($root, 'package:doctor', ['--all' => true, '--fix' => true]);

        expect($code)->toBe(0, $output)
            ->and(contents($root, 'packages/shop/src/ShopServiceProvider.php'))->toContain('Prune::class');
    });

    it('reports them as JSON', function () {
        $root = monorepo();
        unregisteredCommand($root);

        [$code, $output] = anvil($root, 'package:doctor', ['--all' => true, '--json' => true]);
        $report = json_decode($output, true);

        expect($code)->toBe(1)
            ->and(array_keys($report['packages']))->toBe(['packages/blog', 'packages/shop'])
            ->and($report['packages']['blog'] ?? null)->toBeNull()
            ->and($report['packages']['packages/blog']['errors'])->toBe(0)
            ->and($report['errors'])->toBe($report['packages']['packages/shop']['errors'])
            ->and($report['errors'])->toBeGreaterThan(0);
    });

    it('needs a monorepo for --all', function () {
        [$code, $output] = anvil(initPackage(), 'package:doctor', ['--all' => true]);

        expect($code)->toBe(1)
            ->and($output)->toContain('--all checks the packages of a monorepo');
    });
});

/**
 * A monorepo root with the given composer.json (and files).
 *
 * @param  array<string, string>  $files
 */
function monorepoRoot(array $composer, array $files = []): string
{
    $root = tempPackage(['name' => 'acme/monorepo', ...$composer]);

    foreach ($files as $path => $contents) {
        put($root, $path, $contents);
    }

    return $root;
}

function initIn(string $root, string $directory, string $name, array $options = []): string
{
    return generate($root, 'package:init', ['--package' => $directory, '--name' => $name, '--author' => 'Jane Doe', '--email' => 'jane@example.com', '--toolkit' => true, ...$options]);
}

describe('the layouts of a monorepo', function () {
    it('includes a package in wikimedia/composer-merge-plugin', function () {
        $root = monorepoRoot(['extra' => ['merge-plugin' => ['include' => ['packages/*/composer.json']]]]);

        $output = initIn($root, 'packages/blog', 'acme/laravel-blog');

        expect($output)->toContain('UPDATE ../../composer.json (require-dev nyoncode/laravel-package-console)')
            ->and(composerJson($root)['extra']['merge-plugin']['include'])->toBe(['packages/*/composer.json'])
            ->and(composerJson($root)['require-dev'])->not->toHaveKey('acme/laravel-blog');

        $output = initIn($root, 'modules/forum', 'acme/laravel-forum');

        expect($output)->toContain('merge-plugin include modules/forum/composer.json')
            ->and(composerJson($root)['extra']['merge-plugin']['include'])->toBe(['packages/*/composer.json', 'modules/forum/composer.json'])
            ->and(array_keys(anvilPackages($root)))->toBe(['modules/forum', 'packages/blog']);
    });

    it('merges a package into the root of symplify/monorepo-builder', function () {
        $root = monorepoRoot(['require' => ['php' => '^8.2']], [
            'monorepo-builder.php' => "<?php\n\nuse Symplify\\MonorepoBuilder\\Config\\MBConfig;\n\nreturn static function (MBConfig \$config): void {\n    \$config->packageDirectories([__DIR__.'/libs']);\n    \$config->packageDirectoriesExcludes([__DIR__.'/libs/legacy']);\n};\n",
        ]);
        put($root, 'libs/legacy/composer.json', '{"name": "acme/legacy"}');

        $output = initIn($root, 'libs/blog', 'acme/laravel-blog');
        $composer = composerJson($root);

        expect($output)->toContain('UPDATE ../../composer.json (autoload Acme\Blog => libs/blog/src/')
            ->and($composer['autoload']['psr-4'])->toBe(['Acme\\Blog\\' => 'libs/blog/src/', 'Acme\\Blog\\Database\\Factories\\' => 'libs/blog/database/factories/'])
            ->and($composer['autoload-dev']['psr-4'])->toBe(['Acme\\Blog\\Tests\\' => 'libs/blog/tests/'])
            ->and($composer['require'])->toHaveKeys(['php', 'illuminate/support', 'nyoncode/laravel-package-toolkit'])
            ->and($composer['require']['php'])->toBe('^8.2')
            ->and($composer['require-dev'])->toHaveKeys(['orchestra/testbench', 'nyoncode/laravel-package-console'])
            ->and($composer['replace'])->toBe(['acme/laravel-blog' => 'self.version'])
            ->and($composer)->not->toHaveKey('repositories')
            ->and(array_keys(anvilPackages($root)))->toBe(['libs/blog']);

        // The root autoloads only its packages' classes, so it is no package to choose by default.
        [$code, $output] = anvil($root, 'make:event', ['name' => 'Paid']);

        expect($code)->toBe(0, $output)
            ->and("$root/libs/blog/src/Events/Paid.php")->toBeFile();

        expect(initIn($root, 'packages/shop', 'acme/laravel-shop'))
            ->toContain('WARN packages/shop is not in the package directories of monorepo-builder.php (libs). Add it there.');
    });

    it('finds the packages a root replaces, laravel/framework style', function () {
        $root = monorepoRoot([
            'autoload' => ['psr-4' => ['Illuminate\\' => 'src/Illuminate/']],
            'replace' => ['illuminate/support' => 'self.version'],
        ], ['src/Illuminate/Support/composer.json' => '{"name": "illuminate/support", "autoload": {"psr-4": {"Illuminate\\\\Support\\\\": ""}}}']);

        generate($root, 'make:class', ['name' => 'Str', '--package' => 'support']);

        expect("$root/src/Illuminate/Support/Str.php")->toBeFile()
            ->and(array_keys(anvilPackages($root)))->toBe(['.', 'src/Illuminate/Support']);

        // The root's "Illuminate\\" autoloads the package, so the doctor asks for nothing.
        expect(doctor($root, ['--package' => 'support'])[1])->not->toContain('of the monorepo');
    });
});

/**
 * The packages Anvil finds in a monorepo, as package:info --all lists them.
 *
 * @return array<string, string>
 */
function anvilPackages(string $root): array
{
    $packages = [];

    preg_match_all('/^\| (\S+)\s+\| (\S+)/m', generate($root, 'package:info', ['--all' => true]), $rows, PREG_SET_ORDER);

    foreach (array_slice($rows, 1) as [, $package, $name]) {
        $packages[$package] = $name;
    }

    return $packages;
}

describe('package:doctor in a monorepo', function () {
    it('reports a package the root does not install, and --fix installs it', function () {
        $root = monorepo();
        $composer = composerJson($root);
        unset($composer['require-dev']['acme/laravel-shop']);
        file_put_contents("$root/composer.json", json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        [$code, $output] = doctor($root, ['--package' => 'shop']);

        expect($code)->toBe(1)
            ->and($output)->toContain('ERROR ../../composer.json of the monorepo (Composer path repositories) does not install the package. Missing: require-dev acme/laravel-shop.');

        [$code, $output] = doctor($root, ['--package' => 'shop', '--fix' => true]);

        expect($code)->toBe(0, $output)
            ->and(composerJson($root)['require-dev']['acme/laravel-shop'])->toBe('@dev');
    });

    it('reports what a merged root lacks after the package changed', function () {
        $root = monorepoRoot([], ['monorepo-builder.php' => "<?php\n\nreturn static function (\$config): void {};\n"]);
        initIn($root, 'packages/blog', 'acme/laravel-blog');
        $composer = composerJson("$root/packages/blog");
        $composer['require']['spatie/once'] = '^3.0';
        file_put_contents("$root/packages/blog/composer.json", json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        [$code, $output] = doctor($root, ['--package' => 'blog', '--fix' => true]);

        expect($code)->toBe(0, $output)
            ->and($output)->toContain('FIXED ../../composer.json: added require spatie/once')
            ->and(composerJson($root)['require']['spatie/once'])->toBe('^3.0');
    });

    it('warns about a version of another package a path repository cannot give', function () {
        $root = monorepo();
        $composer = composerJson("$root/packages/shop");
        $composer['require']['acme/laravel-blog'] = '^1.0';
        file_put_contents("$root/packages/shop/composer.json", json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        expect(doctor($root, ['--package' => 'shop'])[1])
            ->toContain('WARN  composer.json requires acme/laravel-blog ^1.0, but the path repository gives it only a dev version (dev-main).');

        $blog = composerJson("$root/packages/blog");
        $blog['extra']['branch-alias'] = ['dev-main' => '1.x-dev'];
        file_put_contents("$root/packages/blog/composer.json", json_encode($blog, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        expect(doctor($root, ['--package' => 'shop'])[1])->not->toContain('acme/laravel-blog ^1.0');
    });

    it('tells about dependencies the packages require with different constraints', function () {
        $root = monorepo(['packages/blog' => 'acme/laravel-blog']);
        initIn($root, 'packages/shop', 'acme/laravel-shop', ['--laravel' => '13']);

        [$code, $output] = doctor($root, ['--all' => true]);

        expect($code)->toBe(0, $output)
            ->and($output)->toContain("Monorepo (Composer path repositories)\n  INFO  php is required with different constraints: ^8.2 in packages/blog, ^8.3 in packages/shop.")
            ->and($output)->toContain('INFO  illuminate/support is required with different constraints: ^12.0|^13.0 in packages/blog, ^13.0 in packages/shop.');

        // Packages support different Laravel versions on purpose, so --strict does not fail on it.
        [$code, $output] = doctor($root, ['--all' => true, '--json' => true, '--strict' => true]);
        $report = json_decode($output, true);

        expect($code)->toBe(0, $output)
            ->and($report['warnings'])->toBe(0)
            ->and($report['monorepo']['layouts'])->toBe(['path'])
            ->and($report['monorepo']['warnings'])->toBe(0)
            ->and(array_column($report['monorepo']['findings'], 'severity'))->each->toBe('info')
            ->and(array_column($report['monorepo']['findings'], 'subject'))->toContain('illuminate/support');
    });
});

describe('the CI workflow of a monorepo', function () {
    it('tests every package from the root instead of each package', function () {
        $root = monorepo(['packages/blog' => 'acme/laravel-blog']);

        expect("$root/packages/blog/.github")->not->toBeDirectory()
            ->and(contents($root, '.github/workflows/tests.yml'))
            ->toContain("package: ['packages/blog']")
            ->toContain('working-directory: ${{ matrix.package }}')
            ->toContain("composer config repositories.monorepo-1 '{\"type\": \"path\", \"url\": \"\${{ github.workspace }}/packages/*\"}'");

        $output = initIn($root, 'packages/shop', 'acme/laravel-shop');

        expect($output)->toContain('UPDATE ../../.github/workflows/tests.yml (matrix package packages/shop)')
            ->and(contents($root, '.github/workflows/tests.yml'))->toContain("package: ['packages/blog', 'packages/shop']");
    });

    it('skips the PHP versions a package does not support', function () {
        $root = monorepo(['packages/blog' => 'acme/laravel-blog']);
        $workflow = contents($root, '.github/workflows/tests.yml');
        $composer = trim((string) shell_exec('command -v composer'));

        expect($workflow)->toContain("php: ['8.2', '8.3', '8.4', '8.5']")
            ->and(substr_count($workflow, "if: steps.php.outputs.supported == 'true'"))->toBe(3)
            ->and(preg_match("/supported=\\$\\(php -r '(.*?)' -- /s", $workflow, $script))->toBe(1);

        if ($composer === '') {
            test()->markTestSkipped('Composer is not installed.');
        }

        $supports = function (string $php) use ($root, $script, $composer) {
            $package = composerJson("$root/packages/blog");
            $package['require']['php'] = $php;
            file_put_contents("$root/packages/blog/composer.json", json_encode($package));

            return shell_exec('cd '.escapeshellarg("$root/packages/blog").' && php -r '.escapeshellarg($script[1]).' -- '.escapeshellarg($composer));
        };

        expect($supports('^'.PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION))->toBe('true')
            ->and($supports('^99.0'))->toBe('false');
    });

    it('leaves a workflow of your own alone', function () {
        $root = monorepoRoot(['repositories' => [['type' => 'path', 'url' => 'packages/*']]], ['.github/workflows/tests.yml' => "name: tests\n"]);

        $output = initIn($root, 'packages/blog', 'acme/laravel-blog');

        expect($output)->toContain('WARN ../../.github/workflows/tests.yml does not mention packages/blog.')
            ->and(file_get_contents("$root/.github/workflows/tests.yml"))->toBe("name: tests\n");
    });
});
