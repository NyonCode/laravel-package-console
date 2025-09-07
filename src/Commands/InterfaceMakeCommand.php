<?php

namespace NyonCode\LaravelPackageConsole\Commands;

use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(
    name: 'make:interface',
    description: 'Create a new interface'
)]
final class InterfaceMakeCommand extends GenerateCommand
{
    protected string $stub = 'interface.stub';

    protected string $type = 'Interface';

    /**
     * Get the default namespace for the class.
     */
    protected function getDefaultNamespace(string $rootNamespace): string
    {
        return match (true) {
            is_dir(pcli_package_path('Contracts')) => $rootNamespace.'\\Contracts',
            is_dir(pcli_package_path('Interfaces')) => $rootNamespace.'\\Interfaces',
            default => $rootNamespace,
        };
    }
}
