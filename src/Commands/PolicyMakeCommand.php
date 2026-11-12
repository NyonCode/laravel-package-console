<?php

namespace NyonCode\LaravelPackageConsole\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;

#[AsCommand(name: 'make:policy', description: 'Create a new policy class')]
final class PolicyMakeCommand extends GenerateCommand
{
    use Concerns\ResolvesModels;

    protected string $type = 'Policy';

    protected string $example = 'PostPolicy';

    protected string $namespace = 'Policies';

    public function getStub(): string
    {
        return $this->option('model') ? 'policy.stub' : 'policy.plain.stub';
    }

    protected function getReplacements(string $name): array
    {
        if (! $this->option('model')) {
            return [];
        }

        $replacements = $this->modelReplacements($this->option('model'));

        // The policy methods already take $user, so a User model becomes $model (as in Laravel).
        if ($replacements['modelVariable'] === 'user') {
            $replacements['modelVariable'] = 'model';
        }

        return $replacements;
    }

    protected function getImports(string $class): array
    {
        return $this->option('model') ? [$this->qualifyModel($this->option('model'))] : [];
    }

    protected function afterPromptingForMissingArguments(): void
    {
        if ($this->option('model')) {
            return;
        }

        $model = $this->prompts()->suggest(
            $this->trans('What model should this policy apply to?'),
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
            ['model', 'm', InputOption::VALUE_REQUIRED, 'The model that the policy applies to'],
            ...parent::getOptions(),
        ];
    }
}
