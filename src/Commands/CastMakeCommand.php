<?php

namespace NyonCode\LaravelPackageConsole\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;

#[AsCommand(name: 'make:cast', description: 'Create a new custom Eloquent cast class')]
final class CastMakeCommand extends GenerateCommand
{
    protected string $type = 'Cast';

    protected string $example = 'Json';

    protected string $namespace = 'Casts';

    public function getStub(): string
    {
        return $this->option('inbound') ? 'cast.inbound.stub' : 'cast.stub';
    }

    protected function getOptions(): array
    {
        return [
            ['inbound', null, InputOption::VALUE_NONE, 'Generate an inbound cast class'],
            ...parent::getOptions(),
        ];
    }
}
