<?php

namespace NyonCode\LaravelPackageConsole\Commands;

use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(
    name: 'make:migration',
    description: 'Create a new migration file',
)]
class GenerateMigration extends GenerateCommand
{
    public function build()
    {
        // TODO: Implement handle() method.
    }
}
