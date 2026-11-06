<?php

namespace NyonCode\LaravelPackageConsole\Support;

use ReflectionClass;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Finder\Finder;

/**
 * Registers every concrete command class with an #[AsCommand] attribute found in src/Commands.
 */
class CommandDiscovery
{
    private const NAMESPACE = 'NyonCode\\LaravelPackageConsole\\Commands';

    public function __construct(
        private readonly Application $application,
        private readonly string $commandPath,
    ) {}

    public function registerCommands(): void
    {
        foreach ($this->discoverCommands() as $commandClass) {
            $command = new $commandClass;

            if ($command instanceof Command) {
                // Symfony 7.4+ has addCommand(); add() was removed in Symfony 8.
                method_exists($this->application, 'addCommand')
                    ? $this->application->addCommand($command)
                    : $this->application->add($command);
            }
        }
    }

    /**
     * @return list<class-string<Command>>
     */
    private function discoverCommands(): array
    {
        $commands = [];
        $finder = (new Finder)->files()->in($this->commandPath)->name('*.php')->sortByName();

        foreach ($finder as $file) {
            $className = $this->resolveClassName($file->getRelativePathname());

            if ($className === null) {
                continue;
            }

            $reflection = new ReflectionClass($className);

            if ($reflection->isInstantiable() && $reflection->getAttributes(AsCommand::class) !== []) {
                $commands[] = $className;
            }
        }

        return $commands;
    }

    private function resolveClassName(string $relativePathname): ?string
    {
        $relative = str_replace(['/', '\\'], '\\', substr($relativePathname, 0, -4));
        $className = self::NAMESPACE.'\\'.$relative;

        return class_exists($className) ? $className : null;
    }
}
