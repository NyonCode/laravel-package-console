<?php

namespace NyonCode\LaravelPackageConsole\Commands;

use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'make:interface', description: 'Create a new interface')]
final class InterfaceMakeCommand extends GenerateCommand
{
    protected string $type = 'Interface';

    protected string $namespace = 'Contracts';

    protected string $stub = 'interface.stub';
}
