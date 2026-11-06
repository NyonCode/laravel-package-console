<?php

namespace NyonCode\LaravelPackageConsole\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;

#[AsCommand(name: 'make:config', description: 'Create a new config file')]
final class ConfigMakeCommand extends FileGenerateCommand
{
    protected string $type = 'Config';

    protected string $stub = 'config.stub';

    protected function getNameInput(): string
    {
        $name = parent::getNameInput() ?: $this->package()->shortName();

        return str_ends_with($name, '.php') ? substr($name, 0, -4) : $name;
    }

    protected function getFilePath(string $name): string
    {
        return $this->package()->path("config/$name.php");
    }

    protected function afterGenerate(string $name, string $path): void
    {
        $this->report($this->registrar()->config(basename($path)));
    }

    protected function getArguments(): array
    {
        return [['name', InputArgument::OPTIONAL, 'The name of the config file (defaults to the package short name)']];
    }

    /**
     * The name is optional (it defaults to the short name), so only its format is checked.
     */
    protected function getValidationRules(): array
    {
        return ['name' => array_slice(parent::getValidationRules()['name'], 1)];
    }
}
