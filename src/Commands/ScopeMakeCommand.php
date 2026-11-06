<?php

namespace NyonCode\LaravelPackageConsole\Commands;

use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'make:scope', description: 'Create a new Eloquent scope class')]
final class ScopeMakeCommand extends GenerateCommand
{
    protected string $type = 'Scope';

    protected string $namespace = 'Models\\Scopes';

    protected string $stub = 'scope.stub';
}
