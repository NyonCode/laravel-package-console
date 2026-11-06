<?php

namespace NyonCode\LaravelPackageConsole\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;

#[AsCommand(name: 'make:policy', description: 'Create a new policy class')]
final class PolicyMakeCommand extends GenerateCommand
{
    use Concerns\ResolvesModels;

    protected string $type = 'Policy';

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

    protected function getOptions(): array
    {
        return [
            ['model', 'm', InputOption::VALUE_REQUIRED, 'The model that the policy applies to'],
            ...parent::getOptions(),
        ];
    }
}
