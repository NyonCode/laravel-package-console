<?php

namespace NyonCode\LaravelPackageConsole\Commands;

use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'make:request', description: 'Create a new form request class')]
final class RequestMakeCommand extends GenerateCommand
{
    protected string $type = 'Request';

    protected string $example = 'StorePostRequest';

    protected string $namespace = 'Http\\Requests';

    protected string $stub = 'request.stub';
}
