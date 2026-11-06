<?php

namespace NyonCode\LaravelPackageConsole\Commands;

use NyonCode\LaravelPackageConsole\Support\Package;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;

use function Symfony\Component\String\u;

/**
 * Creates composer.json (or completes an existing one) for a Laravel 12/13 package and
 * scaffolds the main service provider, config, Pest + Testbench tests and tooling.
 * Files that already exist are kept unless --force is given.
 */
#[AsCommand(name: 'package:init', description: 'Create composer.json and scaffold a Laravel package', aliases: ['init'])]
final class PackageInitCommand extends GenerateCommand
{
    /**
     * Supported Laravel versions and what each one needs.
     */
    private const LARAVEL = [
        '12' => ['php' => ['8.2', '8.3', '8.4', '8.5'], 'testbench' => '10'],
        '13' => ['php' => ['8.3', '8.4', '8.5'], 'testbench' => '11'],
    ];

    private const TOOLKIT_VERSION = '^2.5';

    protected string $type = 'File';

    public function handle(): int
    {
        $package = $this->package();
        $composer = $package->composer();

        $this->title($composer->exists() ? 'Completing composer.json' : 'Creating a new Laravel package');

        $name = $this->answer('name', 'Composer package name (vendor/name)', $composer->get('name') ?: $this->guessName(), function (?string $value) {
            if (! preg_match('{^[a-z0-9]([_.-]?[a-z0-9]+)*/[a-z0-9](([_.]|-{1,2})?[a-z0-9]+)*$}', (string) $value)) {
                throw new RuntimeException('The package name must be lowercase "vendor/name", e.g. "acme/laravel-blog".');
            }

            return $value;
        });

        $description = $this->answer('description', 'Description', $composer->get('description') ?: 'A Laravel package.');
        $namespace = trim($this->answer('namespace', 'Root namespace', $this->existingNamespace() ?? $this->guessNamespace($name)), '\\');
        $author = $this->answer('author', 'Author name', $composer->get('authors.0.name') ?: $this->git('user.name') ?: 'Your Name');
        $email = $this->answer('email', 'Author email', $composer->get('authors.0.email') ?: $this->git('user.email') ?: '');
        $toolkit = $this->wantsToolkit();
        $laravel = $this->laravelVersions();

        $this->writeComposer($name, $description, $namespace, $author, $email, $toolkit, $laravel);
        $package->refresh();

        $this->newLine();

        if ($package->mainProvider() === null) {
            $this->call('make:provider', ['name' => $package->defaultProviderName(), '--main' => true]);
        }

        if (! $this->option('no-config') && ! is_file($package->path('config/'.$package->shortName().'.php'))) {
            $this->call('make:config');
        }

        $this->scaffold($this->replacements($description, $author, $toolkit, $laravel));

        $this->newLine();
        $this->right(($toolkit ? 'Package ready (with laravel-package-toolkit).' : 'Package ready.'));
        $this->message([
            '  Next steps:',
            '    <options=bold>composer update</>',
            '    <options=bold>vendor/bin/pest</>',
            '',
            '  Then add resources with the make:* commands, e.g. <options=bold>php anvil make:model Post -mf</>.',
            '  They are registered in '.$package->relativePath((string) $package->mainProviderPath()).' automatically.',
        ]);

        return self::SUCCESS;
    }

