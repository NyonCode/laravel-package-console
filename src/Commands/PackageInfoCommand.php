<?php

namespace NyonCode\LaravelPackageConsole\Commands;

use NyonCode\LaravelPackageConsole\Support\Package;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Helper\Table;

#[AsCommand(name: 'package:info', description: 'Show what the console knows about the package')]
final class PackageInfoCommand extends BaseCommand
{
    public function handle(): int
    {
        $package = $this->package();

        if (! $package->exists()) {
            $this->warning('No composer.json in '.$package->path().'. Run "package:init" to create the package.');

            return self::FAILURE;
        }

        $psr4 = [];

        foreach ($package->psr4() as $namespace => $directory) {
            $psr4[] = "$namespace\\ => $directory/";
        }

        $mainProvider = $package->mainProvider();

        (new Table($this->output()))
            ->setRows([
                ['Path', $package->path()],
                ['Composer name', $package->name()],
                ['Short name', $package->shortName().' (config key, view/translation namespace, publish tag prefix)'],
                ['Root namespace', $this->tryValue(fn () => $package->rootNamespace())],
                ['PSR-4', implode("\n", $psr4) ?: '-'],
                ['Toolkit', $package->usesToolkit() ? 'yes ('.Package::TOOLKIT.')' : 'no (plain Laravel ServiceProvider)'],
                ['Main provider', $mainProvider !== null ? $mainProvider."\n".$package->relativePath((string) $package->mainProviderPath()) : '- (run make:provider)'],
                ['Providers', implode("\n", $package->providers()) ?: '-'],
                ['Laravel', (string) ($package->composer()->get('require.illuminate/support') ?? $package->composer()->get('require.illuminate/contracts') ?? '-')],
            ])
            ->render();

        return self::SUCCESS;
    }

    private function tryValue(callable $value): string
    {
        try {
            return $value();
        } catch (\RuntimeException $e) {
            return '<fg=red>'.$e->getMessage().'</>';
        }
    }
}
