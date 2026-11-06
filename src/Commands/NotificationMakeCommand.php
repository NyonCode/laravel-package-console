<?php

namespace NyonCode\LaravelPackageConsole\Commands;

use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'make:notification', description: 'Create a new notification class')]
final class NotificationMakeCommand extends GenerateCommand
{
    protected string $type = 'Notification';

    protected string $namespace = 'Notifications';

    protected string $stub = 'notification.stub';
}
