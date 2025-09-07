<?php

namespace NyonCode\LaravelPackageConsole\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;

#[AsCommand(name: 'make:class', description: 'Create a new class')]
final class ClassMakeCommand extends GenerateCommand
{
    protected string $type = 'Class';

    /**
     * Get the stub file for the generator.
     */
    public function getStub(): string
    {
        if ($this->option('invokable')) {
            return 'class.invokable.stub';
        }

        return 'class.stub';
    }

    /**
     * Get the console command arguments.
     *
     * @return array[]
     */
    protected function getOptions(): array
    {
        return [
            [
                'invokable',
                'i',
                InputOption::VALUE_NONE,
                'Generate a single method, invokable class',
            ],
            [
                'force',
                'f',
                InputOption::VALUE_NONE,
                'Create the class even if the class already exists',
            ],
        ];
    }

    /**
     * Get the default namespace for the class.
     */
    protected function getDefaultNamespace(string $rootNamespace): string
    {
        return $rootNamespace;
    }
}
