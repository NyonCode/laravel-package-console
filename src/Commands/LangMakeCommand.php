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

    /**
     * The locale of the file being written.
     */
    private string $locale = 'en';

    protected function getNameInput(): string
    {
        $name = parent::getNameInput() ?: 'messages';

        return str_ends_with($name, '.php') ? substr($name, 0, -4) : $name;
    }

    /**
     * One file per locale of --locale ("cs,en"). The translations are registered once.
     */
    public function handle(): int
    {
        $name = $this->getNameInput();
        $created = false;
        $failed = false;

        foreach ($this->locales() as $locale) {
            $this->locale = $locale;

            if ($this->writeFile($this->getFilePath($name), $this->buildFile($name))) {
                $created = true;
            } else {
                $failed = true;
            }
        }

        if ($created) {
            $this->afterGenerate($name, '');
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    protected function getFilePath(string $name): string
    {
        $lang = $this->package()->translationsPath();

        return $this->package()->path($this->option('json') ? "$lang/{$this->locale}.json" : "$lang/{$this->locale}/$name.php");
    }

    /**
     * @return list<string>
     */
    private function locales(): array
    {
        $locales = array_values(array_unique(array_filter(array_map('trim', explode(',', (string) $this->option('locale'))))));

        foreach ($locales as $locale) {
            if (! preg_match('/^[A-Za-z0-9_-]+$/', $locale)) {
                throw new RuntimeException('The locale may only contain letters, numbers, "_" and "-".');
            }
        }

        if ($locales === []) {
            throw new RuntimeException('The --locale option needs at least one locale.');
        }

        return $locales;
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
            ['locale', 'l', InputOption::VALUE_REQUIRED, 'The locale, or several separated by commas (cs,en)', 'en'],
            ['json', null, InputOption::VALUE_NONE, 'Create a JSON translation file for the locale'],
            ...parent::getOptions(),
        ];
    }
}
