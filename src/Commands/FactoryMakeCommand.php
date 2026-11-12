<?php

namespace NyonCode\LaravelPackageConsole\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;

#[AsCommand(name: 'make:factory', description: 'Create a new model factory')]
final class FactoryMakeCommand extends GenerateCommand
{
    use Concerns\ResolvesModels;

    protected string $type = 'Factory';

    protected string $example = 'PostFactory';

    protected string $stub = 'factory.stub';

    /**
     * Factories live in database/factories under "{Root}\Database\Factories", which is added
     * to composer.json's autoload when missing. Models point at them with newFactory().
     */
    public function handle(): int
    {
        $this->ensureAutoload($this->getDefaultNamespace($this->packageRootNamespace()), 'database/factories');

        return parent::handle();
    }

    protected function getDefaultNamespace(string $rootNamespace): string
    {
        return $rootNamespace.'\\Database\\Factories';
    }

    protected function normalizeClassSegment(string $name): string
    {
        return str_ends_with($name, 'Factory') ? ucfirst($name) : ucfirst($name).'Factory';
    }

    protected function getReplacements(string $name): array
    {
        return ['model' => $this->classBasename($this->model($name))];
    }

    protected function getImports(string $class): array
    {
        return [$this->model($class)];
    }

    private function model(string $factory): string
    {
        return $this->qualifyModel(
            $this->option('model') ?: (string) preg_replace('/Factory$/', '', $this->classBasename($factory))
        );
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
            ['model', 'm', InputOption::VALUE_REQUIRED, 'The name of the model'],
            ...parent::getOptions(),
        ];
    }
}
