<?php

namespace NyonCode\LaravelPackageConsole\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;

/**
 * Creates a Pest test (or a PHPUnit test class with --phpunit) in tests/Feature or tests/Unit.
 */
#[AsCommand(name: 'make:test', description: 'Create a new test')]
final class TestMakeCommand extends FileGenerateCommand
{
    protected string $type = 'Test';

    public function getStub(): string
    {
        return $this->option('phpunit') ? 'test.phpunit.stub' : 'test.stub';
    }

    protected function getNameInput(): string
    {
        $name = str_replace('\\', '/', parent::getNameInput());
        $name = str_ends_with($name, '.php') ? substr($name, 0, -4) : $name;
        $segments = array_map('ucfirst', explode('/', trim($name, '/')));
        $class = array_pop($segments);
        $segments[] = str_ends_with($class, 'Test') ? $class : $class.'Test';

        return implode('/', $segments);
    }

    protected function getFilePath(string $name): string
    {
        return $this->package()->path('tests/'.$this->suite().'/'.$name.'.php');
    }

    protected function getReplacements(string $name): array
    {
        $segments = explode('/', $name);
        $class = array_pop($segments);
        $root = $this->packageRootNamespace();

        return [
            'class' => $class,
            'namespace' => implode('\\', [$root, 'Tests', $this->suite(), ...$segments]),
            'rootNamespace' => $root,
            'description' => 'works',
        ];
    }

    private function suite(): string
    {
        return $this->option('unit') ? 'Unit' : 'Feature';
    }

    protected function getOptions(): array
    {
        return [
            ['unit', 'u', InputOption::VALUE_NONE, 'Create a unit test'],
            ['phpunit', null, InputOption::VALUE_NONE, 'Create a PHPUnit test class instead of a Pest test'],
            ...parent::getOptions(),
        ];
    }
}
