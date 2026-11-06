<?php

namespace NyonCode\LaravelPackageConsole\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Validator\Constraints as Assert;

#[AsCommand(name: 'make:facade', description: 'Create a new facade class')]
final class FacadeMakeCommand extends GenerateCommand
{
    protected string $type = 'Facade';

    protected string $namespace = 'Facades';

    protected string $stub = 'facade.stub';

    /**
     * The accessor is --accessor (a container binding name) or, by default,
     * the class of the same name in the package root namespace.
     */
    protected function getReplacements(string $name): array
    {
        $accessor = $this->option('accessor');
        $class = '\\'.$this->packageRootNamespace().'\\'.$this->classBasename($name);

        return [
            'facadeAccessor' => $accessor ? "'$accessor'" : $class.'::class',
            'accessorClass' => $accessor ?: $class,
        ];
    }

    protected function getValidationRules(): array
    {
        return [
            ...parent::getValidationRules(),
            '--accessor' => [new Assert\Regex(
                pattern: '/^[A-Za-z0-9_.:\\\\-]+$/',
                message: 'The accessor must be a container binding name or a class name.',
            )],
        ];
    }

    protected function getOptions(): array
    {
        return [
            ['accessor', null, InputOption::VALUE_REQUIRED, 'The container binding the facade resolves (defaults to the root class of the same name)'],
            ...parent::getOptions(),
        ];
    }
}
