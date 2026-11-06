<?php

namespace NyonCode\LaravelPackageConsole\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Validator\Constraints as Assert;

use function Symfony\Component\String\u;

#[AsCommand(name: 'make:mail', description: 'Create a new email class')]
final class MailMakeCommand extends GenerateCommand
{
    use Concerns\CreatesViews;

    protected string $type = 'Mailable';

    protected string $namespace = 'Mail';

    protected string $stub = 'mail.stub';

    protected function getReplacements(string $name): array
    {
        $view = $this->viewName($name);

        return [
            'subject' => ucwords(u($this->classBasename($name))->snake()->replace('_', ' ')->toString()),
            'contentType' => $this->option('markdown') !== false ? 'markdown' : 'view',
            'view' => $view !== null ? $this->package()->shortName().'::'.$view : 'view.name',
        ];
    }

    /**
     * With --markdown or --view, create the template and register the package's views.
     */
    protected function afterGenerate(string $class, string $path): void
    {
        $view = $this->viewName($class);

        if ($view !== null) {
            $this->createView($view, $this->option('markdown') !== false ? 'mail.markdown.view.stub' : 'view.stub');
        }
    }

    /**
     * The template name without the package prefix, e.g. "mail.order-shipped".
     */
    private function viewName(string $class): ?string
    {
        foreach (['markdown', 'view'] as $option) {
            $value = $this->option($option);

            if ($value !== false) {
                return $value ?: 'mail.'.u($this->classBasename($class))->kebab()->toString();
            }
        }

        return null;
    }

    protected function getValidationRules(): array
    {
        return [
            ...parent::getValidationRules(),
            '--markdown' => [self::viewRule()],
            '--view' => [self::viewRule()],
        ];
    }

    private static function viewRule(): Assert\Regex
    {
        return new Assert\Regex(
            pattern: '/^(?!.*\.\.)[A-Za-z0-9_][A-Za-z0-9_\-.\/]*$/',
            message: 'The view name may only contain letters, numbers, "_", "-", "." and "/".',
        );
    }

    protected function getOptions(): array
    {
        return [
            ['markdown', 'm', InputOption::VALUE_OPTIONAL, 'Create a new Markdown template for the mailable', false],
            ['view', null, InputOption::VALUE_OPTIONAL, 'Create a new Blade template for the mailable', false],
            ...parent::getOptions(),
        ];
    }
}
