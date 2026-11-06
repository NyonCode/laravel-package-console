<?php

namespace NyonCode\LaravelPackageConsole\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;

/**
 * "web" and "api" get a prefixed, named route group with the matching middleware;
 * "channels" is registered as broadcast channels instead of routes.
 */
#[AsCommand(name: 'make:route', description: 'Create a new route file (web, api, channels, ...)')]
final class RouteMakeCommand extends FileGenerateCommand
{
    protected string $type = 'Routes';

    public function getStub(): string
    {
        return match ($this->getNameInput()) {
            'web', 'api', 'channels' => 'route.'.$this->getNameInput().'.stub',
            default => 'route.stub',
        };
    }

    protected function getNameInput(): string
    {
        $name = parent::getNameInput() ?: 'web';

        return str_ends_with($name, '.php') ? substr($name, 0, -4) : $name;
    }

    protected function getFilePath(string $name): string
    {
        return $this->package()->path("routes/$name.php");
    }

    protected function afterGenerate(string $name, string $path): void
    {
        $this->report($name === 'channels'
            ? $this->registrar()->broadcastChannels(basename($path))
            : $this->registrar()->routes(basename($path)));
    }

    protected function getArguments(): array
    {
        return [['name', InputArgument::OPTIONAL, 'The name of the route file: web, api, channels or any other', 'web']];
    }
}
