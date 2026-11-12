<?php

namespace NyonCode\LaravelPackageConsole\Commands;

use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'make:view', description: 'Create a new Blade view')]
final class ViewMakeCommand extends FileGenerateCommand
{
    protected string $type = 'View';

    protected string $example = 'posts.index';

    protected string $stub = 'view.stub';

    /**
     * "posts.index" or "posts/index" -> "posts.index".
     */
    protected function getNameInput(): string
    {
        $name = (string) preg_replace('/\.blade(\.php)?$|\.php$/', '', parent::getNameInput());

        return str_replace('/', '.', trim($name, '/.'));
    }

    protected function getFilePath(string $name): string
    {
        return $this->package()->path('resources/views/'.str_replace('.', '/', $name).'.blade.php');
    }

    protected function afterGenerate(string $name, string $path): void
    {
        $this->report($this->registrar()->views());
        $this->output()->writeln(sprintf("  <fg=gray>Render it with</> view('%s::%s')", $this->package()->shortName(), $name));
    }
}
