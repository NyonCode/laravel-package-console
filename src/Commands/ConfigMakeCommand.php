<?php

namespace NyonCode\LaravelPackageConsole\Commands;

use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'make:config', description: 'Create a new config file')]
final class ConfigMakeCommand extends GenerateCommand
{
    protected string $stub = 'config.stub';

    protected string $type = 'Config';

    /**
     * Get the path to where the config file should go.
     */
    public function getPath(string $qualifiedClass): string
    {
        return $this->qualifyPath('config'.$qualifiedClass);
    }

    /**
     * Get the default namespace for the class.
     */
    protected function getDefaultNamespace(string $rootNamespace): string
    {
        return $rootNamespace;
    }
}
