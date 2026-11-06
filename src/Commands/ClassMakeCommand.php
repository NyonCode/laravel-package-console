<?php

namespace NyonCode\LaravelPackageConsole\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;

#[AsCommand(name: 'make:class', description: 'Create a new class')]
final class ClassMakeCommand extends GenerateCommand
{
    protected string $type = 'Class';

    public function getStub(): string
    {
        return $this->option('invokable') ? 'class.invokable.stub' : 'class.stub';
    }

    protected function getOptions(): array
    {
        return [
            ['invokable', 'i', InputOption::VALUE_NONE, 'Generate a single method, invokable class'],
            ...parent::getOptions(),
        ];
    }
}
