<?php

namespace NyonCode\LaravelPackageConsole\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;

use function Symfony\Component\String\u;

#[AsCommand(name: 'make:facade', description: 'Create a new facade class')]
final class FacadeMakeCommand extends GenerateCommand
{
    protected string $type = 'Facade';

    protected string $stub = 'facade.stub';

    /**
     * Replaces placeholders in the given stub with the correct class name.
     *
     *
     * @return $this
     */
    protected function replaceClass(string &$stub, string $class): static
    {
        parent::replaceClass($stub, $class);

        $facadeAccessor = $this->option('accessor') ?: u($class)->kebab();
        $stub = str_replace(
            [
                'DummyFacadeAccessor',
                '{{ facadeAccessor }}',
                '{{facadeAccessor}}',
            ],
            $facadeAccessor,
            $stub
        );

        return $this;
    }

    /**
     * Get the console command options.
     *
     * @return array[]
     */
    protected function getOptions(): array
    {
        return [
            [
                'accessor',
                null,
                InputOption::VALUE_OPTIONAL,
                'The accessor name for the facade',
            ],
            [
                'force',
                'f',
                InputOption::VALUE_NONE,
                'Force the operation to run when the facade already exists',
            ],
        ];
    }
}