    /**
     * @param  list<string>  $laravel
     */
    private function writeComposer(string $name, string $description, string $namespace, string $author, string $email, bool $toolkit, array $laravel): void
    {
        $composer = $this->package()->composer();
        $supportsL12 = in_array('12', $laravel, true);
        $short = Package::shortNameFor($name);

        $composer->set('name', $name)
            ->setIfMissing('description', $description)
            ->setIfMissing('type', 'library')
            ->setIfMissing('keywords', array_values(array_unique(['laravel', $short])))
            ->setIfMissing('license', 'MIT')
            ->setIfMissing('authors', [array_filter(['name' => $author, 'email' => $email, 'role' => 'Developer'])]);

        if ($description !== '' && $this->option('description')) {
            $composer->set('description', $description);
        }

        $require = [
            'php' => $supportsL12 ? '^8.2' : '^8.3',
            'illuminate/support' => implode('|', array_map(fn (string $v) => "^$v.0", $laravel)),
        ];

        if ($toolkit) {
            $require[Package::TOOLKIT] = self::TOOLKIT_VERSION;
        }

        $composer->mergeMissing('require', $require);
        $composer->mergeMissing('require-dev', [
            'larastan/larastan' => '^3.0',
            'laravel/pint' => '^1.24',
            'orchestra/testbench' => implode('|', array_map(fn (string $v) => '^'.self::LARAVEL[$v]['testbench'].'.0', $laravel)),
            'pestphp/pest' => $supportsL12 ? '^3.8|^4.0' : '^4.0',
            'pestphp/pest-plugin-laravel' => $supportsL12 ? '^3.2|^4.0' : '^4.0',
        ]);

        $psr4 = (array) $composer->get('autoload.psr-4', []);

        if (! in_array('src/', array_map(fn ($path) => rtrim((string) (is_array($path) ? $path[0] : $path), '/').'/', $psr4), true)) {
            $psr4 = [$namespace.'\\' => 'src/'] + $psr4;
        }

        $psr4 += [$namespace.'\\Database\\Factories\\' => 'database/factories/'];
        $composer->set('autoload.psr-4', $psr4);
        $composer->mergeMissing('autoload-dev.psr-4', [$namespace.'\\Tests\\' => 'tests/']);

        $composer->mergeMissing('scripts', [
            'test' => 'vendor/bin/pest',
            'format' => 'vendor/bin/pint',
            'analyse' => 'vendor/bin/phpstan analyse',
        ]);
        $composer->setIfMissing('config.sort-packages', true);
        $composer->setIfMissing('config.allow-plugins.pestphp/pest-plugin', true);
        $composer->setIfMissing('minimum-stability', 'stable');
        $composer->setIfMissing('prefer-stable', true);

        $existed = $composer->exists();
        $composer->save();

        $this->output()->writeln(sprintf('  <fg=green;options=bold>%s</> composer.json', $existed ? 'UPDATE' : 'CREATE'));
    }

    /**
     * @param  array<string, string>  $replacements
     */
    private function scaffold(array $replacements): void
    {
        $files = [
            'anvil' => 'anvil.stub',
            '.gitignore' => 'gitignore.stub',
            '.gitattributes' => 'gitattributes.stub',
            'README.md' => 'README.stub',
            'LICENSE.md' => 'LICENSE.stub',
            'pint.json' => 'pint.json.stub',
            'phpstan.neon.dist' => 'phpstan.neon.dist.stub',
            'testbench.yaml' => 'testbench.yaml.stub',
        ];

        if (! $this->option('no-tests')) {
            $files += [
                'phpunit.xml' => 'phpunit.xml.stub',
                'tests/TestCase.php' => 'TestCase.stub',
                'tests/Pest.php' => 'Pest.stub',
                'tests/ArchTest.php' => 'ArchTest.stub',
                'tests/Feature/ServiceProviderTest.php' => 'ExampleTest.stub',
                '.github/workflows/tests.yml' => 'workflow.stub',
            ];
        }

        foreach ($files as $target => $stub) {
            $path = $this->package()->path($target);

            if ($this->files->isFile($path) && ! $this->option('force')) {
                $this->output()->writeln("  <fg=gray>SKIP</>   $target (exists)");

                continue;
            }

            $contents = $this->populateStub($this->files->get($this->resolveStub('package/'.$stub)), $replacements);

            if (str_ends_with($target, '.php')) {
                $contents = $this->tidyImports($contents);
            }

            $this->makeDirectory($path);
            $this->files->put($path, $contents);

            if ($target === 'anvil') {
                @chmod($path, 0755);
            }

            $this->output()->writeln("  <fg=green;options=bold>CREATE</> $target");
        }
    }

    /**
     * @param  list<string>  $laravel
     * @return array<string, string>
     */
    private function replacements(string $description, string $author, bool $toolkit, array $laravel): array
    {
        $package = $this->package();
        $provider = (string) $package->mainProvider();
        $php = array_values(array_unique(array_merge(...array_map(fn (string $v) => self::LARAVEL[$v]['php'], $laravel))));
        sort($php);

        $include = [];
        $exclude = [];

        foreach ($laravel as $version) {
            $include[] = "          - laravel: '$version.*'\n            testbench: '".self::LARAVEL[$version]['testbench'].".*'";

            foreach (array_diff($php, self::LARAVEL[$version]['php']) as $unsupported) {
                $exclude[] = "          - laravel: '$version.*'\n            php: '$unsupported'";
            }
        }

        return [
            'rootNamespace' => $package->rootNamespace(),
            'package' => $package->shortName(),
            'packageName' => $package->displayName(),
            'composerName' => $package->name(),
            'description' => $description,
            'provider' => $provider,
            'providerClass' => $this->classBasename($provider),
            'php' => (string) $package->composer()->get('require.php'),
            'laravel' => implode(' / ', array_map(fn (string $v) => "$v.x", $laravel)),
            'configTag' => $package->shortName().($toolkit ? '::' : '-').'config',
            'year' => date('Y'),
            'appKey' => 'base64:'.base64_encode(random_bytes(32)),
            'author' => $author,
            'phpVersions' => implode(', ', array_map(fn (string $v) => "'$v'", $php)),
            'laravelVersions' => implode(', ', array_map(fn (string $v) => "'$v.*'", $laravel)),
            'matrixInclude' => implode("\n", $include),
            'matrixExclude' => $exclude === [] ? '' : "        exclude:\n".implode("\n", $exclude),
        ];
    }

