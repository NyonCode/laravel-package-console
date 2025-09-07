<?php

namespace NyonCode\LaravelPackageConsole\Commands;

use NyonCode\LaravelPackageConsole\Support\JsonManager;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(
    name: 'configure:json',
    description: 'Configure JSON',
)]
class ConfigureJson extends BaseCommand
{
    public function handle(): void
    {
        $jsonManager = new JsonManager(pcli_base_path());
        var_dump($jsonManager->data);
    }
}
