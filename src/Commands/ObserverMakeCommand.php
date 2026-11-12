<?php

namespace NyonCode\LaravelPackageConsole\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;

#[AsCommand(name: 'make:observer', description: 'Create a new observer class')]
final class ObserverMakeCommand extends GenerateCommand
{
    use Concerns\ResolvesModels;

    protected string $type = 'Observer';

    protected string $example = 'PostObserver';

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

    protected function afterPromptingForMissingArguments(): void
    {
        if ($this->option('model')) {
            return;
        }

        $model = $this->prompts()->suggest(
            $this->trans('What model should this observer apply to?'),
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
            ['model', 'm', InputOption::VALUE_REQUIRED, 'The model that the observer applies to'],
            ...parent::getOptions(),
        ];
    }
}
