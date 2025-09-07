<?php

namespace NyonCode\LaravelPackageConsole\Support;

use ReflectionClass;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Finder\Finder;

class CommandDiscovery
{
    private Application $application;

    private string $commandPath;

    /**
     * Constructor
     */
    public function __construct(Application $application, string $commandPath)
    {
        $this->application = $application;
        $this->commandPath = $commandPath;
    }

    /**
     * Register commands
     */
    public function registerCommands(): void
    {
        $commands = $this->discoverCommands();

        foreach ($commands as $commandClass) {
            $commandInstance = new $commandClass;
            if ($commandInstance instanceof Command) {
                $this->application->add($commandInstance);
            }
        }
    }

    /**
     * Discover commands
     */
    private function discoverCommands(): array
    {
        $commands = [];
        $finder = new Finder;
        $finder->files()->in($this->commandPath)->name('*.php');

        foreach ($finder as $file) {
            $className = $this->resolveClassName($file->getRealPath());

            if ($className && class_exists($className)) {
                $reflection = new ReflectionClass($className);

                if ($reflection->getAttributes(AsCommand::class)) {
                    $commands[] = $className;
                }
            }
        }

        return $commands;
    }

    /**
     * Resolve command class name
     */
    private function resolveClassName(string $filePath): ?string
    {
        $relativePath = str_replace([$this->commandPath, '.php'], '', $filePath);
        $relativePath = trim(str_replace(['/', '\\'], '\\', $relativePath), '\\');
        $namespace = 'NyonCode\\LaravelPackageConsole\\Commands';

        $fullClassName = "$namespace\\$relativePath";

        return class_exists($fullClassName) ? $fullClassName : null;
    }
}
