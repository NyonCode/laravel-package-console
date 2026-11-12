<?php

namespace NyonCode\LaravelPackageConsole\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;

#[AsCommand(name: 'make:resource', description: 'Create a new API resource')]
final class ResourceMakeCommand extends GenerateCommand
{
    protected string $type = 'Resource';

    protected string $example = 'PostResource';

    protected string $namespace = 'Http\\Resources';

    public function getStub(): string
    {
        return $this->option('collection') || str_ends_with($this->getNameInput(), 'Collection')
            ? 'resource-collection.stub'
            : 'resource.stub';
    }

    protected function getOptions(): array
    {
        return [
            ['collection', 'c', InputOption::VALUE_NONE, 'Create a resource collection'],
            ...parent::getOptions(),
        ];
    }
}
