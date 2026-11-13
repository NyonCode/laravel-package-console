<?php

declare(strict_types=1);

namespace NyonCode\LaravelPackageConsole;

use Exception;
use NyonCode\LaravelPackageConsole\Support\CommandDiscovery;
use NyonCode\LaravelPackageConsole\Support\Config;
use NyonCode\LaravelPackageConsole\Support\Package;
use NyonCode\LaravelPackageConsole\Support\Prompts;
use NyonCode\LaravelPackageConsole\Support\Workspace;
use RuntimeException;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputOption;

class Kernel
{
    private static ?Config $config = null;

    private static ?Package $package = null;

    private static ?Workspace $workspace = null;

    /**
     * Whether a command chose the package already (see selectPackage()).
     */
    private static bool $selected = false;

    public Application $application;

    /**
     * @param  string|null  $directory  The directory the console works in (defaults to the working directory):
     *                                  the root of the package being developed, a directory inside it, or a monorepo.
     */
    public function __construct(?string $directory = null)
    {
        $directory ??= (string) getcwd();

        self::$config = new Config($directory);
        self::$package = new Package(self::$config->packagePath());
        self::$workspace = new Workspace(self::$config->packagePath());
        self::$selected = false;

        $this->application = new Application(anvil_config('name'), anvil_config('version'));
        $this->application->getDefinition()->addOption(new InputOption(
            'package',
            null,
            InputOption::VALUE_REQUIRED,
            'The package to work on: a package of the monorepo (directory, directory name or Composer name) or a directory',
            null,
            fn () => array_keys(self::workspace()->packages()),
        ));
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

    public static function workspace(): Workspace
    {
        return self::$workspace ?? throw new RuntimeException('Kernel was not initialized. Please instantiate Kernel first.');
    }

    /**
     * Work on the package in $path from now on.
     */
    public static function usePackage(string $path): Package
    {
        self::config()->usePackagePath($path);

        return self::$package = new Package(self::config()->packagePath());
    }

    /**
     * Choose the package the command works on (see Workspace). Once chosen, it stays for the
     * commands it calls, unless one names another with --package.
     *
     * @param  string|null  $selector  --package, else the ANVIL_PACKAGE environment variable.
     */
    public static function selectPackage(?string $selector, bool $creating, Prompts $prompts): Package
    {
        if (($selector === null || $selector === '') && self::$selected) {
            return self::package();
        }

        $selector = $selector ?: (getenv('ANVIL_PACKAGE') ?: null);
        $package = self::usePackage(self::workspace()->select($selector, $creating, $prompts));
        self::$selected = true;

        return $package;
    }

    /**
     * @throws Exception
     */
    public function run(): int
    {
        return $this->application->run();
    }
}
