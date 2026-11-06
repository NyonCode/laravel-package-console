<?php

namespace NyonCode\LaravelPackageConsole\Commands;

use NyonCode\LaravelPackageConsole\Support\Filesystem;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'make:structure', description: 'Create the standard Laravel package directory structure')]
final class StructureMakeCommand extends BaseCommand
{
    private const DIRECTORIES = [
        'config',
        'database/factories',
        'database/migrations',
        'database/seeders',
        'lang',
        'resources/views',
        'routes',
        'src',
        'tests/Feature',
        'tests/Unit',
    ];

    public function handle(): int
    {
        $files = new Filesystem;
        $created = 0;

        foreach (self::DIRECTORIES as $directory) {
            $path = $this->package()->path($directory);

            if ($files->isDirectory($path)) {
                $this->output()->writeln("  <fg=gray>EXISTS</> $directory");

                continue;
            }

            $files->makeDirectory($path, 0755, true);
            $this->output()->writeln("  <fg=green;options=bold>CREATE</> $directory");
            $created++;
        }

        $this->newLine();
        $this->right($created > 0 ? "Created $created directories." : 'Package structure is complete.');

        return self::SUCCESS;
    }
}
