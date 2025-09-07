<?php

namespace NyonCode\LaravelPackageConsole\Commands;

use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(
    name: 'make:provider',
    description: 'Create a new provider class'
)]
final class ProviderMakeCommand extends GenerateCommand
{
    protected string $stub = 'provider.stub';

    protected string $type = 'Provider';

    /**
     * Execute the console command.
     */
    public function handle(): ?bool
    {

        return parent::handle();
        // TODO: Add provider to composer
    }
}
