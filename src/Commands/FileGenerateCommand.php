<?php

namespace NyonCode\LaravelPackageConsole\Commands;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Generates a non-class file (config, routes, migration, view, translation, ...) from a stub.
 */
abstract class FileGenerateCommand extends GenerateCommand
{
    public function handle(): int
    {
        $name = $this->getNameInput();
        $path = $this->getFilePath($name);

        if (! $this->writeFile($path, $this->buildFile($name))) {
            return static::FAILURE;
        }

        $this->afterGenerate($name, $path);

        return static::SUCCESS;
    }

    /**
     * Absolute path of the file to generate.
     */
    abstract protected function getFilePath(string $name): string;

    protected function buildFile(string $name): string
    {
        $stub = $this->files->get($this->resolveStub($this->getStub()));

        return $this->populateStub($stub, array_merge([
            'name' => $name,
            'package' => $this->package()->shortName(),
            'packageName' => $this->package()->displayName(),
        ], $this->getReplacements($name)));
    }

    protected function getNameInput(): string
    {
        return trim((string) $this->argument('name'));
    }

    protected function getValidationRules(): array
    {
        return [
            'name' => [
                new Assert\NotBlank(message: 'The name argument is required.'),
                new Assert\Regex(
                    pattern: '/^(?!.*\.\.)[A-Za-z0-9_][A-Za-z0-9_\-.\/]*$/',
                    message: 'The name may only contain letters, numbers, "_", "-", "." and "/".',
                ),
            ],
        ];
    }
}