    /**
     * Use the --option value, else ask (interactive), else the default.
     */
    private function answer(string $option, string $question, string $default, ?callable $validator = null): string
    {
        $value = $this->option($option);

        if (is_string($value) && $value !== '') {
            return $validator !== null ? $validator($value) : $value;
        }

        if (! $this->input()->isInteractive()) {
            return $validator !== null ? $validator($default) : $default;
        }

        return (string) $this->ask($question, $default, $validator);
    }

    private function wantsToolkit(): bool
    {
        if ($this->option('toolkit')) {
            return true;
        }

        if ($this->option('no-toolkit')) {
            return false;
        }

        $default = $this->package()->usesToolkit() || ! $this->package()->exists() || $this->package()->mainProvider() === null;

        return $this->input()->isInteractive()
            ? $this->confirm('Use nyoncode/laravel-package-toolkit for the service provider?', $default)
            : $default;
    }

    /**
     * @return list<string>
     */
    private function laravelVersions(): array
    {
        $versions = array_values(array_unique(array_filter(array_map('trim', explode(',', (string) $this->option('laravel'))))));
        $unsupported = array_diff($versions, array_keys(self::LARAVEL));

        if ($versions === [] || $unsupported !== []) {
            throw new RuntimeException('Supported Laravel versions: '.implode(', ', array_keys(self::LARAVEL)).'.');
        }

        sort($versions);

        return $versions;
    }

    private function existingNamespace(): ?string
    {
        try {
            return $this->package()->rootNamespace();
        } catch (RuntimeException) {
            return null;
        }
    }

    private function guessName(): string
    {
        $vendor = u((string) ($this->git('github.user') ?: 'vendor'))->kebab()->lower()->toString();

        return $vendor.'/'.u(basename($this->package()->path()))->kebab()->lower()->toString();
    }

    /**
     * "acme/laravel-blog-posts" -> "Acme\BlogPosts".
     */
    private function guessNamespace(string $name): string
    {
        $vendor = explode('/', $name)[0];

        return u($vendor)->camel()->title()->toString().'\\'.u(Package::shortNameFor($name))->camel()->title()->toString();
    }

    private function git(string $key): ?string
    {
        $value = @shell_exec('git config --get '.escapeshellarg($key).' 2>'.(windows_os() ? 'NUL' : '/dev/null'));

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    protected function getArguments(): array
    {
        return [];
    }

    protected function getValidationRules(): array
    {
        return [];
    }

    protected function getOptions(): array
    {
        return [
            ['name', null, InputOption::VALUE_REQUIRED, 'Composer package name (vendor/name)'],
            ['description', null, InputOption::VALUE_REQUIRED, 'Package description'],
            ['namespace', null, InputOption::VALUE_REQUIRED, 'Root PHP namespace (e.g. "Acme\\Blog")'],
            ['author', null, InputOption::VALUE_REQUIRED, 'Author name (defaults to git config user.name)'],
            ['email', null, InputOption::VALUE_REQUIRED, 'Author email (defaults to git config user.email)'],
            ['toolkit', null, InputOption::VALUE_NONE, 'Use nyoncode/laravel-package-toolkit'],
            ['no-toolkit', null, InputOption::VALUE_NONE, 'Use a plain Laravel ServiceProvider'],
            ['laravel', null, InputOption::VALUE_REQUIRED, 'Supported Laravel versions, comma separated', '12,13'],
            ['no-tests', null, InputOption::VALUE_NONE, 'Do not scaffold Pest, Testbench and the CI workflow'],
            ['no-config', null, InputOption::VALUE_NONE, 'Do not create a config file'],
            ['force', 'f', InputOption::VALUE_NONE, 'Overwrite scaffolded files that already exist'],
        ];
    }
}
