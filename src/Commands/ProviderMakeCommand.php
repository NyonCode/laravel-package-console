<?php

namespace NyonCode\LaravelPackageConsole\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * The first provider (or one made with --main) is the package's main provider: it lives in the
 * root namespace, is listed first in extra.laravel.providers and is where generators register
 * resources. With laravel-package-toolkit installed it extends PackageServiceProvider.
 */
#[AsCommand(name: 'make:provider', description: 'Create a new service provider class')]
final class ProviderMakeCommand extends GenerateCommand
{
    protected string $type = 'Provider';

    protected string $example = 'EventServiceProvider';

    private bool $main = false;

    public function handle(): int
    {
        $this->main = $this->option('main') || $this->package()->mainProvider() === null;

        return parent::handle();
    }

    public function getStub(): string
    {
        return $this->main && $this->package()->usesToolkit() ? 'provider.toolkit.stub' : 'provider.stub';
    }

    protected function getDefaultNamespace(string $rootNamespace): string
    {
        return $this->main ? $rootNamespace : $rootNamespace.'\\Providers';
    }

    protected function getNameInput(): string
    {
        return parent::getNameInput() ?: $this->package()->defaultProviderName();
    }

    /**
     * Register the provider for Laravel's package discovery.
     */
    protected function afterGenerate(string $class, string $path): void
    {
        $composer = $this->package()->composer();
        $providers = array_values(array_diff($this->package()->providers(), [$class]));
        $providers = $this->main ? [$class, ...$providers] : [...$providers, $class];

        if ($providers === $this->package()->providers()) {
            return;
        }

        $composer->set('extra.laravel.providers', $providers);
        $composer->save();
        $this->package()->refresh();

        $this->output()->writeln('  <fg=green;options=bold>DONE</> Registered in composer.json "extra.laravel.providers".');
    }

    protected function getArguments(): array
    {
        return [
            ['name', InputArgument::OPTIONAL, 'The name of the provider (defaults to "{Package}ServiceProvider")'],
        ];
    }

    protected function getOptions(): array
    {
        return [
            ['main', null, InputOption::VALUE_NONE, 'Make it the main package provider (generators register resources in it)'],
            ...parent::getOptions(),
        ];
    }

    protected function getValidationRules(): array
    {
        return ['name' => [new Assert\Regex(pattern: '/^[A-Za-z_][A-Za-z0-9_]*(?:[\/\\\\][A-Za-z_][A-Za-z0-9_]*)*$/')]];
    }
}
