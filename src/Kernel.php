<?php

declare(strict_types=1);

namespace NyonCode\LaravelPackageConsole;

use Exception;
use NyonCode\LaravelPackageConsole\Support\CommandDiscovery;
use NyonCode\LaravelPackageConsole\Support\Config;
use NyonCode\LaravelPackageConsole\Support\Package;
use RuntimeException;
use Symfony\Component\Console\Application;

class Kernel
{
    private static ?Config $config = null;

    private static ?Package $package = null;

    public Application $application;

    /**
     * @param  string|null  $packagePath  Root of the Laravel package being developed (defaults to the working directory).
     */
    public function __construct(?string $packagePath = null)
    {
        $packagePath ??= (string) getcwd();

        self::$config = new Config($packagePath);
        self::$package = new Package(self::$config->packagePath());

        $this->application = new Application(anvil_config('name'), anvil_config('version'));
        $this->registerCommands();
    }

    private function registerCommands(): void
    {
        $discovery = new CommandDiscovery($this->application, anvil_app_path('Commands'));
        $discovery->registerCommands();
    }

    public static function config(): Config
    {
        return self::$config ?? throw new RuntimeException('Kernel was not initialized. Please instantiate Kernel first.');
    }

    public static function package(): Package
    {
        return self::$package ?? throw new RuntimeException('Kernel was not initialized. Please instantiate Kernel first.');
    }

    /**
     * @throws Exception
     */
    public function run(): int
    {
        return $this->application->run();
    }
}
