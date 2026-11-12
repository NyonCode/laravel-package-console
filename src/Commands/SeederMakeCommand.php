<?php

namespace NyonCode\LaravelPackageConsole\Commands;

use Symfony\Component\Console\Attribute\AsCommand;

/**
 * Seeders are publish-only: they are copied into the application's database/seeders,
 * so they are written in the application's "Database\Seeders" namespace.
 */
#[AsCommand(name: 'make:seeder', description: 'Create a new seeder class', aliases: ['make:seed'])]
final class SeederMakeCommand extends GenerateCommand
{
    protected string $type = 'Seeder';

    protected string $example = 'PostSeeder';

    protected string $stub = 'seeder.stub';

    public function qualifyClass(string $name): string
    {
        return 'Database\\Seeders\\'.str_replace('/', '\\', trim($name, '\\/'));
    }

    public function getPath(string $class): string
    {
        return $this->package()->path(
            'database/seeders/'.str_replace('\\', '/', substr($class, strlen('Database\\Seeders\\'))).'.php'
        );
    }

    protected function afterGenerate(string $class, string $path): void
    {
        $this->report($this->registrar()->seeders(basename($path)));
    }
}
