<?php

namespace NyonCode\LaravelPackageConsole\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;

#[AsCommand(name: 'make:enum', description: 'Create a new enum')]
final class EnumMakeCommand extends GenerateCommand
{
    protected string $type = 'Enum';

    protected string $namespace = 'Enums';

    public function getStub(): string
    {
        return $this->option('string') || $this->option('int') ? 'enum.backed.stub' : 'enum.stub';
    }

    protected function getReplacements(string $name): array
    {
        return ['type' => $this->option('int') ? 'int' : 'string'];
    }

    protected function getOptions(): array
    {
        return [
            ['string', 's', InputOption::VALUE_NONE, 'Generate a string backed enum'],
            ['int', 'i', InputOption::VALUE_NONE, 'Generate an integer backed enum'],
            ...parent::getOptions(),
        ];
    }
}
