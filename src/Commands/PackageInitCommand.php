<?php

namespace NyonCode\LaravelPackageConsole\Commands;

use Closure;
use Laravel\Prompts\FormBuilder;
use NyonCode\LaravelPackageConsole\Support\Package;
use NyonCode\LaravelPackageConsole\Support\PhpFileEditor;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Formatter\OutputFormatter;
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

    /**
     * Files written by this run.
     *
     * @var list<string>
     */
    private array $written = [];

    protected string $type = 'File';

    public function handle(): int
    {
        $package = $this->package();
        $composer = $package->composer();

        $this->title($composer->exists() ? 'Completing composer.json' : 'Creating a new Laravel package');

        // A provider the package already has, but composer.json does not list.
        $discovered = $package->exists() && $package->mainProvider() === null ? $package->discoverProviders() : [];
        $hasProvider = $package->mainProvider() !== null || $discovered !== [];

        $before = $this->current();
        $answers = $this->questions($hasProvider, $before)->submit();

        if ($answers['confirmed'] === false) {
            $this->message('  '.$this->trans('Nothing was written.'));

            return self::FAILURE;
        }

        $name = $answers['name'];
        $description = $answers['description'];
        $namespace = $answers['namespace'];
        $author = $answers['author'];
        $email = $answers['email'];
        $toolkit = $answers['toolkit'];
        $laravel = $answers['laravel'];

        $this->keepNamespace($before['namespace']);
        $this->writeComposer($name, $description, $namespace, $author, $email, $toolkit, $laravel, $before);
        $package->refresh();

        $this->newLine();

        if ($package->mainProvider() === null && $discovered !== []) {
            $this->registerExistingProvider($discovered[0]);
        } elseif ($package->mainProvider() === null) {
            $this->call('make:provider', ['name' => $package->defaultProviderName(), '--main' => true]);
        }

        if ($this->option('toolkit') && $package->providerMode() !== 'toolkit') {
            $this->output()->writeln(sprintf(
                '  <fg=yellow;options=bold>WARN</> %s is not a laravel-package-toolkit provider, so generators keep registering in its own style.',
                $package->relativePath((string) $package->mainProviderPath())
            ));
        }

        if (! $this->option('no-config') && ! is_file($package->path('config/'.$package->shortName().'.php'))) {
            $this->call('make:config');
        }

        $this->scaffold($this->replacements($description, $author, $laravel));

        if ($before['laravel'] !== null && $before['laravel'] !== $laravel) {
            $this->checkWorkflow($laravel);
        }

        $this->newLine();
        $this->right(match ($package->providerMode()) {
            'toolkit' => 'Package ready (with laravel-package-toolkit).',
            'spatie' => 'Package ready (with spatie/laravel-package-tools).',
            default => 'Package ready.',
        });
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
     * List a provider the package already has in extra.laravel.providers, first, instead of
     * creating a second one.
     */
    private function registerExistingProvider(string $provider): void
    {
        $package = $this->package();
        $composer = $package->composer();
        $composer->set('extra.laravel.providers', [$provider, ...array_values(array_diff($package->providers(), [$provider]))]);
        $composer->save();
        $package->refresh();

        $this->output()->writeln(sprintf(
            '  <fg=green;options=bold>DONE</> Found %s and registered %s in composer.json "extra.laravel.providers".',
            $package->relativePath((string) $package->pathForClass($provider)),
            $provider
        ));
    }

    /**
     * @param  list<string>  $laravel
     */
    /**
     * Fill in what composer.json lacks, and write what the answers change: the name, the
     * description, the author and the Laravel versions. An answer that is the current value
     * (as every answer is with --no-interaction and no option) changes nothing.
     *
     * @param  list<string>  $laravel
     * @param  array{name: ?string, description: ?string, namespace: ?string, author: ?string, email: ?string, laravel: list<string>|null}  $before
     */
    private function writeComposer(string $name, string $description, string $namespace, string $author, string $email, bool $toolkit, array $laravel, array $before): void
    {
        $composer = $this->package()->composer();
        $supportsL12 = in_array('12', $laravel, true);
        $short = Package::shortNameFor($name);

        $composer->set('name', $name)
            ->setIfMissing('type', 'library')
            ->setIfMissing('keywords', array_values(array_unique(['laravel', $short])))
            ->setIfMissing('license', 'MIT');

        if ($description !== $before['description']) {
            $composer->set('description', $description);
        }

        $this->writeAuthor($author, $email, $before);

        $require = [
            'php' => $supportsL12 ? '^8.2' : '^8.3',
            'illuminate/support' => implode('|', array_map(fn (string $v) => "^$v.0", $laravel)),
        ];

        if ($toolkit) {
            $require[Package::TOOLKIT] = self::TOOLKIT_VERSION;
        }

        $requireDev = [
            'larastan/larastan' => '^3.0',
            'laravel/pint' => '^1.24',
            'nyoncode/laravel-package-console' => '^'.explode('.', (string) anvil_config('version'))[0].'.0',
            'orchestra/testbench' => implode('|', array_map(fn (string $v) => '^'.self::LARAVEL[$v]['testbench'].'.0', $laravel)),
            'pestphp/pest' => $supportsL12 ? '^3.8|^4.0' : '^4.0',
            'pestphp/pest-plugin-laravel' => $supportsL12 ? '^3.2|^4.0' : '^4.0',
        ];

        if ($before['laravel'] !== null && $before['laravel'] !== $laravel) {
            $this->changeLaravel($require, $requireDev);
        }

        $composer->mergeMissing('require', $require);
        $composer->mergeMissing('require-dev', $requireDev);

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
     * Rewrite the constraints that follow from the Laravel versions. PHP is only raised: a
     * package may well require a newer PHP than Laravel does.
     *
     * @param  array<string, string>  $require
     * @param  array<string, string>  $requireDev
     */
    private function changeLaravel(array $require, array $requireDev): void
    {
        $composer = $this->package()->composer();

        foreach (array_keys((array) $composer->get('require', [])) as $package) {
            if ($package === 'laravel/framework' || str_starts_with((string) $package, 'illuminate/')) {
                $composer->set("require.$package", $require['illuminate/support']);
            }
        }

        $php = $composer->get('require.php');
        $minimum = ltrim($require['php'], '^');

        if (is_string($php) && preg_match('/(\d+\.\d+)/', $php, $current) && version_compare($current[1], $minimum, '<')) {
            $composer->set('require.php', $require['php']);
        }

        foreach (['orchestra/testbench', 'pestphp/pest', 'pestphp/pest-plugin-laravel'] as $package) {
            if ($composer->get("require-dev.$package") !== null) {
                $composer->set("require-dev.$package", $requireDev[$package]);
            }
        }
    }

    /**
     * The first author of composer.json is the one the questions are about. Its other keys
     * (role, homepage) are kept.
     *
     * @param  array{author: ?string, email: ?string}  $before
     */
    private function writeAuthor(string $author, string $email, array $before): void
    {
        $composer = $this->package()->composer();
        $current = $composer->get('authors.0');

        if (! is_array($current)) {
            $composer->setIfMissing('authors', [array_filter(['name' => $author, 'email' => $email, 'role' => 'Developer'])]);

            return;
        }

        if ($author === $before['author'] && $email === (string) $before['email']) {
            return;
        }

        $current = ['name' => $author, 'email' => $email] + $current;
        $current['name'] = $author;
        $current['email'] = $email;

        $composer->set('authors.0', array_filter($current, fn (mixed $value) => $value !== ''));
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

            if (($existing = $this->existingEquivalent($target)) !== null && ! $this->option('force')) {
                $this->output()->writeln("  <fg=gray>SKIP</>   $target ($existing exists)");

                continue;
            }

            $contents = $this->populateStub($this->files->get($this->resolveStub('package/'.$stub)), $replacements);

            if (str_ends_with($target, '.php')) {
                $contents = $this->tidyImports($contents);
            }

            $this->makeDirectory($path);
            $this->files->put($path, $contents);
            $this->written[] = $target;

            if ($target === 'anvil') {
                @chmod($path, 0755);
            }

            $this->output()->writeln("  <fg=green;options=bold>CREATE</> $target");
        }
    }

    /**
     * A file the package already has that does the job of $target, so writing $target would
     * clash with it: phpunit.xml would override phpunit.xml.dist, a second CI workflow would run
     * the tests twice, and Pest files would point at a TestCase the package does not have.
     */
    private function existingEquivalent(string $target): ?string
    {
        $package = $this->package();
        $alternatives = [
            'phpunit.xml' => ['phpunit.xml.dist', 'phpunit.dist.xml'],
            'phpstan.neon.dist' => ['phpstan.neon', 'phpstan.dist.neon'],
            'pint.json' => ['.pint.json'],
            'LICENSE.md' => ['LICENSE', 'LICENSE.txt', 'license.md'],
            'README.md' => ['readme.md', 'README', 'README.rst'],
            'tests/Pest.php' => ['tests/TestCase.php'],
            'tests/ArchTest.php' => ['tests/TestCase.php'],
            'tests/Feature/ServiceProviderTest.php' => ['tests/TestCase.php'],
        ];

        foreach ($alternatives[$target] ?? [] as $file) {
            // tests/TestCase.php is ours when this run just wrote it.
            if (is_file($package->path($file)) && ! in_array($file, $this->written, true)) {
                return $file;
            }
        }

        if ($target === '.github/workflows/tests.yml') {
            foreach (glob($package->path('.github/workflows/*.{yml,yaml}'), GLOB_BRACE) ?: [] as $workflow) {
                if (preg_match('/\b(pest|phpunit|composer test)\b/i', (string) file_get_contents($workflow))) {
                    return $package->relativePath($workflow);
                }
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $laravel
     * @return array<string, string>
     */
    private function replacements(string $description, string $author, array $laravel): array
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
            'configTag' => $package->shortName().($package->providerMode() === 'toolkit' ? '::' : '-').'config',
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
     * Everything package:init needs to know. An answer comes from its --option, else from a
     * question, else (not interactive) from the default. In a terminal Ctrl+U goes back to the
     * previous question, and nothing is written before the summary is confirmed.
     */
    /**
     * @param  array{name: ?string, description: ?string, namespace: ?string, author: ?string, email: ?string, laravel: list<string>|null}  $before
     */
    private function questions(bool $hasProvider, array $before): FormBuilder
    {
        // Git fills in an author composer.json does not have, not the email of one it has.
        $hasAuthor = is_array($this->package()->composer()->get('authors.0'));

        return (new FormBuilder)
            ->add(fn (array $answers, ?string $previous) => $this->answer(
                'name',
                $this->trans('Composer package name'),
                $previous ?? ($before['name'] ?: $this->guessName()),
                'vendor/name',
                fn (string $value) => preg_match('{^[a-z0-9]([_.-]?[a-z0-9]+)*/[a-z0-9](([_.]|-{1,2})?[a-z0-9]+)*$}', $value)
                    ? null
                    : 'The package name must be lowercase "vendor/name", e.g. "acme/laravel-blog".',
            ), 'name')
            ->add(fn (array $answers, ?string $previous) => $this->answer(
                'description',
                $this->trans('Description'),
                $previous ?? $before['description'] ?? 'A Laravel package.',
            ), 'description')
            // The namespace of a package that has one is not asked: renaming it would break its classes.
            ->add(fn (array $answers, ?string $previous) => $before['namespace'] ?? trim($this->answer(
                'namespace',
                $this->trans('Root namespace'),
                $previous ?? $this->existingNamespace() ?? $this->guessNamespace($answers['name']),
                validate: fn (string $value) => preg_match('/^\\\\?[A-Za-z_][A-Za-z0-9_]*(\\\\[A-Za-z_][A-Za-z0-9_]*)*\\\\?$/', $value)
                    ? null
                    : 'The namespace must be a valid PHP namespace, e.g. "Acme\Blog".',
            ), '\\'), 'namespace')
            ->add(fn (array $answers, ?string $previous) => $this->answer(
                'author',
                $this->trans('Author name'),
                $previous ?? $before['author'] ?? ($hasAuthor ? '' : ($this->git('user.name') ?: 'Your Name')),
            ), 'author')
            ->add(fn (array $answers, ?string $previous) => $this->answer(
                'email',
                $this->trans('Author email'),
                $previous ?? $before['email'] ?? ($hasAuthor ? '' : ($this->git('user.email') ?: '')),
            ), 'email')
            ->add(fn () => $this->wantsToolkit($hasProvider), 'toolkit')
            ->add(fn () => $this->laravelVersions($before['laravel']), 'laravel')
            ->add(fn (array $answers) => $this->confirmed($answers, $hasProvider, $before), 'confirmed');
    }

    /**
     * Use the --option value, else ask (interactive), else the default.
     *
     * @param  (Closure(string): ?string)|null  $validate  Returns the error, in English.
     */
    private function answer(string $option, string $question, string $default, string $placeholder = '', ?Closure $validate = null): string
    {
        $value = $this->option($option);

        if (is_string($value) && $value !== '') {
            if ($validate !== null && ($error = $validate($value)) !== null) {
                throw new RuntimeException($error);
            }

            return $value;
        }

        return $this->prompts()->text(
            $question,
            $default,
            $placeholder,
            validate: $validate !== null
                ? fn (string $value) => ($error = $validate($value)) !== null && $this->prompts()->interactive() ? $this->trans($error) : $error
                : null,
        );
    }

    /**
     * A new package uses the toolkit unless --no-toolkit is given (or the plain provider is
     * chosen). A package that already has a provider keeps its dependencies: the provider's
     * own style decides how generators register, so the toolkit is only added with an
     * explicit --toolkit.
     */
    private function wantsToolkit(bool $hasProvider): bool
    {
        if ($this->option('toolkit')) {
            return true;
        }

        if ($this->option('no-toolkit')) {
            return false;
        }

        if ($hasProvider) {
            return $this->package()->usesToolkit();
        }

        return $this->prompts()->select($this->trans('Which service provider should the package have?'), [
            'toolkit' => $this->trans('nyoncode/laravel-package-toolkit, with fluent ->hasX() calls'),
            'plain' => $this->trans('A plain Laravel ServiceProvider'),
        ]) === 'toolkit';
    }

    /**
     * @return list<string>
     */
    /**
     * The versions of --laravel, else the ones the package already requires, else 12 and 13;
     * asked for in a terminal unless --laravel is given.
     *
     * @param  list<string>|null  $current
     * @return list<string>
     */
    private function laravelVersions(?array $current): array
    {
        $versions = $this->input()->hasParameterOption('--laravel') || $current === null
            ? array_values(array_unique(array_filter(array_map('trim', explode(',', (string) $this->option('laravel'))))))
            : $current;

        if (! $this->input()->hasParameterOption('--laravel')) {
            $versions = array_map('strval', $this->prompts()->multiselect(
                $this->trans('Which Laravel versions should the package support?'),
                array_map(fn (string $version) => 'Laravel '.$version, array_combine(array_keys(self::LARAVEL), array_keys(self::LARAVEL))),
                array_map('intval', $versions),
                required: true,
            ));
        }

        $unsupported = array_diff($versions, array_map('strval', array_keys(self::LARAVEL)));

        if ($versions === [] || $unsupported !== []) {
            throw new RuntimeException('Supported Laravel versions: '.implode(', ', array_keys(self::LARAVEL)).'.');
        }

        sort($versions);

        return $versions;
    }

    /**
     * Show what is about to be written, with what an existing package changes from → to, and
     * ask whether to go on. Not asked when the run is not interactive.
     *
     * @param  array<string, mixed>  $answers
     * @param  array{name: ?string, description: ?string, namespace: ?string, author: ?string, email: ?string, laravel: list<string>|null}  $before
     */
    private function confirmed(array $answers, bool $hasProvider, array $before): bool
    {
        if (! $this->prompts()->interactive()) {
            return true;
        }

        $author = fn (?string $name, ?string $email) => trim($name.($email !== null && $email !== '' ? " <$email>" : ''));
        $rows = [
            $this->trans('Package') => [$before['name'], $answers['name']],
            $this->trans('Description') => [$before['description'], $answers['description']],
            $this->trans('Namespace') => [null, $answers['namespace']],
            $this->trans('Author') => [
                $before['author'] !== null ? $author($before['author'], $before['email']) : null,
                $author($answers['author'], $answers['email']),
            ],
            $this->trans('Service provider') => [null, $this->providerLabel($hasProvider, $answers['toolkit'])],
            'Laravel' => [
                $before['laravel'] !== null ? implode(', ', $before['laravel']) : null,
                implode(', ', $answers['laravel']),
            ],
        ];
        $width = max(array_map('mb_strlen', array_keys($rows)));

        $this->newLine();

        foreach ($rows as $label => [$old, $new]) {
            $value = $old !== null && $old !== $new
                ? '<fg=gray>'.OutputFormatter::escape($old).'</> → <options=bold>'.OutputFormatter::escape($new).'</>'
                : OutputFormatter::escape($new);

            $this->message(sprintf('  <fg=gray>%s</>%s  %s', $label, str_repeat(' ', $width - mb_strlen($label)), $value));
        }

        $this->newLine();

        return $this->prompts()->confirm($this->trans(
            $this->package()->composer()->exists() ? 'Complete the package with these settings?' : 'Create the package with these settings?'
        ));
    }

    private function providerLabel(bool $hasProvider, bool $toolkit): string
    {
        $package = $this->package();
        $mode = $package->providerMode();

        if ($mode === null && $hasProvider) {
            $path = $package->pathForClass($package->discoverProviders()[0] ?? '');
            $mode = $path !== null && is_file($path) ? PhpFileEditor::fromFile($path)->mode() : null;
        }

        return match (true) {
            $mode === 'toolkit', ! $hasProvider && $toolkit => 'nyoncode/laravel-package-toolkit',
            $mode === 'spatie' => 'spatie/laravel-package-tools',
            $mode === 'plain', ! $hasProvider => $this->trans('plain Laravel ServiceProvider'),
            default => $this->trans('the one the package has'),
        };
    }

    /**
     * What composer.json says now, to prefill the questions and to tell what an answer changes.
     *
     * @return array{name: ?string, description: ?string, namespace: ?string, author: ?string, email: ?string, laravel: list<string>|null}
     */
    private function current(): array
    {
        $composer = $this->package()->composer();
        $string = fn (string $key) => is_string($composer->get($key)) ? $composer->get($key) : null;
        $laravel = array_values(array_intersect($this->package()->laravelVersions() ?? [], array_map('strval', array_keys(self::LARAVEL))));

        return [
            'name' => $string('name'),
            'description' => $string('description'),
            'namespace' => $composer->exists() ? $this->existingNamespace() : null,
            'author' => $string('authors.0.name'),
            'email' => $string('authors.0.email'),
            'laravel' => $laravel !== [] ? $laravel : null,
        ];
    }

    /**
     * --namespace cannot rename the namespace of a package that has one.
     */
    private function keepNamespace(?string $namespace): void
    {
        $option = trim((string) $this->option('namespace'), '\\');

        if ($namespace !== null && $option !== '' && $option !== $namespace) {
            $this->output()->writeln(sprintf(
                '  <fg=yellow;options=bold>WARN</> Kept the namespace %s from composer.json: package:init does not rename the namespace of existing classes.',
                $namespace
            ));
        }
    }

    /**
     * After the Laravel versions changed, the CI matrix of a workflow the package already has
     * may still test the old ones. It is not rewritten, because it may have been edited.
     *
     * @param  list<string>  $laravel
     */
    private function checkWorkflow(array $laravel): void
    {
        $path = $this->package()->path('.github/workflows/tests.yml');

        if (in_array('.github/workflows/tests.yml', $this->written, true) || ! is_file($path)) {
            return;
        }

        $workflow = (string) file_get_contents($path);
        $stale = false;

        foreach (array_keys(self::LARAVEL) as $version) {
            if (str_contains($workflow, "'$version.*'") !== in_array((string) $version, $laravel, true)) {
                $stale = true;
            }
        }

        if ($stale) {
            $this->output()->writeln(
                '  <fg=yellow;options=bold>WARN</> The CI matrix in .github/workflows/tests.yml does not match the new Laravel versions. Update it by hand, or delete it and run package:init again.'
            );
        }
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
     * "acme/laravel-blog-posts" -> "Acme\BlogPosts". A part that would start with a digit,
     * which no namespace can, gets a "Package" prefix.
     */
    private function guessNamespace(string $name): string
    {
        $segments = array_map(
            fn (string $part) => preg_replace('/[^A-Za-z0-9_]/', '', u($part)->camel()->title()->toString()),
            [explode('/', $name)[0], Package::shortNameFor($name)]
        );

        return implode('\\', array_map(
            fn (string $segment) => preg_match('/^[A-Za-z_]/', $segment) ? $segment : 'Package'.$segment,
            $segments
        ));
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
