<?php

namespace NyonCode\LaravelPackageConsole\Commands;

use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'make:trait', description: 'Create a new trait')]
final class TraitMakeCommand extends GenerateCommand
{
    protected string $type = 'Trait';

    protected string $namespace = 'Concerns';

    protected string $stub = 'trait.stub';
}
