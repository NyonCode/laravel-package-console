<?php

namespace NyonCode\LaravelPackageConsole\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Validator\Constraints as Assert;

use function Symfony\Component\String\u;

/**
 * Creates a Blade component usable as <x-{package}::name />: the class in View\Components
 * (registered with a component namespace) plus its view in resources/views/components.
 * With --view only an anonymous component (the view) is created.
 */
#[AsCommand(name: 'make:component', description: 'Create a new view component class')]
final class ComponentMakeCommand extends GenerateCommand
{
    use Concerns\CreatesViews;

    protected string $type = 'Component';

    protected string $example = 'Alert';

    protected string $namespace = 'View\\Components';

    protected string $stub = 'component.stub';

    public function handle(): int
    {
        if (! $this->option('view')) {
            return parent::handle();
        }

        $view = 'components.'.$this->dottedName($this->getNameInput());
        $path = $this->package()->path('resources/views/'.str_replace('.', '/', $view).'.blade.php');

        if ($this->files->isFile($path) && ! $this->option('force')) {
            $this->error('View ['.$this->package()->relativePath($path).'] already exists!');

            return self::FAILURE;
        }

        $this->createView($view, 'view.stub');
        $this->usage($view);

        return self::SUCCESS;
    }

    protected function getReplacements(string $name): array
    {
        $view = $this->package()->shortName().'::'.$this->viewName($name);

        return [
            'view' => $this->option('inline')
                ? "<<<'blade'\n            <div>\n                {{-- $view --}}\n            </div>\n        blade"
                : "view('$view')",
        ];
    }

    protected function afterGenerate(string $class, string $path): void
    {
        if (! $this->option('inline')) {
            $this->createView($this->viewName($class), 'view.stub');
        }

        $this->report($this->registrar()->componentNamespace(
            $this->getDefaultNamespace($this->packageRootNamespace())
        ));

        $this->usage($this->viewName($class));
    }

    /**
     * "Forms/TextInput" -> "components.forms.text-input".
     */
    private function viewName(string $class): string
    {
        $relative = str_starts_with($class, $this->getDefaultNamespace($this->packageRootNamespace()).'\\')
            ? substr($class, strlen($this->getDefaultNamespace($this->packageRootNamespace())) + 1)
            : $class;

        return 'components.'.$this->dottedName($relative);
    }

    private function dottedName(string $name): string
    {
        return implode('.', array_map(
            fn (string $segment) => u($segment)->kebab()->toString(),
            preg_split('/[\/\\\\.]/', $name)
        ));
    }

    private function usage(string $view): void
    {
        $this->output()->writeln('  <fg=gray>Use it as</> '.OutputFormatter::escape(sprintf(
            '<x-%s::%s />',
            $this->package()->shortName(),
            substr($view, strlen('components.'))
        )));
    }

    /**
     * An anonymous component is only a view, so --view accepts view names ("forms.input").
     */
    protected function getValidationRules(): array
    {
        if (! $this->option('view')) {
            return parent::getValidationRules();
        }

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

    protected function getOptions(): array
    {
        return [
            ['inline', null, InputOption::VALUE_NONE, 'Create a component that renders an inline view'],
            ['view', null, InputOption::VALUE_NONE, 'Create an anonymous component with only a view'],
            ...parent::getOptions(),
        ];
    }
}
