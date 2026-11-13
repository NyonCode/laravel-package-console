<?php

namespace NyonCode\LaravelPackageConsole\Commands;

use NyonCode\LaravelPackageConsole\Kernel;
use NyonCode\LaravelPackageConsole\Support\Monorepo;
use NyonCode\LaravelPackageConsole\Support\Package;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputOption;

#[AsCommand(name: 'package:info', description: 'Show what the console knows about the package')]
final class PackageInfoCommand extends BaseCommand
{
    protected function worksOnPackage(): bool
    {
        return ! $this->option('all');
    }

    public function handle(): int
    {
        if ($this->option('all')) {
            return $this->all();
        }

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
        $monorepo = Kernel::workspace()->monorepo();
        $relative = $monorepo?->relative($package->path());

        (new Table($this->output()))
            ->setRows([
                ['Path', $package->path()],
                ...($monorepo !== null && $relative !== null && $relative !== '.' ? [['Monorepo', $monorepo->root()."\n".implode(', ', array_map(fn (string $layout) => Monorepo::LABELS[$layout], $monorepo->layouts() ?: [Monorepo::PATH]))."\n--package=$relative"]] : []),
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

    /**
     * One row per package of the monorepo.
     */
    private function all(): int
    {
        $packages = Kernel::workspace()->packages();

        if ($packages === []) {
            $this->warning('--all lists the packages of a monorepo, and there are none: see "Monorepos" in the docs for how Anvil finds them.');

            return self::FAILURE;
        }

        $rows = [];

        foreach ($packages as $relative => $path) {
            $package = Kernel::usePackage($path);
            $rows[] = [
                $relative,
                $package->name(),
                $package->shortName(),
                $this->tryValue(fn () => $package->rootNamespace()),
                $package->providerMode() ?? '-',
                $package->mainProvider() ?? '-',
            ];
        }

        (new Table($this->output()))
            ->setHeaders(['Package', 'Composer name', 'Short name', 'Root namespace', 'Mode', 'Main provider'])
            ->setRows($rows)
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

    protected function getOptions(): array
    {
        return [
            ['all', null, InputOption::VALUE_NONE, 'List every package of the monorepo'],
        ];
    }
}
