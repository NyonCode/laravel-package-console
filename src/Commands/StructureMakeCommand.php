<?php

namespace NyonCode\LaravelPackageConsole\Commands;

use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(
    name: 'make:structure',
    description: 'Create a directory structure'
)]
final class StructureMakeCommand extends GenerateCommand
{
    private array $defaultDirectories = [
        'config',
        'database',
        'database/migrations',
        'database/seeders',
        'database/factories',
        'lang',
        'public',
        'resources',
        'resources/views',
        'routes',
        'src/Models',
        'src/Providers',
        'tests',
    ];

    /**
     * Execute the console command.
     */
    public function handle(): ?bool
    {
        $outputMessage = [];

        $this->title('Creating directory structure...');

        $this->progressStart(count($this->defaultDirectories));

        foreach ($this->defaultDirectories as $directory) {
            $packageDirectory = pcli_package_path($directory);

            if ($this->files->isDirectory($packageDirectory)) {
                $this->files->makeDirectory($packageDirectory);
                $outputMessage[] = '<comment>Created directory: </comment>'.$directory;
            } else {
                $outputMessage[] = '<info>Directory already exists: </info>'.$directory;
            }
            $this->progressAdvance();
        }

        $this->progressFinish();

        $this->message($outputMessage);
        $this->newLine();
        $this->right('Package structure generated successfully!');

        return 0;
    }

    /**
     * Get the console command arguments.
     */
    protected function getArguments(): array
    {
        return [];
    }

    /**
     * Get the console command options.
     */
    protected function getOptions(): array
    {
        return [];
    }
}
