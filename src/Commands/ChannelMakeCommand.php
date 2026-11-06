<?php

namespace NyonCode\LaravelPackageConsole\Commands;

use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'make:channel', description: 'Create a new broadcast channel class')]
final class ChannelMakeCommand extends GenerateCommand
{
    protected string $type = 'Channel';

    protected string $namespace = 'Broadcasting';

    protected string $stub = 'channel.stub';
}
