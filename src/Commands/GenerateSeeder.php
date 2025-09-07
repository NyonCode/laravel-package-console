<?php

namespace NyonCode\LaravelPackageConsole\Commands;

use Exception;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(
    name: 'make:seed',
    description: 'Create a new database seeder'
)]
class GenerateSeeder extends GenerateCommand
{
    /**
     * Execute the console command.
     *
     * @throws Exception
     */
    public function handle(): ?bool
    {
        $this->call('make:structure');

        return true;
    }
}
