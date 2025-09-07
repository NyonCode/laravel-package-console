<?php

namespace NyonCode\LaravelPackageConsole\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

use function Symfony\Component\String\u;

#[AsCommand(name: 'make:console', description: 'Create a new console command')]
final class ConsoleMakeCommand extends GenerateCommand
{
    protected string $stub = 'console.stub';

    protected string $type = 'Console command';

    /**
     * Replaces placeholders in the given stub with the correct class name.
     *
     *
     * @return $this
     */
    protected function replaceClass(string &$stub, string $class): static
    {
        parent::replaceClass($stub, $class);

        $command = $this->option('command') ?: 'app:'.u($class)->kebab();

        $stub = str_replace(
            ['dummy:command', '{{ command }}', '{{command}}'],
            $command,
            $stub
        );

        return $this;
    }

    /**
     * Get the default namespace for the class.
     */
    protected function getDefaultNamespace(string $rootNamespace): string
    {
        return $rootNamespace.'\\Commands';
    }

    /**
     * Get the console command arguments.
     */
    protected function getArguments(): array
    {
        return [['name', InputArgument::REQUIRED, 'The name of the command']];
    }

    /**
     * Get the console command options.
     *
     * @return array[]
     */
    protected function getOptions(): array
    {
        return [
            [
                'force',
                'f',
                InputOption::VALUE_NONE,
                'Create the class even if the console command already exists',
            ],
            [
                'command',
                null,
                InputOption::VALUE_OPTIONAL,
                'The terminal command that will be used to invoke the class',
            ],
        ];
    }
}
