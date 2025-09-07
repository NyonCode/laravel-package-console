<?php

namespace NyonCode\LaravelPackageConsole\Commands;

use NyonCode\LaravelPackageConsole\Concerns\HasClassResolver;
use NyonCode\LaravelPackageConsole\Concerns\HasNamespaceResolver;
use NyonCode\LaravelPackageConsole\Concerns\HasReservedNames;
use NyonCode\LaravelPackageConsole\Concerns\HasResolver;
use NyonCode\LaravelPackageConsole\Concerns\HasStubResolver;
use NyonCode\LaravelPackageConsole\Concerns\InputValidator;
use NyonCode\LaravelPackageConsole\Support\Filesystem;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\String\Inflector\EnglishInflector;

abstract class GenerateCommand extends BaseCommand
{
    use HasClassResolver;
    use HasNamespaceResolver;
    use HasReservedNames;
    use HasResolver;
    use HasStubResolver;
    use InputValidator;

    protected Filesystem $files;

    protected string $type = '';

    public function __construct()
    {
        parent::__construct();
        $this->files = new Filesystem;
        $this->inflector = new EnglishInflector;
    }

    public function handle(): ?bool
    {
        if ($this->isReservedName($this->getNameInput())) {
            $this->error('The name ['.$this->getNameInput().'] is reserved by PHP.');

            return static::FAILURE;
        }

        $name = $this->qualifyClass($this->getNameInput());
        $path = $this->getPath($name);

        if (
            (! $this->hasOption('force') || ! $this->option('force'))
            && $this->files->isFile($path)
        ) {
            $this->error('The '.$this->getType().' ['.$this->argument('name').'] already exists!');

            return static::FAILURE;
        }

        $this->makeDirectory($path);

        if (windows_os()) {
            $path = str_replace('\\', '/', $this->getPath($this->qualifyClass($this->getNameInput())));
        }

        $this->files->put($path, $this->sortImports($this->buildClass($this->getNameInput())));

        $this->success('Creating '.$this->getType().' ['.$this->getNameInput().'] in '.$path);

        return static::SUCCESS;
    }

    protected function getNameInput(): string
    {
        $name = $this->argument('name');

        if (str_contains($name, '.php')) {
            return substr($name, 0, -4);
        }

        return $name;
    }

    public function getPath(string $qualifiedClass): string
    {
        return $this->qualifyPath('src'.$qualifiedClass);
    }

    /**
     * Gets the type of generator.
     *
     * @return string The type of generator.
     */
    protected function getType(): string
    {
        return $this->type;
    }

    /**
     * Make the directory for the class if necessary.
     */
    public function makeDirectory(string $path): string
    {
        if (! $this->files->isDirectory(dirname($path))) {
            $this->files->makeDirectory(dirname($path));
        }

        return $path;
    }

    /**
     * Sorts the imports in the given stub.
     *
     *
     * @return array|string|string[]
     */
    protected function sortImports($stub): array|string
    {
        if (preg_match('/(?P<imports>(?:^use [^;{]+;$\n?)+)/m', $stub, $match)) {
            $imports = explode("\n", trim($match['imports']));

            sort($imports);

            return str_replace(trim($match['imports']), implode("\n", $imports), $stub);
        }

        return $stub;
    }

    /**
     * Get the console command options.
     *
     * This method returns an array of options available for the command.
     * Each option is an array containing the option name, shortcut,
     * option mode, and a description.
     *
     * @return array The array of command options.
     */
    protected function getOptions(): array
    {
        return [
            [
                'force',
                'f',
                InputOption::VALUE_NONE,
                'Force the operation to run when the '.strtolower($this->getType()).' already exists'],
        ];
    }

    /**
     * Gets the console command arguments.
     *
     * This method returns an array of arguments available for the command.
     * Each argument is an array containing the argument name, mode, and a description.
     *
     * @return array The array of command arguments.
     */
    protected function getArguments(): array
    {
        return [
            [
                'name',
                InputOption::VALUE_REQUIRED,
                'The name of the '.strtolower($this->getType()),
            ],
        ];
    }
}
