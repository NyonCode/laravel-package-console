<?php

namespace NyonCode\LaravelPackageConsole\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;

#[AsCommand(name: 'make:controller', description: 'Create a new controller class')]
final class ControllerMakeCommand extends GenerateCommand
{
    use Concerns\ResolvesModels;

    protected string $type = 'Controller';

    protected string $namespace = 'Http\\Controllers';

    public function getStub(): string
    {
        return match (true) {
            (bool) $this->option('invokable') => 'controller.invokable.stub',
            (bool) $this->option('api') => 'controller.api.stub',
            $this->option('resource') || $this->option('model') => 'controller.resource.stub',
            default => 'controller.plain.stub',
        };
    }

    protected function getReplacements(string $name): array
    {
        $model = $this->option('model');

        return [
            'modelParameter' => $model
                ? $this->classBasename($model).' $'.lcfirst($this->classBasename($model))
                : 'string $id',
        ];
    }

    protected function getImports(string $class): array
    {
        // An invokable controller does not reference the model.
        return $this->option('model') && ! $this->option('invokable') ? [$this->qualifyModel($this->option('model'))] : [];
    }

    protected function getOptions(): array
    {
        return [
            ['resource', 'r', InputOption::VALUE_NONE, 'Generate a resource controller class'],
            ['api', null, InputOption::VALUE_NONE, 'Exclude the create and edit methods from the controller'],
            ['invokable', 'i', InputOption::VALUE_NONE, 'Generate a single method, invokable controller class'],
            ['model', 'm', InputOption::VALUE_REQUIRED, 'Generate a resource controller for the given model'],
            ...parent::getOptions(),
        ];
    }
}
