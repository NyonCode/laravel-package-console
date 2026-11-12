<?php

namespace NyonCode\LaravelPackageConsole\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Validator\Constraints as Assert;

#[AsCommand(name: 'make:middleware', description: 'Create a new HTTP middleware class')]
final class MiddlewareMakeCommand extends GenerateCommand
{
    protected string $type = 'Middleware';

    protected string $example = 'EnsureAuthor';

    protected string $namespace = 'Http\\Middleware';

    protected string $stub = 'middleware.stub';

    /**
     * With --alias the middleware is registered under that alias in the service provider.
     */
    protected function afterGenerate(string $class, string $path): void
    {
        if ($alias = $this->option('alias')) {
            $this->report($this->registrar()->middlewareAlias($alias, $class));
        }
    }

    protected function getValidationRules(): array
    {
        return [
            ...parent::getValidationRules(),
            '--alias' => [new Assert\Regex(
                pattern: '/^[A-Za-z0-9_.:-]+$/',
                message: 'The alias may only contain letters, numbers, "_", "-", "." and ":".',
            )],
        ];
    }

    protected function getOptions(): array
    {
        return [
            ['alias', 'a', InputOption::VALUE_REQUIRED, 'Register the middleware under this alias'],
            ...parent::getOptions(),
        ];
    }
}
