<?php

namespace NyonCode\LaravelPackageConsole\Commands;

use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(
    name: 'make:factory',
    description: 'Generate a new factory'
)]
class GenerateFactory extends GenerateCommand
{
    protected string $stub = 'factory.stub';

    protected string $type = 'Factory';
}
