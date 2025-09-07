<?php

declare(strict_types=1);

namespace NyonCode\LaravelPackageConsole;

use Exception;
use NyonCode\LaravelPackageConsole\Support\CommandDiscovery;
use NyonCode\LaravelPackageConsole\Support\Config;
use RuntimeException;
use Symfony\Component\Console\Application;

class Kernel
{
    private static Config $config;

    public Application $application;

    public function __construct(?string $packagePath = null)
    {
        self::$config = new Config($packagePath);

        $this->application = new Application(pcli_config('name'), pcli_config('version'));
        $this->registerCommands();
    }

    private function registerCommands(): void
    {
        $discovery = new CommandDiscovery($this->application, pcli_app_path('Commands'));
        $discovery->registerCommands();
    }

    public static function config(): Config
    {
        if (self::$config === null) {
            throw new RuntimeException('Kernel was not initialized. Please instantiate Kernel first.');
        }

        return self::$config;
    }

    /**
     * @throws Exception
     */
    public function run(): void
    {
        $this->application->run();
    }
}
