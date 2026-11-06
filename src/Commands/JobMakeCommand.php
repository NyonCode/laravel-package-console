<?php

namespace NyonCode\LaravelPackageConsole\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;

#[AsCommand(name: 'make:job', description: 'Create a new job class')]
final class JobMakeCommand extends GenerateCommand
{
    protected string $type = 'Job';

    protected string $namespace = 'Jobs';

    public function getStub(): string
    {
        return $this->option('sync') ? 'job.sync.stub' : 'job.stub';
    }

    protected function getOptions(): array
    {
        return [
            ['sync', null, InputOption::VALUE_NONE, 'Indicates that the job should be synchronous'],
            ...parent::getOptions(),
        ];
    }
}
