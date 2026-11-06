<?php

namespace NyonCode\LaravelPackageConsole\Commands;

use NyonCode\LaravelPackageConsole\Concerns\HasClassResolver;
use NyonCode\LaravelPackageConsole\Concerns\HasNamespaceResolver;
use NyonCode\LaravelPackageConsole\Concerns\HasReservedNames;
use NyonCode\LaravelPackageConsole\Concerns\HasStubResolver;
use NyonCode\LaravelPackageConsole\Support\Filesystem;
use NyonCode\LaravelPackageConsole\Support\ProviderRegistrar;
use NyonCode\LaravelPackageConsole\Support\Registration;
use RuntimeException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Generates a class from a stub (the package's counterpart of Laravel's GeneratorCommand).
 *
 * Subclasses set $type (label), $namespace (relative to the package root namespace) and
 * $stub, and may override getReplacements() and afterGenerate().
 */
abstract class GenerateCommand extends BaseCommand
{
    use HasClassResolver;
    use HasNamespaceResolver;
    use HasReservedNames;
    use HasStubResolver;

    protected Filesystem $files;

    /**
     * Human readable type of the generated file, e.g. "Model".
     */
    protected string $type = '';

    /**
     * Namespace relative to the package root namespace, e.g. "Http\Controllers".
     */
    protected string $namespace = '';

    public function __construct()
    {
        parent::__construct();
        $this->files = new Filesystem;
    }

    public function handle(): int
    {
        $className = $this->classBasename($this->getNameInput());

        if ($this->isReservedName($className)) {
            $this->error('The name ['.$className.'] is reserved by PHP.');

            return static::FAILURE;
        }

        $class = $this->qualifyClass($this->getNameInput());
        $path = $this->getPath($class);

        if (! $this->writeFile($path, $this->tidyImports($this->buildClass($class), $this->getImports($class)))) {
            return static::FAILURE;
        }

        $this->afterGenerate($class, $path);

        return static::SUCCESS;
    }

    /**
     * Hook for registering the generated class, creating related files, ...
     */
    protected function afterGenerate(string $class, string $path): void {}

    protected function getNameInput(): string
    {
        $name = trim((string) $this->argument('name'));

        return str_ends_with($name, '.php') ? substr($name, 0, -4) : $name;
    }

    public function getPath(string $class): string
    {
        return $this->package()->pathForClass($class)
            ?? throw new RuntimeException("Class [$class] is not covered by any PSR-4 mapping in composer.json.");
    }

    protected function getType(): string
    {
        return $this->type;
    }

    /**
     * Write a generated file, refusing to overwrite an existing one unless --force is given.
     */
    protected function writeFile(string $path, string $contents): bool
    {
        if ($this->files->isFile($path) && ! ($this->hasOption('force') && $this->option('force'))) {
            $this->error($this->getType().' ['.$this->package()->relativePath($path).'] already exists!');

            return false;
        }

        $this->makeDirectory($path);
        $this->files->put($path, $contents);

        $this->output()->writeln(sprintf(
            '  <fg=blue;options=bold>INFO</> %s [<options=bold>%s</>] created successfully.',
            $this->getType(),
            $this->package()->relativePath($path)
        ));

        return true;
    }

    /**
     * Make the directory for the file if necessary.
     */
    public function makeDirectory(string $path): string
    {
        if (! $this->files->isDirectory(dirname($path))) {
            $this->files->makeDirectory(dirname($path), 0755, true);
        }

        return $path;
    }

    /**
     * Make sure composer.json autoloads $namespace from $directory (PSR-4).
     */
    protected function ensureAutoload(string $namespace, string $directory): void
    {
        if (array_key_exists($namespace, $this->package()->psr4())) {
            return;
        }

        $composer = $this->package()->composer();
        $composer->set('autoload.psr-4', array_merge(
            (array) $composer->get('autoload.psr-4', []),
            [$namespace.'\\' => rtrim($directory, '/').'/']
        ));
        $composer->save();
        $this->package()->refresh();

        $this->output()->writeln(sprintf(
            '  <fg=green;options=bold>DONE</> Added "%s\\\\" => "%s/" to composer.json autoload. Run <options=bold>composer dump-autoload</>.',
            $namespace,
            rtrim($directory, '/')
        ));
    }

    protected function registrar(): ProviderRegistrar
    {
        return new ProviderRegistrar($this->package());
    }

    /**
     * Print the outcome of registering something in the service provider.
     */
    protected function report(Registration $registration): void
    {
        if ($registration->status === Registration::REGISTERED && $registration->warning !== null) {
            $this->output()->writeln('  <fg=green;options=bold>DONE</> '.$registration->message);
            $this->output()->writeln('  <fg=yellow;options=bold>WARN</> '.$registration->warning);

            return;
        }

        match ($registration->status) {
            Registration::REGISTERED => $this->output()->writeln('  <fg=green;options=bold>DONE</> '.$registration->message),
            Registration::MANUAL, Registration::NO_PROVIDER => $this->output()->writeln('  <fg=yellow;options=bold>WARN</> '.$registration->message),
            default => null,
        };
    }

    /**
     * Extra classes to import into the generated class (e.g. the related model).
     *
     * @return list<string>
     */
    protected function getImports(string $class): array
    {
        return [];
    }

    /**
     * Merge extra imports into the `use` block after the namespace, sort it and
     * normalise the blank lines around it. Imports from the class's own namespace are dropped.
     *
     * @param  list<string>  $extra
     */
    protected function tidyImports(string $code, array $extra = []): string
    {
        if (! preg_match('/^namespace ([^;]+);\n/m', $code, $match, PREG_OFFSET_CAPTURE)) {
            return $code;
        }

        $namespace = $match[1][0];
        $headerEnd = $match[0][1] + strlen($match[0][0]);
        $rest = substr($code, $headerEnd);

        preg_match('/^(?:use [^;{]+;\n|\n)*/', $rest, $block);
        preg_match_all('/^use ([^;{]+);/m', $block[0], $uses);

        $imports = array_filter(
            array_unique(array_map(fn (string $import) => trim($import, '\\ '), [...$uses[1], ...$extra])),
            fn (string $import) => $this->getClassNamespace($import) !== $namespace || str_contains($import, ' ')
        );

        sort($imports, SORT_STRING | SORT_FLAG_CASE);

        $useBlock = $imports === [] ? '' : implode("\n", array_map(fn (string $import) => "use $import;", $imports))."\n\n";

        return substr($code, 0, $headerEnd)."\n".$useBlock.substr($rest, strlen($block[0]));
    }

    protected function getOptions(): array
    {
        return [
            ['force', 'f', InputOption::VALUE_NONE, 'Create the '.strtolower($this->getType()).' even if it already exists'],
        ];
    }

    protected function getArguments(): array
    {
        return [
            ['name', InputArgument::REQUIRED, 'The name of the '.strtolower($this->getType())],
        ];
    }

    protected function getValidationRules(): array
    {
        return [
            'name' => [
                new Assert\NotBlank(message: 'The name argument is required.'),
                new Assert\Regex(
                    pattern: '/^[A-Za-z_][A-Za-z0-9_]*(?:[\/\\\\][A-Za-z_][A-Za-z0-9_]*)*(?:\.php)?$/',
                    message: 'The name must be a valid class name, optionally nested with "/" (e.g. "Admin/UserService").',
                ),
            ],
        ];
    }
}
