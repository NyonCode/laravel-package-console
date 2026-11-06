<?php

namespace NyonCode\LaravelPackageConsole\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Validator\Constraints as Assert;

use function Symfony\Component\String\u;

#[AsCommand(name: 'make:command', description: 'Create a new Artisan command', aliases: ['make:console'])]
final class CommandMakeCommand extends GenerateCommand
{
    protected string $type = 'Console command';

    protected string $namespace = 'Commands';

    protected string $stub = 'console.stub';

    /**
     * Signature defaults to "{package}:{name}", e.g. SyncPostsCommand -> "blog:sync-posts".
     */
    protected function getReplacements(string $name): array
    {
        $class = (string) preg_replace('/Command$/', '', $this->classBasename($name));

        return [
            'command' => $this->option('command') ?: $this->package()->shortName().':'.u($class !== '' ? $class : $this->classBasename($name))->kebab(),
        ];
    }

    protected function afterGenerate(string $class, string $path): void
    {
        $this->report($this->registrar()->command($class));
    }

    protected function getValidationRules(): array
    {
        return [
            ...parent::getValidationRules(),
            '--command' => [new Assert\Regex(
                pattern: "/^[^'\\\\]+$/",
                message: 'The command signature may not contain "\'" or "\\".',
            )],
        ];
    }

    protected function getOptions(): array
    {
        return [
            ['command', null, InputOption::VALUE_REQUIRED, 'The terminal command that will be used to invoke the class'],
            ...parent::getOptions(),
        ];
    }
}
