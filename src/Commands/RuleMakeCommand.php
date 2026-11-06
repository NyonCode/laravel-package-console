<?php

namespace NyonCode\LaravelPackageConsole\Commands;

use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'make:rule', description: 'Create a new validation rule')]
final class RuleMakeCommand extends GenerateCommand
{
    protected string $type = 'Rule';

    protected string $namespace = 'Rules';

    protected string $stub = 'rule.stub';
}
