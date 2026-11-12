<?php

namespace NyonCode\LaravelPackageConsole\Commands;

use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'make:event', description: 'Create a new event class')]
final class EventMakeCommand extends GenerateCommand
{
    protected string $type = 'Event';

    protected string $example = 'PostPublished';

    protected string $namespace = 'Events';

    protected string $stub = 'event.stub';
}
