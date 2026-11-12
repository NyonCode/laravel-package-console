<?php

namespace NyonCode\LaravelPackageConsole\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;

#[AsCommand(name: 'make:controller', description: 'Create a new controller class')]
final class ControllerMakeCommand extends GenerateCommand
{
    use Concerns\ResolvesModels;

    protected string $type = 'Controller';

    protected string $example = 'PostController';

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

    protected function afterPromptingForMissingArguments(): void
    {
        if ($this->option('resource') || $this->option('api') || $this->option('invokable') || $this->option('model')) {
            return;
        }

        $type = $this->prompts()->select($this->trans('Which type of controller would you like?'), [
            'empty' => $this->trans('Empty'),
            'resource' => $this->trans('Resource'),
            'api' => $this->trans('API resource'),
            'invokable' => $this->trans('Invokable'),
        ]);

        if ($type === 'empty') {
            return;
        }

        $this->input()->setOption((string) $type, true);

        if ($type === 'invokable') {
            return;
        }

        $model = $this->prompts()->suggest(
            $this->trans('What model should this controller be for?'),
            $this->classesIn('Models'),
            hint: $this->trans('Optional.'),
            validate: fn (string $value) => ($error = $this->validationError('--model', $value)) !== null ? $this->trans($error) : null,
        );

        if ($model !== '') {
            $this->input()->setOption('model', $model);
        }
    }

    protected function getValidationRules(): array
    {
        return [
            ...parent::getValidationRules(),
            '--model' => [$this->classOptionRule('The model must be a class name that PHP does not reserve, e.g. "Post", "Admin/Post" or "\\App\\Models\\User".')],
        ];
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
