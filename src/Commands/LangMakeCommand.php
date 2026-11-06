<?php

namespace NyonCode\LaravelPackageConsole\Commands;

use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

/**
 * PHP translations are namespaced by the package short name: __('blog::messages.key').
 * JSON translations (--json) are global.
 */
#[AsCommand(name: 'make:lang', description: 'Create a new translation file')]
final class LangMakeCommand extends FileGenerateCommand
{
    protected string $type = 'Translation';

    protected string $stub = 'lang.stub';

    protected function getNameInput(): string
    {
        $name = parent::getNameInput() ?: 'messages';

        return str_ends_with($name, '.php') ? substr($name, 0, -4) : $name;
    }

    protected function getFilePath(string $name): string
    {
        $locale = (string) $this->option('locale');

        if (! preg_match('/^[A-Za-z0-9_-]+$/', $locale)) {
            throw new RuntimeException('The locale may only contain letters, numbers, "_" and "-".');
        }

        $lang = $this->package()->translationsPath();

        return $this->package()->path($this->option('json') ? "$lang/$locale.json" : "$lang/$locale/$name.php");
    }

    protected function buildFile(string $name): string
    {
        return $this->option('json') ? "{\n}\n" : parent::buildFile($name);
    }

    protected function afterGenerate(string $name, string $path): void
    {
        $this->report($this->registrar()->translations());

        if (! $this->option('json')) {
            $this->output()->writeln(sprintf("  <fg=gray>Use it with</> __('%s::%s.key')", $this->package()->shortName(), $name));
        }
    }

    protected function getArguments(): array
    {
        return [['name', InputArgument::OPTIONAL, 'The name of the translation file', 'messages']];
    }

    protected function getOptions(): array
    {
        return [
            ['locale', 'l', InputOption::VALUE_REQUIRED, 'The locale', 'en'],
            ['json', null, InputOption::VALUE_NONE, 'Create a JSON translation file for the locale'],
            ...parent::getOptions(),
        ];
    }
}
