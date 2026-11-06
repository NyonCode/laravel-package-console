<?php

namespace NyonCode\LaravelPackageConsole\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;

#[AsCommand(name: 'make:observer', description: 'Create a new observer class')]
final class ObserverMakeCommand extends GenerateCommand
{
    use Concerns\ResolvesModels;

    protected string $type = 'Observer';

    protected string $namespace = 'Observers';

    public function getStub(): string
    {
        return $this->option('model') ? 'observer.stub' : 'observer.plain.stub';
    }

    protected function getReplacements(string $name): array
    {
        return $this->option('model') ? $this->modelReplacements($this->option('model')) : [];
    }

    protected function getImports(string $class): array
    {
        return $this->option('model') ? [$this->qualifyModel($this->option('model'))] : [];
    }

    protected function afterGenerate(string $class, string $path): void
    {
        if ($this->option('model')) {
            $this->output()->writeln(sprintf(
                '  <fg=gray>Attach it with #[ObservedBy(%s::class)] on the %s model.</>',
                $this->classBasename($class),
                $this->classBasename($this->qualifyModel($this->option('model')))
            ));
        }
    }

    protected function getOptions(): array
    {
        return [
            ['model', 'm', InputOption::VALUE_REQUIRED, 'The model that the observer applies to'],
            ...parent::getOptions(),
        ];
    }
}
