<?php

namespace NyonCode\LaravelPackageConsole\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;

#[AsCommand(name: 'make:exception', description: 'Create a new custom exception class')]
final class ExceptionMakeCommand extends GenerateCommand
{
    protected string $type = 'Exception';

    protected string $example = 'InvalidPostException';

    protected string $namespace = 'Exceptions';

    public function getStub(): string
    {
        return $this->option('render') ? 'exception.render-report.stub' : 'exception.stub';
    }

    protected function getOptions(): array
    {
        return [
            ['render', null, InputOption::VALUE_NONE, 'Create the exception with report() and render() methods'],
            ...parent::getOptions(),
        ];
    }
}
